<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;
    use app\components\helpers\Helper;

    class Registration extends DBComponent
    {
        private $smsValidTime = 5; // mins

        public static function tableName() {
            return '{{%registrations}}';
        }

        public function checkActuality($CookieName) {
            if ($this->stage == 'completed') {
                setcookie($CookieName, null, -1, '/');

                return false;
            }

            return true;
        }

        public static function findByCookie($Cookie, $Salt) {
            return parent::findBySql("
                SELECT * FROM {{%registrations}} WHERE MD5(CONCAT_WS('', id, '" . $Salt . "')) = '" . $Cookie . "'
            ")->one();
        }
        
        public function sendSMS() {
            if (!empty($this->phone))
            {
                $SMSSend = true;
                if (!empty($this->sms_sended)) {
                    $SMSSendTime = new \DateTime($this->sms_sended);
                    if ($SMSSendTime < new \DateTime(date('Y-m-d H:i:s', (time() - (60 * $this->smsValidTime))))) {
                        $this->sms_sended = null;
                        $this->sms_count = null;
                        $this->sms_tentatives = null;
                        $this->sms_checked = 0;
                        $this->save();
                    } else {
                        $SMSSend = false;
                    }
                }

                if ($SMSSend === true) {
                    $this->sms_code = rand(1000, 9999);
                    $this->sms_sended = date('Y-m-d H:i:s');
                    $this->save();

                    Helper::sendSMS($this->phone, "Код подтверждения: " . $this->sms_code);

                    return true;
                }
            }

            return false;
        }
    }

?>