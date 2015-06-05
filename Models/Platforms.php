<?php

/**
 * This is the model class for table "platforms".
 *
 * The followings are the available columns in table 'platforms':
 * @property string $id
 * @property string $server
 * @property integer $is_active
 * @property integer $is_external
 * @property integer $is_deleted
 * @property string[] $hosts
 * @property string $currency
 * @property integer $user_id
 * @property integer $is_vat
 * @property float $billing_debit
 * @property float $billing_paid
 * @property string $tag_names
 *
 * The followings are the available model relations:
 * @property Teasers[] $teasers
 * @property Tags[] $tags
 * @property PlatformsCpc[] $cpcs
 * @property Users $user
 *
 * Behaviors
 * @property DirtyObjectBehavior $dirty
 */
class Platforms extends CActiveRecord
{
    /**
     * Ключ в кэше, с данными площадки
     */
    const CACHE_KEY = 'ttarget:platform:%u:data';

    /**
     * Идентификатор площадки, на которую переносим статистику с удаляемой площадки
     */
    const DELETED_PLATFORM_ID = 23;

    public $tagIds = array();
    public $cleanTagIds = array();

	public $tname;

    public $tag_names;

    public function afterFind()
    {

        if ($this->hasRelated('tags') && !empty($this->tags))
        {
            foreach ($this->tags as $n => $service)
                $this->tagIds[] = $service->id;
            $this->cleanTagIds = $this->tagIds;
        }

        parent::afterFind();
    }

	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Platforms the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return self::getTableName();
	}

    /**
     * @return string Возвращает название таблицы отчета
     */
    protected static function getTableName()
    {
        return 'platforms';
    }

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array('server, currency', 'required'),
			array('is_active, is_external, is_vat', 'numerical', 'integerOnly' => true),
			array('server', 'length', 'max'=>250),
            array('currency', 'in', 'range' => array_keys(PlatformsCpc::getCurrencies())),
            array('hosts', 'filter', 'filter' => function($hosts) {
                preg_match_all('@(?:https?://)?(?:www\\.)?([\\w-\.]+)@i', strtolower($hosts), $result);
                return implode("\n", $result[1]);
            }),
			array('teasers', 'safe'),
            array('tagIds', 'type', 'type' => 'array'),
			array('id, server, is_active, is_external, user_id', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
            'teasers'   => array(self::MANY_MANY, 'Teasers', '{{ct_except}}(platform_id, teaser_id)'),
            'tags'      => array(self::MANY_MANY , 'Tags', 'platforms_tags(platform_id, tag_id)'),
            'cpcs'      => array(self::HAS_MANY, 'PlatformsCpcs', 'platform_id'),
            'user'      => array(self::BELONGS_TO, 'Users', 'user_id'),
		);
	}

    public function behaviors()
    {
        return array(
            'relations' => array(
                'class' => 'ext.yiiext.behaviors.EActiveRecordRelationBehavior'
            ),
            'dirty' => array(
                'class' => 'application.components.behaviors.DirtyObjectBehavior'
            ),
            'timestamps' => array(
                'class'                 => 'zii.behaviors.CTimestampBehavior',
                'createAttribute'       => 'created',
                'updateAttribute'       => null,
                'timestampExpression'   => new CDbExpression('now()'),
            ),
        );
    }

    protected function beforeSave()
    {
        $this->tags = $this->tagIds;
        return parent::beforeSave();
    }

    protected function afterSave()
    {
        if ($this->isBecameActive()) {
            // создаем задание на добавление в редис
            Yii::app()->resque->createJob('app', 'PlatformAddToRedisJob', array('platform_id' => $this->id));

        } elseif ($this->isBecameNotActive() || $this->is_deleted) {
            // Cоздаем задание на удаление из redis
            Yii::app()->resque->createJob('app', 'PlatformDelFromRedisJob', array('platform_id' => $this->id));

        } elseif (!$this->getIsNewRecord()) {
            // Cоздаем задание на апдейт данных платформы в redis
            Yii::app()->resque->createJob('app', 'PlatformUpdateInRedisJob', array(
                'platform_id'       => $this->id,
                'clean_attributes'  => $this->dirty->getCleanAttributes(),
                'tagIds'            => $this->tagIds,
                'cleanTagIds'       => $this->cleanTagIds
            ));
        }

        if ($this->dirty->isDirty()) {
            Yii::app()->cache->delete(sprintf(self::CACHE_KEY, $this->id));
        }

        parent::afterSave();
    }

    /**
     * @return bool Возвращает true, если платформа стала активна
     */
    private function isBecameActive()
    {
        return ($this->getIsNewRecord() && $this->is_active) ||
        ($this->is_active && $this->dirty->isAttributeChanged('is_active'));
    }

    /**
     * @return bool Возвращает true, если платформа сталв неактивена
     */
    private function isBecameNotActive()
    {
        return !$this->getIsNewRecord() && !$this->is_active && $this->dirty->isAttributeChanged('is_active');
    }

    /**
     * Возвращает список хостов площадки в виде массива
     *
     * @param string hosts null
     *
     * @return array
     */
    public function getHostsAsArray($hosts = '')
    {
        $hosts = $hosts ?: $this->hosts;
        return explode("\n", $hosts);
    }

    /**
     * @return Platforms Именованная группа для выборки не удаленных площадок
     */
    public function notDeleted()
    {
        $alias = $this->getTableAlias(false,false);
        $this->getDbCriteria()->mergeWith(array(
            'condition' => $alias . '.is_deleted = 0',
        ));

        return $this;
    }

    /**
     * @return Platforms Именованная группа для выборки активных площадок
     */
    public function active()
    {
        $alias = $this->getTableAlias(false,false);
        $this->getDbCriteria()->mergeWith(array(
            'condition' => $alias . '.is_active = 1',
        ));

        return $this;
    }

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'server' => 'Сервер',
			'is_active' => 'Активна',
			'is_external' => 'Внешняя сеть',
            'hosts' => 'Url-адреса серверов площадки',
            'currency' => 'валюта',
            'is_vat' => 'НДС',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria=new CDbCriteria;

		$criteria->select = "t.*, GROUP_CONCAT(tags.name SEPARATOR ' | ') AS tag_names";
		$criteria->compare('id',$this->id,true);
		$criteria->compare('is_active',$this->is_active);
		$criteria->compare('is_external',$this->is_external);
        $criteria->compare('user_id',$this->user_id);
		
		$criteria->addCondition('t.id <> 23');
        $criteria->addCondition('t.is_deleted = 0');

        $criteria->with = array('tags');
        $criteria->together = true;
        $criteria->group = 't.id';

        if(!empty($this->server)){
            $searchCriteria = new CDbCriteria();
            $searchCriteria->compare('t.server',$this->server,true,'OR');
            $searchCriteria->compare('t.id',$this->server,false,'OR');
            $criteria->mergeWith($searchCriteria);
        }


		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
            'sort'=>array(
                'attributes'=>array(
                    'tag_names' => array(
                        'asc'=>'tag_names',
                        'desc'=>'tag_names DESC',
                    ),
                    '*',
                ),
            )
        ));
	}

    /**
     * Возвращает идентификаторы всех активных площадок по заданному сегменту
     *
     * @param int[] $tags
     *
     * @return array
     */
    public function getAllActiveByTagIds($tags)
    {
        if(empty($tags))
            return array();

        $command = $this->getDbConnection()->createCommand();
        $command->selectDistinct('id');
        $command->from($this->tableName());
        $command->join('platforms_tags pt', 'pt.platform_id = id');
        $command->where('is_active = 1 AND is_external = 0 AND is_deleted = 0 AND id <> '.self::DELETED_PLATFORM_ID.' AND pt.tag_id in('.implode(',',$tags).')');

        return $command->queryColumn();
    }

    /**
     * Возвращает идентификаторы всех активных площадок, на которых может быть показа тизер
     *
     * @param int $teaser_id
     * @param bool $withExternal
     *
     * @return array
     */
    public function getAllActiveByTeaserId($teaser_id, $withExternal = false)
    {
        $command = $this->getDbConnection()->createCommand();
        $command->selectDistinct('p.id');
        $command->from($this->tableName() . ' p');
        $command->leftJoin('ct_except e', 'p.id = e.platform_id AND e.teaser_id = :t_id', array('t_id' => $teaser_id));
        $command->join('platforms_tags pt', 'pt.platform_id = p.id');
        $command->leftJoin('teasers_tags tt', 'tt.teaser_id = :t_id AND tt.tag_id = pt.tag_id', array('t_id' => $teaser_id));
        $command->where('p.is_active = 1 AND p.is_deleted = 0 '.($withExternal ? '' : 'AND p.is_external = 0 ').'AND p.id <> 23 AND e.platform_id IS NULL');
        $command->andWhere('(tt.teaser_id IS NOT NULL'.($withExternal ? ' OR p.is_external = 1' : '').')');

        return $command->queryColumn();
    }

    /**
     * Возвращает идентификаторы всех активных площадок, на которых может быть показана новость
     *
     * @todo не учитывает теги тизеров, и поэтому может вернут лишние. старый алгоритм ротации.
     * @param int $news_id
     *
     * @return array
     */
    public function getAllActiveByNewsId($news_id)
    {
        $command = $this->getDbConnection()->createCommand();
        $command->selectDistinct('p.id')
            ->from($this->tableName() . ' p')
            ->leftJoin('teasers t', 't.news_id = :news_id AND t.is_active = 1 AND t.is_deleted = 0', array(':news_id' => $news_id))
            ->leftJoin('ct_except e', 'e.platform_id = p.id AND e.teaser_id = t.id')
            ->where('e.teaser_id IS NULL AND p.is_active = 1 AND p.is_deleted = 0 AND p.id <> 23 AND p.is_external = 0');
        return $command->queryColumn();
    }

    /**
     * Возвращает идентификаторы всех активных площадок, на которых может быть показана РК
     *
     * @param int $campaign_id
     * @param bool $withExternal
     *
     * @return array
     */
    public function getAllActiveByCampaignId($campaign_id, $withExternal = false, $activeNewsOnly = true)
    {
        $command = $this->getDbConnection()->createCommand();
        $command->selectDistinct('p.id');
        $command->from($this->tableName() . ' p');
        $command->join(
            'news n',
            'n.campaign_id = :campaign_id'.($activeNewsOnly ? ' AND n.is_active = 1' : ''),
            array(':campaign_id' => $campaign_id)
        );
        $command->join('teasers t', 't.news_id = n.id AND t.is_active = 1');
        $command->leftJoin('ct_except e', 'p.id = e.platform_id AND e.teaser_id = t.id');
        $command->leftJoin('platforms_tags pt', 'pt.platform_id = p.id');
        $command->leftJoin('teasers_tags tt', 'tt.teaser_id = t.id AND tt.tag_id = pt.tag_id');
        $command->where('p.is_active = 1 AND p.is_deleted = 0 '.($withExternal ? '' : 'AND p.is_external = 0 ').'AND p.id <> 23 AND e.platform_id IS NULL');
        $command->andWhere('(tt.teaser_id IS NOT NULL'.($withExternal ? ' OR p.is_external = 1' : '').')');

        return $command->queryColumn();
    }

    /**
     * Возвращает данные платформы
     *
     * Если данные закэшированы, тогда они берутся из redis
     *
     * @param $platform_id
     * @return array
     * @throws CException
     */
    public static function getById($platform_id)
    {
        $platform = Yii::app()->cache->get(sprintf(self::CACHE_KEY, $platform_id));
        if (!$platform) {
            $platform = self::model()->notDeleted()->findByPk($platform_id);
            if ($platform === null) {
                throw new CException('Cant get platform by id: '.$platform_id);
            }
            Yii::app()->cache->set(sprintf(self::CACHE_KEY, $platform_id), $platform->getAttributes());
        }

        return $platform;
    }

    public function getBilling_paid()
    {
        return round(BillingIncome::model()->getPaidByPlatform($this->id),2);
    }

    public function getBilling_debit()
    {
        return round(BillingIncome::model()->getProfitByPlatform($this->id) - BillingIncome::model()->getPaidByPlatform($this->id),2);
    }

    public function getLink()
    {
        return '.'.$this->id.'.'.$this->getEncryptedId();
    }

    public function getEncryptedId()
    {
        return Crypt::encryptUrlComponent($this->id);
    }

    /**
     * @return Platforms Именованная группа для выборки площадок для вывода списком
     */
    public function printable()
    {
        $alias = $this->getTableAlias(false,false);
        $this->notDeleted()->getDbCriteria()->mergeWith(array(
            'condition' => $alias . '.id <> :deleted',
            'params' => array(':deleted' => self::DELETED_PLATFORM_ID),
            'order' => 'server ASC'
        ));

        return $this;
    }

    /**
     * Возвращает названия всех платформ по переданным идентификаторам
     *
     * @param array $ids
     *
     * @return array
     */
    public function getServersByIds(array $ids)
    {
        if (empty($ids)) return array();

        $command = $this->getDbConnection()->createCommand();
        $command->select(array('id', 'server'));
        $command->from($this->tableName());
        $command->where('id IN ('. implode(', ', $ids) . ')');

        $result = array();
        foreach($command->queryAll() as $dbRow){
            $result[$dbRow['id']] = $dbRow['server'];
        }

        return $result;
    }
}