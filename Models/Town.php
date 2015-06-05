<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;

    class Town extends DBComponent
    {
        public static function tableName() {
            return '{{%towns}}';
        }
    }

?>