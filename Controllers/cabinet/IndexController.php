<?php

    namespace app\controllers\cabinet;

    use app\components\inheritance\CabinetComponent;

    class IndexController extends CabinetComponent
    {
        public function actionIndex() {
            if ($this->data->User->email_submitted == 'N') {
                $this->data->User->sendEmailVerification();
            }
            if ($this->data->User->is_new == 'Y' && $this->isClient() && !empty($this->data->User->domains)) {
                $this->data->User->is_new = 'N';
                $this->data->User->save();

                return $this->redirect('/cabinet/domains/view/' . $this->data->User->domains[0]->id.'?tab=2');
            }

            $this->data->active_sidebar_menu = '/';

            return $this->render('index');
        }


        public function actionError() {
            $this->data->hide_sidebar = true;

            return $this->render('error');
        }
    }


?>