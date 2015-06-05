<?php

/**
 * This is the model class for table "users".
 *
 * The followings are the available columns in table 'users':
 * @property string $id
 * @property string $email
 * @property string $login
 * @property string $password
 * @property string $logo
 * @property string $role
 * @property integer $is_deleted
 * @property string $billing_details_type
 * @property string $billing_details_text
 * @property integer $is_auto_withdrawal
 *
 * The followings are the available model relations:
 * @property Campaigns[] $campaigns
 * @property Platforms[] $platforms
 *
 * Behaviors
 * @property DirtyObjectBehavior $dirty
 */
class Users extends CActiveRecord
{
    const ROLE_ADMIN    = 'admin';
    const ROLE_USER     = 'user';
    const ROLE_GUEST    = 'guest';
    const ROLE_PLATFORM = 'platform';

    const DEFAULT_LOGO = 'default.jpg';

    public $repeat_password;
    public $initialPassword;
    public $campaigns;

    private $campaigns_cont;
    private $platforms_count;

    /**
     * Returns the static model of the specified AR class.
     * @param string $className active record class name.
     * @return Users the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'users';
    }

    /**
     * @return array Доступные для выобра поли пользователей
     */
    public static function getAvailableRoles()
    {
        return array(
            self::ROLE_USER     => 'Клиент',
            self::ROLE_ADMIN    => 'Администратор',
            self::ROLE_PLATFORM    => 'Площадка',
        );
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $width = Yii::app()->params->userImageWidth;
        $height = Yii::app()->params->userImageHeight;

        $logoErrorMsg = 'Размер изображния больше допустимого (' . $width . ' x ' . $height . ')';

        return array(
            array('email, login', 'required'),
            array('email', 'length', 'max' => 128),
            array('email', 'email'),
            array('email', 'unique'),
            array('password, repeat_password', 'required', 'on' => 'insert'),
            array('repeat_password, password', 'length', 'min' => 6, 'max' => 40),
            array('repeat_password', 'compare', 'compareAttribute' => 'password'),
            array('login, billing_details_type', 'length', 'max' => 45),
            array(
                'logo',
                'ext.yiiext.validators.EImageValidator',
                'allowEmpty' => true,
                'mime' => array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/png'),
                'maxWidth' => $width,
                'maxHeight' => $height,
                'tooLargeWidth' => $logoErrorMsg,
                'tooLargeHeight' => $logoErrorMsg,
                'on' => array('create', 'edit')
            ),
            array('logo', 'default', 'value' => self::DEFAULT_LOGO),
            array('role', 'in', 'range' => array_keys(self::getAvailableRoles())),
            array('billing_details_text', 'safe'),
            array('is_auto_withdrawal', 'numerical', 'integerOnly' => true),
            array('id, email, login, password, logo, role, campaigns', 'safe', 'on' => 'search'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'campaigns' => array(self::HAS_MANY, 'Campaigns', 'client_id'),
            'platforms' => array(self::HAS_MANY, 'Platforms', 'user_id'),
        );
    }

    public function behaviors()
    {
        return array(
            'dirty' => array(
                'class' => 'application.components.behaviors.DirtyObjectBehavior'
            )
        );
    }

    public function afterFind()
    {
        //reset the password to null because we don't want the hash to be shown.
        $this->initialPassword = $this->password;
        $this->password = null;

        parent::afterFind();
    }

    protected function beforeSave()
    {
        if (empty($this->password) && empty($this->repeat_password) && !empty($this->initialPassword)) {
            $this->password = $this->repeat_password = $this->initialPassword;
        } else {
            $this->password = md5($this->password);
        }

        $this->role = ($this->role) ?: self::ROLE_GUEST;

        return parent::beforeSave();
    }

    protected function afterSave()
    {
        if ($this->is_deleted) {
            // Cоздаем задание на удаление из БД
            Yii::app()->resque->createJob('app', 'UserDelFromDbJob', array('user_id' => $this->id));

        }

        $this->deleteOldLogo();
        parent::afterSave();
    }

    /**
     * При удалении пользователя, удаляем его лого
     */
    protected function afterDelete()
    {
        if ($this->logo != self::DEFAULT_LOGO) {
            $filePath = Yii::app()->params['logoBasePath'] . DIRECTORY_SEPARATOR . $this->logo;
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }

        parent::afterDelete();
    }

    /**
     * Удаляет старое лого
     */
    private function deleteOldLogo()
    {
        if (!$this->getIsNewRecord() &&
            $this->dirty->isAttributeChanged('logo') &&
            $this->dirty->getCleanAttribute('logo') != self::DEFAULT_LOGO) {

            $oldFilePath = Yii::app()->params['logoBasePath'] . DIRECTORY_SEPARATOR . $this->dirty->getCleanAttribute('logo');
            if (is_file($oldFilePath)) {
                unlink($oldFilePath);
            }
        }
    }

    public function validatePassword($password)
    {
        return md5($password) === $this->initialPassword;
    }

    /**
     * @return bool Сохраняет лого
     */
    public function saveLogo()
    {
        if (!($this->logo instanceof CUploadedFile))
        {
            return true;
        }

        $newFileName = 'c_' . $this->id . '_' . $this->logo->name;

        $filePath   = Yii::app()->params['logoBasePath'] . DIRECTORY_SEPARATOR . $newFileName;
        $uploaded   = $this->logo->saveAs($filePath);
        $this->logo = $newFileName;

        if ($uploaded)
        {
            $command    = $this->getDbConnection()->createCommand();
            $updated    = $command->update(
                $this->tableName(),
                array('logo' => $this->logo),
                'id = :id',
                array('id' => $this->id)
            );
        }

        return $uploaded && $updated;
    }

    /**
     * @return Teasers Именованная группа для выборки не удаленных тизеров
     */
    public function notDeleted()
    {
        $alias = $this->getTableAlias(false, false);
        $this->getDbCriteria()->mergeWith(array(
            'condition' => 'is_deleted = 0',
        ));

        return $this;
    }

    /**
     * Удаляет кампании пользователя
     */
    public function deleteCampaigns()
    {
        foreach ($this->getRelated('campaigns') as $campaign) {
            $campaign->is_deleted = 1;
            $campaign->save(false, array('is_deleted'));
        }
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'email' => 'Email',
            'login' => 'Имя',
            'password' => 'Пароль',
            'logo' => 'Логотип',
            'role' => 'Роль',
            'billing_details_type' => 'Реквизиты',
            'is_auto_withdrawal' => 'Автоматический запрос на выплаты',
        );
    }

