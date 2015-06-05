<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;
    use Yii;

    /**
     * Class ClientSession
     *
     * Модель работы с сессионной кукой для счетчика
     *
     * @package app\models
     */
    class ClientSession extends DBComponent
    {
        public static function tableName() {
            return '{{%clients_sessions}}';
        }


        /**
         * Метод для работы с сессией, по сути должен быть в паттерне синглтона.
         *
         * @param bool $Session
         * @return ClientSession|bool|null|static
         */
        public static function getSession($Session = false) {
            if (!empty($_COOKIE[Yii::$app->params['cookieShortName']])) {
                $Client = Client::findOne(['cookie' => $_COOKIE[Yii::$app->params['cookieShortName']]]);
            }

            if (!empty($Client))
            {
                if (!empty($_COOKIE[Yii::$app->params['cookieSessionName']])) {
                    $Session = self::findOne(['cookie' => $_COOKIE[Yii::$app->params['cookieSessionName']]]);
                }

                if (empty($Session)) {
                    $Session = new self();
                    $Session->client_id = $Client->id;
                    $Session->cookie = md5(microtime().rand(0,9999));
                    $Session->save();
                }
            }

            if (!empty($Session)) {
                setcookie(Yii::$app->params['cookieSessionName'], $Session->cookie, 0, null, '.admeo.ru');

                return $Session;
            }

            return false;
        }

    }

?>