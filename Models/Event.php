<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;

    /**
     * Class Event
     *
     * Модель для работы с событиями счетчика
     *
     * @package app\models
     */
    class Event extends DBComponent
    {
        public static function tableName() {
            return '{{%events}}';
        }
    }

?>