<?php

    namespace app\controllers;

    use app\components\inheritance\DefinitionsComponent;
    use Yii;
    use app\models\Domain;
    use app\models\Event;
    use app\models\Client;
    use app\models\ClientSession;
    use app\components\helpers\Filter;

    class IndexController extends DefinitionsComponent
    {
        public function actionIndex() {
            $this->layout = 'main.tpl';
            return $this->render('index');
            //die('construction');
        }

        public function actionError() {
            return $this->render('error');
        }


        /**
         * Акшен для счетчика, сохраняет ping/view/click эвенты
         */
        public function actionPush() {
            $DomainId = Filter::GET('sid', 'int', ['min'=>1,'max'=>9999999]);
            $Type = Filter::GET('type', 'enum', ['allowed'=>['ping','view','click']]);
            $Url = Filter::GET('url', 'varchar', ['min'=>1,'max'=>2048]);
            $Session = ClientSession::getSession();

            if (!empty($Session) && !empty($DomainId) && !empty($Url) && !empty($Type)) {
                $Domain = Domain::findOne(['id'=>$DomainId]);

                if (!empty($Domain))
                {
                    $Event = new Event();
                    $Event->domain_id = $DomainId;
                    $Event->session_id = $Session->id;
                    $Event->type = $Type;
                    $Event->url = $Url;
                    $Event->ts = date('Y-m-d H:i:s');
                    $Event->save();

                    $Domain->checkTargets($Event, $Session->client_id);
                }
            }

            $this->return1x1();
        }
    }


?>