    public function searchWithActive($user = false, $campaignsCostType = '')
    {
//        var_dump($campaignsCostType); exit();
        $criteria = new CDbCriteria;
        $criteria->select = 't.*';
        $criteria->compare('id', $this->id, true);

        $criteria->with = array('campaigns' => array(
            'together' => true,
            'select' => false,
            'with' => array(
                'news' => array('together' => true, 'select' => false),
                'news.teasers' => array('together' => true, 'select' => false)
            )
        ));
        $criteria->group = 't.id';

        $criteria->addCondition('t.is_deleted = 0');
        $criteria->addCondition('t.role = "user"');
        $criteria->addCondition('campaigns.is_active = 1');
        $criteria->addCondition('campaigns.date_end >= CURDATE()');
        if ($campaignsCostType != '' && array_key_exists($campaignsCostType, Campaigns::model()->getAvailableCostTypes())){
            $criteria->compare('campaigns.cost_type', $campaignsCostType);
        }
        if (!empty($this->login)) {
            $searchCriteria = $this->getSearchCriteria();
            $searchCriteria->compare('campaigns.name', $this->login, true, 'OR');
            $criteria->mergeWith($searchCriteria);
        }

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }


    public function searchAll($campaignFilters = array())
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria;

        $criteria->select = 't.*';
        $criteria->with = array('campaigns' => array(
            'together' => true,
            'select' => false,
            'with' => array(
                'news' => array('together' => true, 'select' => false),
                'news.teasers' => array('together' => true, 'select' => false)
            )
        ));
        $criteria->group = 't.id';

        $criteria->compare('t.id', $this->id, true);
        $criteria->addCondition('t.role = "user"');
        $criteria->addCondition('t.is_deleted = 0');
        if (isset($campaignFilters['cost_type']) &&
            array_key_exists($campaignFilters['cost_type'], Campaigns::model()->getAvailableCostTypes())
        ){
            $criteria->compare('campaigns.cost_type', $campaignFilters['cost_type']);
        }
        if (isset($campaignFilters['is_active']) && $campaignFilters['is_active'] == 1){
            $criteria->addCondition('campaigns.is_active = 1');
            $criteria->addCondition('campaigns.date_end >= CURDATE()');
        }
        if (!empty($this->login)) {
            $criteria->mergeWith($this->getSearchCriteria());
        }

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
        ));
    }

    /**
     * @return int Возвращает количество кампаний пользователя
     */
    public function campaignsCount()
    {
        if (!isset($this->campaigns_cont)) {
            $this->campaigns_cont = (int) Campaigns::model()->count('client_id = :id', array(':id' => $this->id));
        }

        return $this->campaigns_cont;
    }

    /**
     * @return int Возвращает количество площадок пользователя
     */
    public function platformsCount()
    {
        if (!isset($this->platforms_count)) {
            $this->platforms_count = (int) Platforms::model()->count('user_id = :id', array(':id' => $this->id));
        }

        return $this->platforms_count;
    }

    private function getSearchCriteria()
    {
        $searchCriteria = new CDbCriteria();
        $searchCriteria->compare('t.email', $this->login, true, 'OR');
        $searchCriteria->compare('t.login', $this->login, true, 'OR');
        $searchCriteria->compare('campaigns.id', $this->login, false, 'OR');
        $searchCriteria->compare('news.id', $this->login, false, 'OR');
        $searchCriteria->compare('teasers.id', $this->login, false, 'OR');
        return $searchCriteria;
    }
}