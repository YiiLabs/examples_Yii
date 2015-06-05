<?php

    namespace app\controllers\cabinet\settings;

    use app\components\helpers\Helper;
    use app\components\inheritance\CabinetComponent;
    use app\components\helpers\Filter;
    use app\models\User;

    use Yii;

    use yii\web\Response;

    class UsersController extends CabinetComponent
    {
        public function actionIndex() {
            if (!$this->isAdmin()) {
                $this->data->hide_sidebar = true;
                return $this->render('//index/error');
            }

            $this->data->active_sidebar_menu = '/settings/users';

            $this->data->users = User::find()->search()->all();

            return $this->render('list');
        }

        public function actionView($id = false) {
            $this->data->active_sidebar_menu = '/settings/users';

            if (empty($id)) {
                $id = $this->data->User->id;
            }

            $this->data->user = User::find()->search($id)->one();

            if (!empty($this->data->user)) {

                if (!empty($this->data->user->phone_submit_sms)) {
                    $SMSSendTime = new \DateTime($this->data->user->phone_submit_sms);
                    if ($SMSSendTime < new \DateTime(date('Y-m-d H:i:s', (time()-(60*15))))) {
                        $this->data->user->phone_submit_block_until = null;
                        $this->data->user->phone_submit_code = null;
                        $this->data->user->phone_submit_sms = null;
                        $this->data->user->phone_submit_tentatives = 0;
                        $this->data->user->save();
                    }
                }

                return $this->render('view');
            } else {
                $this->data->hide_sidebar = true;
                return $this->render('//index/error');
            }
        }

        public function actionCreate() {
            $this->data->active_sidebar_menu = '/settings/users';

            if ($this->isAdmin()) {
                return $this->render('create');
            } else {
                $this->data->hide_sidebar = true;
                return $this->render('//index/error');
            }
        }

        public function actionSave_base() {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $Id = Filter::POST('id', 'int', array('min'=>1,'max'=>8));
            $Name = Filter::POST('name', 'varchar', array('min'=>3,'max'=>64));
            $Email = Filter::POST('email', 'email');
            $Phone = Filter::POST('phone', 'varchar', array('min'=>3,'max'=>32,'mask'=>'/\+7\ \([0-9]{3}\)\ [0-9]{3}\-[0-9]{2}\-[0-9]{2}/is'));
            $SubscribeByEmail = ( !empty($_POST['subscribed_by_email']) && $_POST['subscribed_by_email'] == 'Y' ? 'Y' : 'N' );
            $SubscribeBySMS= ( !empty($_POST['subscribed_by_sms']) && $_POST['subscribed_by_sms'] == 'Y' ? 'Y' : 'N' );

            if (empty($Id) || empty($Name)) {
                return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
            }

            $User = User::find()->search($Id)->one();
            if (empty($User)) {
                return array('status'=>'error','statusText'=>'Пользователь не найден!');
            }

            $User->name = $Name;
            $User->subscribed_by_email = $SubscribeByEmail;
            $User->subscribed_by_sms = $SubscribeBySMS;

            if (!empty($Email)) {
                $User->email = $Email;
            } else {
                $User->email = null;
            }

            if ($this->isAdmin()) {
                $User->phone_submitted = 'Y';
                $User->phone = $Phone;
            } else {
                if ($User->phone_submitted == 'N' || ( !empty($Phone) && $Phone != $User->phone)) {
                    if ($User->phone_submit_block_until != '' && (new \DateTime($User->phone_submit_block_until)) > (new \DateTime())) {
                        return array('status'=>'error','statusText'=>'Изменение телефона заблокировано до ' . (new \DateTime($User->phone_submit_block_until))->format('d.m.Y H:i:s') . '!');
                    }

                    if ($Phone != '')
                    {
                        if (empty($User->phone_submit_code))
                        {
                            $User->phone_submit_code = rand(1000,9999);
                            $User->phone_submit_sms = date('Y-m-d H:i:s');
                            $User->save();

                            Helper::sendSMS($Phone, "Код подтверждения: " . $User->phone_submit_code);

                            return array('status'=>'checkPhone');
                        }
                        else
                        {
                            $SMSCode = Filter::POST('sms_code', 'int', array('min'=>4,'max'=>4));

                            if (empty($SMSCode) || $SMSCode != $User->phone_submit_code) {
                                $User->updateCounters(['phone_submit_tentatives'=>1]);

                                if ($User->phone_submit_tentatives > 3)
                                {
                                    $BlockUntilTime = new \DateTime(date('Y-m-d H:i:s', (time()+(60*15))));

                                    $User->phone_submit_block_until = $BlockUntilTime->format('Y-m-d H:i:s');
                                    $User->phone_submit_code = null;
                                    $User->phone_submit_sms = null;
                                    $User->phone_submit_tentatives = 0;

                                    $User->save();

                                    return array('status'=>'error','statusText'=>'Изменение телефона заблокировано до ' . $BlockUntilTime->format('d.m.Y H:i:s') . ' в связи с превышением попыток подтверждения!');
                                }

                                return array('status'=>'error','statusText'=>'Введенный код неверный!');
                            } else {
                                $User->phone_submit_block_until = null;
                                $User->phone_submit_code = null;
                                $User->phone_submit_sms = null;
                                $User->phone_submit_tentatives = 0;

                                if (!empty($Phone)) {
                                    $User->phone_submitted = 'Y';
                                    $User->phone = $Phone;
                                } else {
                                    $User->phone = null;
                                }
                            }
                        }
                    }
                } else {
                    if ($Phone == '') {
                        $User->subscribed_by_sms = 'N';
                        $User->phone_submitted = 'N';
                        $User->phone = '';
                    }
                }
            }

            if ($User->save()) {
                return array('status'=>'ok','statusText'=>'Данные обновлены');
            }

            return array('status'=>'error','statusText'=>'Неизвестная ошибка');
        }

        public function actionSave_password() {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $Id = Filter::POST('id', 'int', array('min'=>1,'max'=>8));
            $Password = Filter::POST('password', 'varchar', array('min'=>3,'max'=>32));
            $NewPassword = Filter::POST('new_password', 'varchar', array('min'=>3,'max'=>32));
            $NewPasswordConfirm = Filter::POST('new_password_confirm', 'varchar', array('min'=>3,'max'=>32));

            if (empty($Id) || empty($Password) || empty($NewPassword) || empty($NewPasswordConfirm)) {
                return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
            }

            $User = User::find()->search($Id)->one();
            if (empty($User)) {
                return array('status'=>'error','statusText'=>'Пользователь не найден!');
            }

            if ($User->id == $this->data->User->id) {
                if (!Helper::checkPassword($Password, $User->password)) {
                    return array('status'=>'error','statusText'=>'Вы ввели неверный текущий пароль!');
                }
            }

            if ($NewPassword == $Password) {
                return array('status'=>'error','statusText'=>'Новый пароль не может совпадать с текущим!');
            }

            if ($NewPassword != $NewPasswordConfirm) {
                return array('status'=>'error','statusText'=>'Введенные пароли не совпадают!');
            }

            $User->password = Helper::hashPassword($NewPassword);

            if ($User->save()) {
                return array('status'=>'ok','statusText'=>'Пароль успешно изменен');
            }

            return array('status'=>'error','statusText'=>'Неизвестная ошибка');
        }

        public function actionInsert() {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $Login = Filter::POST('login', 'varchar', array('min'=>3,'max'=>64));
            $Password = Filter::POST('password', 'varchar', array('min'=>3,'max'=>64));
            $Name = Filter::POST('name', 'varchar', array('min'=>3,'max'=>64));
            $Email = Filter::POST('email', 'email');
            $Phone = Filter::POST('phone', 'varchar', array('min'=>3,'max'=>32,'mask'=>'/\+7\ \([0-9]{3}\)\ [0-9]{3}\-[0-9]{2}\-[0-9]{2}/is'));
            $SubscribeByEmail = ( !empty($_POST['subscribed_by_email']) && $_POST['subscribed_by_email'] == 'Y' ? 'Y' : 'N' );
            $SubscribeBySMS= ( !empty($_POST['subscribed_by_sms']) && $_POST['subscribed_by_sms'] == 'Y' ? 'Y' : 'N' );

            if (empty($Login) || empty($Password) || empty($Name)) {
                return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
            }

            $User = new User();

            $User->login = $Login;
            $User->password = Helper::hashPassword($Password);
            $User->name = $Name;
            $User->email = ( !empty($Email) ? $Email : null );
            $User->phone = ( !empty($Phone) ? $Phone : null );
            $User->subscribed_by_email = $SubscribeByEmail;
            $User->subscribed_by_sms = $SubscribeBySMS;

            if ($User->save()) {
                return array('status'=>'ok', 'id' =>$User->id, 'statusText'=>'Аккаунт успешно создан');
            }

            return array('status'=>'error','statusText'=>'Неизвестная ошибка');
        }
    }


?>