<?php

    namespace app\controllers\api;

    use app\components\inheritance\DefinitionsComponent;
    use app\components\helpers\Filter;
    use app\models\Domain;
    use app\models\Client;
    use app\models\Call;

    // API телефонии
    class AsteriskController extends DefinitionsComponent
    {
        public function beforeAction($action)
        {
            //Выключаем валидацию для внешнего POST
            if ($action->id === 'import') {
                $this->enableCsrfValidation = false;
            }
            return parent::beforeAction($action);
        }

        /*
         * Обработка инфы от астериска, одним экшеном все параметры, возможно нужно будет разделить, пока не нужно.
         * Кука здесь не нужна, достаточно ID звонка.
         * Секьюрность обеспечивается связкой ID домена + ID звонка, почти нереально подобрать (8+ символов схожих)
         */
        public function actionImport() {
            $CallId = Filter::POST('cuid', 'int', ['min'=>1,'max'=>9999999]);
            $DomainId = Filter::POST('site_id', 'int', ['min'=>1,'max'=>9999999]);
            $Status = Filter::POST('status', 'varchar', ['min'=>1,'max'=>32]); // TODO: пока char, нужны варианты, будет enum
            $Record = Filter::POST('record', 'varchar', ['min'=>1,'max'=>256]);
            $Duration = Filter::POST('duration', 'int', ['min'=>1,'max'=>3600]); // Длительность звонка

            if (!empty($CallId) && !empty($DomainId)) {
                $Call = Call::findOne(['external_id' => $CallId]);
                $Domain = Domain::findOne(['id' => $DomainId]);
            }

            if (!empty($Call) && !empty($Domain) && $Call->domain_id == $Domain->id) {
                if (!empty($Status)) {
                    $Call->status = $Status;
                }
                if (!empty($Record)) {
                    $Call->record = $Record;
                }
                if (!empty($Duration)) {
                    $Call->record = $Duration;
                }
                $Call->save();
                die('ok');
            } else {
                die('error');
            }
        }
    }


?>