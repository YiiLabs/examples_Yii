<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;

    /**
     * Class Domain
     *
     * Модель для работы с доменами/проектами клиентов
     *
     * @package app\models
     */
    class Domain extends DBComponent
    {
        public static function tableName() {
            return '{{%domains}}';
        }

        public function isActive(){
            return $this->is_active=='Y';
        }

        public function getToken($Salt = false) {
            if ($Salt === false) {
                $Salt = Client::$salt;
            }

            return md5($this->id . $Salt);
        }

        public function getTargets($Type = false) {
            if (empty($Type)) {
                return $this->hasMany(Target::className(), ['domain_id' => 'id']);
            } else {
                return $this->hasMany(Target::className(), ['domain_id' => 'id'])
                    ->where(['type'=>['view'=>'visit_url','click'=>'click'][$Type]])->all();
            }
        }

        public function checkTargets($Event, $ClientId)
        {
            if (!empty($Event))
            {
                $Targets = $this->getTargets($Event->type);

                if (!empty($Targets))
                {
                    foreach($Targets as $Target)
                    {
                        $LastAchieve = Achieve::find([
                            'condition'=>'target_id = :target_id AND client_id = :client_id',
                            'params' => [
                                'target_id' => $Target->id,
                                'client_id' => $ClientId
                            ],
                            'order' => 'creation_time DESC'
                        ])->one();

                        # if target not achieved or achieve is older then time in cfg
                        if (empty($LastAchieve) || ( (new \DateTime(date('Y-m-d H:i:s', time()-$Target->settings['timeout']))) > new \DateTime($LastAchieve->creation_time) ))
                        {
                            if ($Target->type == 'visit_url')
                            {
                                if (!empty($Target->settings['urls'])) {
                                    foreach($Target->settings['urls'] as $Url)
                                    {
                                        if ($Url['type'] == 'identical' && $Url['url'] == $Event->url) {
                                            $Target->achieve($ClientId,$this);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        public function getCreationTime() {
            return '-';
        }

        public function getTime() {
            if (!empty($this->time_json)) {
                #print_r(json_decode($this->time_json));die();
                return json_decode($this->time_json, true);
            }

            return ['type'=>'alldays','data'=>[]];
        }

        public function getCalls() {
            if (!empty($this->calls_json)) {
                #print_r(json_decode($this->time_json));die();
                return json_decode($this->calls_json, true);
            }

            return [];
        }

        public function getPhones() {
            $Phones = [];
            $CallsData = $this->getCalls();
            if (!empty($CallsData)) {
                foreach($CallsData as $CallData)
                {
                    if (!empty($CallData['phone'])) {
                        $Phones[] = preg_replace("/[^0-9]+/is", "", $CallData['phone']);
                    }
                }
            }

            return $Phones;
        }
    }

?>