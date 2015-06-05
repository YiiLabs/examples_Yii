<?php

    namespace app\controllers\cabinet;

    use app\components\helpers\Helper;
    use app\components\inheritance\DefinitionsComponent;
    use app\components\helpers\Filter;
    use app\components\helpers\AJAX;
    use app\components\helpers\DUrl;

    use app\models\User;
    use app\models\Domain;
    use app\models\Registration;
    use yii\db\Expression;
    use Yii;
    use yii\web\Response;

    /**
     * Class AuthorizationController
     *
     * Контроллер авторизации и регистрации клиентов
     *
     * @package app\controllers\cabinet
     */
    class AuthorizationController extends DefinitionsComponent
    {
        private $salt = 'T&*()';
        private $regCookie = 'regid';

        public function actionIndex() {
            $this->layout = 'authorization.tpl';

            return $this->render('sign-in');
        }

        public function actionSign_up()
        {
            if ($this->isAuthorized) {
                $this->redirect('/cabinet', 301);
            }

            $this->layout = 'registration.tpl';

            if (!empty($_COOKIE[$this->regCookie])) {
                $Registration = Registration::findBySql("
                    SELECT * FROM {{%registrations}} WHERE MD5(CONCAT_WS('', id, '" . $this->salt . "')) = '" . $_COOKIE[$this->regCookie] . "'
                ")->one();

                if (!empty($Registration) && !$Registration->checkActuality($this->regCookie)) {
                    $Registration = null;
                }
            }

            if (!empty($Registration)) {
                $this->data->registration = $Registration;
            } else {
                $Registration = new Registration();
                $Registration->creation_time = date('Y-m-d H:i:s');
                $Registration->stage = 1;
                $Registration->save();

                $this->data->registration = $Registration;

                setcookie($this->regCookie, md5($Registration->id.$this->salt), ((int)time() + (86400*30)));
            }

            if ($Registration->stage == '2') {
                $Registration->sendSMS();
            }

            return $this->render('sign-up');
        }

        public function actionRegistration1()
        {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $Params['domain'] = Filter::POST('domain', 'domain');
            $Params['name'] = Filter::POST('name', 'varchar', ['mask' => '/[a-zA-ZА-Яа-я\-]/is']);
            $Params['email'] = Filter::POST('email', 'email');
            $Params['phone'] = Filter::POST('phone', 'phone');
            $Params['password'] = Filter::POST('password', 'varchar', ['min'=>5,'max'=>32]);
            if (!empty($_COOKIE[$this->regCookie])) {
                $Registration = Registration::findBySql("
                    SELECT * FROM {{%registrations}} WHERE MD5(CONCAT_WS('', id, '" . $this->salt . "')) = '" . $_COOKIE[$this->regCookie] . "'
                ")->one();
            }

            if (!empty($Registration) && !isset(Helper::regenerateByValue($Params)[''])) {
                $DomainCheck = User::findOne(['email'=>$Params['email']]);
                if (!empty($DomainCheck)) {
                    return array('status'=>'error','statusText'=>'Данный email уже зарегистрирован в системе!');
                }
                $DomainCheck = $DomainCheck || Domain::findOne(['host'=>$Params['domain']]);
                if (!empty($DomainCheck)) {
                    return array('status'=>'error','statusText'=>'Данный домен уже зарегистрирован в системе!');
                }

                $Params['password'] = Helper::hashPassword($Params['password']);
                $Registration->setAttributes($Params, false);
                $Registration->stage = '2';
                $Registration->save();

                $Registration->sendSMS();

                return array('status' => 'ok');
            }

            return array('status'=>'error','statusText'=>'Заполните все обязательные поля!');
        }

        public function actionRegistration2()
        {
            Yii::$app->response->format = Response::FORMAT_JSON;

            if (!empty($_COOKIE[$this->regCookie])) {
                $Registration = Registration::findByCookie($_COOKIE[$this->regCookie], $this->salt);
            }

            if (!empty($Registration)) {
                if ($Registration->sms_code == $_POST['code'])
                {
                    $Registration->sms_checked = 'Y';
                    $Registration->save();

                    $Account = new User();
                    $Account->group_id = 2;
                    $Account->login = $Registration->domain;
                    $Account->password = $Registration->password;
                    $Account->name = $Registration->name;
                    $Account->email = $Registration->email;
                    $Account->phone = $Registration->phone;
                    $Account->phone_submitted = 'Y';
                    $Account->subscribed_by_email = 'Y';
                    $Account->creation_time = date('Y-m-d H:i:s');
                    $Account->save();

                    $Domain = new Domain();
                    $Domain->user_id = $Account->id;
                    $Domain->host = $Registration->domain;
                    $Domain->caption = $Registration->domain;
                    $Domain->save();

                    $Registration->stage = 'completed';
                    $Registration->save();

                    $Registration->checkActuality($this->regCookie);

                    return array('status' => 'ok', 'domain_id' => $Domain->id, 'login' => $Account->login);
                } else {
                    $Registration->sms_tentatives += 1;
                    $Registration->save();

                    if ($Registration->sms_tentatives > 3) {
                        $Registration->sms_tentattives = 0;
                        $Registration->sms_count += 1;
                        $Registration->save();

                        return array('status'=>'error','statusText'=>'На ваш номер телефона был выслан новый код подтверждения.');
                    }

                    return array('status'=>'error','statusText'=>'Вы ввели неверный код подтверждения.');
                }
            } else {
                return array('status'=>'error','statusText'=>'Регистрация не найдена.');
            }
        }

        public function actionSign_in() {
            if (!$this->isAuthorized) {
                print_r($_POST);
                $Login = Filter::POST('login', 'varchar', array('min'=>3,'max'=>32,'mask'=>'/[а-яА-Яa-zA-Z0-9\_\-\.]+/isu'));
                $Password = Filter::POST('password', 'varchar', array('min'=>3,'max'=>32));

                # Проверка аккаунта
                $AccountCheck = User::find()->byLogin($Login)->one();

                if (empty($AccountCheck)) {
                    Durl::redirect(PATH . $this->data->url_authorization . '?error=1');
                }

                if (Helper::checkPassword($Password, $AccountCheck->password)) {
                    $this->login($AccountCheck);

                    $this->redirect(PATH);
                } else {
                    $this->redirect(PATH . $this->data->url_authorization . '?error=2');
                }
            }
        }

        public function actionSign_out() {
            if ($this->isAuthorized)
            {
                $this->logout();

                $this->redirect(PATH);
            }
        }
    }


?>