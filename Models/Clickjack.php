<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;

    class Clickjack extends DBComponent
    {
        public static function tableName() {
            return '{{%clickjacks}}';
        }
    }

?>