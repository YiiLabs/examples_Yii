<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;

    #use app\components\helpers\Helper;

    class Session extends DBComponent
    {
        public static function tableName() {
            return '{{%sessions}}';
        }

        public function getUser() {
            return User::find()->byId($this->user_id)->one();
        }
    }

?>