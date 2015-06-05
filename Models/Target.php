<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;
    use app\models\Achieve;
    use Yii;


    /**
     * Class Target
     *
     * Модель цели
     *
     * @package app\models
     */
    class Target extends DBComponent
    {
        public static function tableName() {
            return '{{%targets}}';
        }

        public function achieve($ClientId,$Domain) {
            $Client = Client::findOne(['id'=>$ClientId]);

            if (!empty($Client))
            {
                $Achieve = new Achieve();
                $Achieve->client_id = $ClientId;
                $Achieve->target_id = $this->id;
                $Achieve->creation_time = date('Y-m-d H:i:s');

                if ($this->action == 'call') {
                    $Achieve->result = file_get_contents('http://u.admeo.ru/call/start', false, stream_context_create(array('http' =>
                        array(
                            'method'  => 'POST',
                            'header'  => 'Content-type: application/x-www-form-urlencoded',
                            'content' => http_build_query(
                                array(
                                    Yii::$app->params['cookieShortName'] => $Client->cookie,
                                    'site_id' => $Domain->id,
                                    'tels' => implode(",", $Domain->getPhones())
                                )
                            )
                        )
                    )));

                    $JSONResult = json_decode($Achieve->result, true);
                    if (!empty($JSONResult)) {
                        $Call = new Call();
                        $Call->external_id = $JSONResult['nCUID'];
                        $Call->domain_id = $Domain->id;
                        $Call->client_id = $ClientId;
                        $Call->save();
                    }
                }

                $Achieve->save();
            }
        }

        public function getSettings() {
            if (!empty($this->json_settings)) {
                #print_r(json_decode($this->time_json));die();
                return json_decode($this->json_settings, true);
            }

            return [];
        }
    }

?>