<?php

    namespace app\controllers\cabinet;

    use app\components\helpers\Helper;
    use app\components\inheritance\CabinetComponent;
    use app\components\helpers\Filter;
    use app\models\Domain;
    use app\models\Town;
    use app\models\Country;

    use Yii;

    use yii\web\Response;

    /**
     * Class DomainsController
     *
     * Контроллер для работы с сайтами клиентов
     *
     * @package app\controllers\cabinet
     */
    class DomainsController extends CabinetComponent
    {
        public function actionIndex() {
            $this->data->active_sidebar_menu = '/domains';

            if ($this->isClient()) {
                $this->data->items = Domain::find()->where(['user_id'=>$this->data->User->id])->all();
            } elseif ($this->isAdmin()) {
                $this->data->items = Domain::find()->all();
            }

            return $this->render('list');
        }

        public function actionView($id = false) {
            $this->data->active_sidebar_menu = '/domains';

            if ($this->isClient()) {
                $this->data->item = Domain::findOne(['id'=>$id,'user_id'=>$this->data->User->id]);
            } elseif ($this->isAdmin()) {
                $this->data->item = Domain::findOne(['id'=>$id]);
            }

            if (!empty($this->data->item)) {
                return $this->render('view');
            } else {
                $this->data->hide_sidebar = true;
                return $this->render('//index/error');
            }
        }

        public function actionCreate() {
            $this->data->active_sidebar_menu = '/domains';

            if ($this->isAdmin() || $this->isClient()) {
                return $this->render('create');
            } else {
                $this->data->hide_sidebar = true;
                return $this->render('//index/error');
            }
        }

        public function actionInsert() {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $Name = Filter::POST('name', 'varchar', array('min'=>3,'max'=>512));
            $Domain = Filter::POST('host', 'domain', array('min'=>3,'max'=>64));

            if (empty($Domain)) {
                return array('status'=>'error','statusText'=>'Домен не существует или не доступен.');
            }

            if (empty($Name)) {
                return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
            }

            $DomainCheck = Domain::findOne(['host'=>$Domain]);
            if (!empty($DomainCheck)) {
                return array('status'=>'error','statusText'=>'Данный домен уже зарегистрирован в системе!');
            }

            $Item = new Domain();

            $Item->user_id = $this->data->User->id;
            $Item->caption = $Name;
            $Item->host = $Domain;

            if ($Item->save()) {
                return array('status'=>'ok', 'id' =>$Item->id, 'statusText'=>'Сайт успешно добавлен');
            }

            return array('status'=>'error','statusText'=>'Неизвестная ошибка');
        }

        public function actionSave_main() {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $Id = Filter::POST('id', 'int', array('min'=>1,'max'=>6));
            $Name = Filter::POST('name', 'varchar', array('min'=>3,'max'=>512));
            $Domain = Filter::POST('host', 'domain', array('min'=>3,'max'=>64));

            if (empty($Domain)) {
                return array('status'=>'error','statusText'=>'Домен не существует или не доступен.');
            }

            if (empty($Id) || empty($Name)) {
                return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
            }

            if ($this->isClient()) {
                $Item = Domain::findOne(['id'=>$Id,'user_id'=>$this->data->User->id]);
            } elseif ($this->isAdmin()) {
                $Item = Domain::findOne(['id'=>$Id]);
            }
            if (empty($Item)) {
                return array('status'=>'error','statusText'=>'Сайт не найден!');
            }

            $Item->caption = $Name;
            $Item->host = $Domain;

            if ($Item->save()) {
                return array('status'=>'ok', 'id' =>$Item->id, 'statusText'=>'Информация обновлена');
            }

            return array('status'=>'error','statusText'=>'Неизвестная ошибка');
        }

        public function actionSave_time() {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $Id = Filter::POST('id', 'int', array('min'=>1,'max'=>6));
            $Country = Filter::POST('country', 'int', array('min'=>1,'max'=>6));
            $Town = Filter::POST('town', 'int', array('min'=>1,'max'=>6));

            if (empty($Id) || empty($Country) || empty($Town)) {
                return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
            }

            $Town = Town::find()->where(['id'=>$Town])->one();
            $Country = Country::find()->where(['id'=>$Country])->one();

            if (empty($Town) || empty($Country)) {
                return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
            }

            if ($this->isClient()) {
                $Item = Domain::findOne(['id'=>$Id,'user_id'=>$this->data->User->id]);
            } elseif ($this->isAdmin()) {
                $Item = Domain::findOne(['id'=>$Id]);
            }
            if (empty($Item)) {
                return array('status'=>'error','statusText'=>'Сайт не найден!');
            }

            #$Type = Filter::ARR('weekdays_hours', 'enum', ['allowed'=>['on','off']], 'off', $_POST['time']);
            if (isset($_POST['weekdays_hours'])) {
                $Type = 'weekdays';
            } else {
                $Type = 'alldays';
            }

            if ($Type == 'weekdays') {
                $TimeStart = Filter::POST('time_start', 'varchar', array('mask'=>'/[0-2][0-9]\:[0-5][0-9]/is'));
                $TimeEnd = Filter::POST('time_end', 'varchar', array('mask'=>'/[0-2][0-9]\:[0-5][0-9]/is'));

                if (empty($TimeEnd) || empty($TimeStart)) {
                    return array('status'=>'error','statusText'=>'Введите корректное время работы!');
                } else {
                    $Item->time_json = json_encode(['type'=>'weekdays','start'=>$TimeStart,'end'=>$TimeEnd]);
                }
            } else {
                $Time = ['type'=>'alldays','data'=>[]];

                if (!empty($_POST['time']) && is_array($_POST['time'])) {
                    $Time['data'] = [];
                    foreach($_POST['time'] as $Day=>$Value) {
                        if ($Value['checked'] == 'on') {
                            $TimeStart = Filter::ARR('start', 'varchar', array('mask'=>'/[0-2][0-9]\:[0-5][0-9]/is'), false, $Value);
                            $TimeEnd = Filter::ARR('end', 'varchar', array('mask'=>'/[0-2][0-9]\:[0-5][0-9]/is'), false, $Value);

                            if (empty($TimeStart) || empty($TimeEnd)) {
                                return array('status'=>'error','statusText'=>'Введите корректное время работы!');
                            } else {
                                $Time['data'][$Day] = ['start'=>$TimeStart,'end'=>$TimeEnd];
                            }
                        }
                    }

                    $Item->time_json = json_encode($Time);
                } else {
                    return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
                }
            }

            $Item->country_id = $Country->id;
            $Item->town_id = $Town->id;

            if ($Item->save()) {
                return array('status'=>'ok', 'id' =>$Item->id, 'statusText'=>'Информация обновлена');
            }

            return array('status'=>'error','statusText'=>'Неизвестная ошибка');
        }

        public function actionSave_calls() {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $Id = Filter::POST('id', 'int', array('min'=>1,'max'=>6));

            if (empty($Id)) {
                return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
            }

            if ($this->isClient()) {
                $Item = Domain::findOne(['id'=>$Id,'user_id'=>$this->data->User->id]);
            } elseif ($this->isAdmin()) {
                $Item = Domain::findOne(['id'=>$Id]);
            }

            if (empty($Item)) {
                return array('status'=>'error','statusText'=>'Сайт не найден!');
            }

            if (!empty($_POST['calls']) && is_array($_POST['calls']))
            {
                $Calls = [];
                foreach($_POST['calls'] as $Call)
                {
                    $Phone = Filter::ARR('phone', 'phone', [], false, $Call);
                    $PhoneAdditional = Filter::ARR('phone_add', 'varchar', ['min'=>1,'max'=>10], false, $Call);
                    $Name = Filter::ARR('name', 'varchar', ['min'=>1,'max'=>64], false, $Call);
                    $IsActive = ( !empty($Call['is_active']) && $Call['is_active'] == 'Y' ? 'Y' : 'N' );

                    if (empty($Phone) || empty($Name)) {
                        return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
                    }

                    $Calls[] = array(
                        'country_id' => (int)$Call['country'],
                        'phone' => $Phone,
                        'phone_add' => $PhoneAdditional,
                        'name' => $Name,
                        'is_active' => $IsActive
                    );
                }

                $Item->calls_json = json_encode($Calls);
            } else {
                $Item->calls_json = '';
            }

            if ($Item->save()) {
                return array('status'=>'ok', 'id' =>$Item->id, 'statusText'=>'Информация обновлена');
            }

            return array('status'=>'error','statusText'=>'Неизвестная ошибка');
        }
    }


?>