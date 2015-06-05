<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;

    class Call extends DBComponent
    {
        public static function tableName() {
            return '{{%calls}}';
        }
    }

?>