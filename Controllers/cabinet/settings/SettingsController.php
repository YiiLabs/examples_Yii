<?php

    namespace app\controllers\cabinet\settings;

    use app\components\inheritance\CabinetComponent;

    class UsersController extends CabinetComponent
    {
        public function actionIndex() {
            return $this->render('index');
        }
    }


?>