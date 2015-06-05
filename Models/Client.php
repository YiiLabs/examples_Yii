<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;


    /**
     * Class Client
     *
     * Модель для работы с клиентами
     *
     * @package app\models
     */
    class Client extends DBComponent
    {
        public static $salt = '&^%#$(*(%(!@#)!@#;;';
        
        public static function tableName() {
            return '{{%clients}}';
        }
    }

?>