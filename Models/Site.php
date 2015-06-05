<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;

    class Site extends DBComponent
    {
        public static function tableName() {
            return '{{%sites}}';
        }
    }

?>