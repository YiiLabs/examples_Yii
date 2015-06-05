<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;

    class Country extends DBComponent
    {
        public static function tableName() {
            return '{{%countries}}';
        }
    }

?>