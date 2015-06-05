<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;

    class Stat extends DBComponent
    {
        public static function tableName() {
            return '{{%stats}}';
        }
    }

?>