<?php

class PlatformsController extends Controller
{
    /**
     * @return array action filters
     */
    public function filters()
    {
        return array(
            'accessControl', // perform access control for CRUD operations
            //'postOnly + delete', // we only allow deletion via POST request
        );
    }

    /**
     * Specifies the access control rules.
     * This method is used by the 'accessControl' filter.
     * @return array access control rules
     */
    public function accessRules()
    {
        return array(
            array('allow',
                'actions' => array('statsPlatform'),
                'roles' => array('platform'),
            ),
            array('allow',
                'actions' => array('create', 'delete'),
                'roles' => array('admin'),
            ),
            array('allow',
                'actions' => array('admin', 'returnForm', 'update', 'report', 'news', 'changeActivity', 'traffic'),
                'roles' => array('admin', 'platform'),
            ),
            array('deny',
                'users' => array('*'),
            ),
        );
    }

    /**
     * Manages all models.
     */
    public function actionAdmin()
    {
        $model = new Platforms('search');
        $model->unsetAttributes(); // clear any default values

        if (isset($_GET['Platforms'])) {
            $model->attributes = $_GET['Platforms'];
        }

        if(Yii::app()->user->role !== Users::ROLE_ADMIN){
            $model->user_id = Yii::app()->user->id;
        }

        $this->render('admin', array(
            'model' => $model,
        ));
    }

    /**
     * Creates a new model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     */
    public function actionCreate()
    {
        $this->save(new Platforms('create'));
    }

    /**
     * Updates a particular model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id the ID of the model to be updated
     */
    public function actionUpdate($id)
    {
        $model = $this->loadModel($id);
        $model->setScenario('edit');
        $this->save($model);
    }

    /**
     * Deletes a particular model.
     * If deletion is successful, the browser will be redirected to the 'admin' page.
     * @param integer $id the ID of the model to be deleted
     */
    public function actionDelete($id)
    {
        $model = $this->loadModel($id);
        $model->is_deleted = 1;
        $model->save(false, array('is_deleted'));

        $this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
    }

    /**
     * Страница трафика по площадке
     */
    public function actionTraffic($id)
    {
        $platform   = $this->loadModel($id);
        list($period, $dateFrom, $dateTo) = Report::getPeriod();

        $report = ReportDailyByPlatform::model()->getByPeriod($id, $dateFrom, $dateTo);

        $this->setUserData($platform);

        $this->render('view',array(
            'view'              => 'partials/_traffic',
            'platform'          => $platform,
            'period'            => $period,
            'report'            => $this->getTrafficDataProvider($report),
            'total_clicks'      => $report['total']['clicks'],
            'total_price'       => $report['total']['price'],
            'dateFrom'          => $dateFrom,
            'dateTo'            => $dateTo,
        ));
    }

    /**
     * Отчеты по площадкам
     */
    public function actionReport()
    {
        if (isset($_GET['id'])) {
            $platform   = $this->loadModel($_GET['id']);
            list($period, $dateFrom, $dateTo) = Report::getPeriod();

            $report = new ExcelReportPlatformByPeriod($platform, $dateFrom, $dateTo);
            $report->build()->render();
            Yii::app()->end();
        }

        if (!isset($_POST['report'])) {
            throw new CHttpException(404, 'Page not found');
        }

        if(Yii::app()->user->role !== Users::ROLE_ADMIN){
            switch($_POST['report']){
                case 'full':
                    $report = new ExcelReportPlatformsFullForPlatform(Yii::app()->user->id,$_POST['full_date_from'], $_POST['full_date_to']);
                    break;
                case 'partner':
                    $report = $this->reportPartner();
                    break;
                default:
                    throw new CHttpException(404, 'Page not found');
            }
        }else{
            switch ($_POST['report']) {

                case 'full':
                    if(isset($_POST['user_id'])){
                        $report = new ExcelReportPlatformsFullForPlatform((int) $_POST['user_id'],$_POST['full_date_from'], $_POST['full_date_to']);
                    }else{
                        $report = new ExcelReportPlatformsFull($_POST['full_date_from'], $_POST['full_date_to']);
                    }
                    break;

                case 'external':
                    $report = new ExcelReportPlatformsExternal($_POST['external_date_from'], $_POST['external_date_to'], $_POST['isExternal']);
                    break;

                case 'partner':
                    $report = $this->reportPartner();
                    break;

                default:
                    throw new CHttpException(404, 'Page not found');
            }
        }
        $report->build()->render();
        Yii::app()->end();
    }

    /**
     * Страница списка новостей по площадке
     */
    public function actionNews($id)
    {
        $platform   = $this->loadModel($id);
        list($period, $dateFrom, $dateTo) = Report::getPeriod();

        $report = ReportDailyByTeaserAndPlatform::model()->getForPlatform($id, $dateFrom, $dateTo);
        $total  = ReportDailyByPlatform::model()->totalByPeriod($id, $dateFrom, $dateTo);

        if(Yii::app()->user->role !== Users::ROLE_PLATFORM){
            HashSort::sortBy(isset($_GET['sort']) ? $_GET['sort'] : 'name', $report, 'teasers');
        }

        $this->setUserData($platform);

        $this->render('view',array(
            'view'              => 'partials/_news',
            'platform'          => $platform,
            'period'            => $period,
            'report'            => $this->getNewsDataProvider($report),
            'total_clicks'      => $total['clicks'],
            'total_price'       => $total['price'],
            'dateFrom'          => $dateFrom,
            'dateTo'            => $dateTo,
        ));
    }

    /**
     * Устанавливает пользовательские данные
     * @param Platforms $platform
     */
    private function setUserData(Platforms $platform)
    {
        if($platform->user){
            $this->userData = $platform->user;
        }
    }

    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param integer $id the ID of the model to be loaded
     * @return Platforms the loaded model
     * @throws CHttpException
     */
    private function loadModel($id)
    {
        $model = Platforms::model()->notDeleted()->with('user', 'tags')->findByPk($id);
        if ($model === null)
            throw new CHttpException(404, 'The requested page does not exist.');
        if(Yii::app()->user->role !== Users::ROLE_ADMIN && $model->user_id !== Yii::app()->user->id)
            throw new CHttpException(403,'Forbidden');
        return $model;
    }

    /**
     * Меняет активность кампании/тизера
     * @throws CHttpException
     */
    public function actionChangeActivity($id)
    {
        $success = false;
        if (isset($_POST['type']) && isset($_POST['val']) &&
            (Yii::app()->user->role === Users::ROLE_ADMIN || $_POST['val'] != '1')
        ) {
            $platform = $this->loadModel($id);
            if($_POST['type'] == 'campaign'){
                Yii::beginProfile('load');
                $campaign = Campaigns::model()->notDeleted()->with(array(
                    'news:notDeleted.teasers:notDeleted' => array(
                        'with' => array('platforms', 'tags')
                    )
                ))->findByPk($_POST['update_id']);
                Yii::endProfile('load');
                $success = true;
                Yii::beginProfile('foreach');
                foreach($campaign->news as $news){
                    foreach($news->teasers as $teaser){
                        $success = $this->changePlatformException($platform->id, $teaser, $_POST['val'] != '1') && $success;
                    }
                }
                Yii::endProfile('foreach');
            }else{
                $teaser = Teasers::model()->with('platforms', 'tags')->findByPk($_POST['update_id']);
                $success = $this->changePlatformException($platform->id, $teaser, $_POST['val'] != '1');
            }
        }

        echo json_encode(array('success' => $success));
        Yii::app()->end();
    }

    private function changePlatformException($platform_id, Teasers $teaser, $is_excepted)
    {
        Yii::beginProfile('first');
        if($is_excepted){
            $teaser->platformIds[] = $platform_id;
        }else{
            $teaser->platformIds = array_diff($teaser->platformIds, array($platform_id));
        }
        $success = $teaser->save();
        if($success && $is_excepted){
            Notifications::model()->add($platform_id, $teaser->id);
        }
        Yii::endProfile('first');
        return $success;
    }

    public function actionReturnForm()
    {
        if (isset($_POST['update_id'])) {
            $model      = $this->loadModel($_POST['update_id']);
            $lastCpc    = PlatformsCpc::model()->findLast($model->id);
        } else {
            $model = new Platforms('create');
            if (isset($_POST['user_id'])) {
                $model->user = Users::model()->findByPk($_POST['user_id']);
            }
        }

        if (!isset($lastCpc) || !$lastCpc) {
            $lastCpc = new PlatformsCpc();
            $lastCpc->date = date('d.m.Y');
        }

        $this->disableClientScripts();
        $this->renderPartial('partials/_form', array(
            'model' => $model,
            'cpc'   => $lastCpc,
            'user'  => ($model->user) ?: new Users()
        ), false, true);
    }

    public function actionStatsPlatform()
    {
        $days_left = false;
        if(date('d') > 10){
            $curMonth = date('n');
            $curYear  = date('Y');
            if ($curMonth == 12)
                $firstDayNextMonth = mktime(0, 0, 0, 0, 0, $curYear+1);
            else
                $firstDayNextMonth = mktime(0, 0, 0, $curMonth+1, 1);
            $days_left = ceil(($firstDayNextMonth - time()) / (24 * 3600));
        }
        return $this->renderPartial('stats_platform',array(
            'active_platforms' => Platforms::model()->countByAttributes(array('user_id' => Yii::app()->user->id, 'is_active' => 1)),
            'user' => $this->userData,
            'totalProfit' => ReportDailyByPlatform::model()->getPriceSumByAttributes(array('user_id' => Yii::app()->user->id)),
            'todayProfit' => ReportDailyByPlatform::model()->getPriceSumByAttributes(array(
                'user_id' => Yii::app()->user->id,
                'date' => date('Y-m-d'),
            )),
            'debit' => BillingIncome::model()->getDebitByUser(Yii::app()->user->id),
            'currency' => PlatformsCpc::getCurrency(Platforms::model()->findByAttributes(array('user_id' => Yii::app()->user->id))->currency),
            'days_left' => $days_left,
        ));
    }

    /**
     * Создает/обновляет площадку
     *
     * @param Platforms $model
     *
     * @throws Exception
     */
    private function save(Platforms $model)
    {
        $success = false;
        if (Yii::app()->user->role !== Users::ROLE_ADMIN){
            $model->user->attributes = array(
                'is_auto_withdrawal' => $_POST[get_class($model->user)]['is_auto_withdrawal'],
                'billing_details_type' => $_POST[get_class($model->user)]['billing_details_type'],
                'billing_details_text' => $_POST[get_class($model->user)]['billing_details_text'],
            );
            $success = $model->user->save();
        }else if (isset($_POST[get_class($model)]) && isset($_POST['PlatformsCpc'])) {

            if (!$model->getIsNewRecord()) {
                $cpc = PlatformsCpc::model()->findByAttributes(array(
                    'platform_id'   => $model->id,
                    'date'          => date('Y-m-d', strtotime($_POST['PlatformsCpc']['date'])),
                ));
            }

            if (!isset($cpc) || !$cpc) {
                $cpc = new PlatformsCpc();
            }

            $cpc->attributes    = $_POST[get_class($cpc)];
            $model->attributes  = $_POST[get_class($model)];
            if(!is_array($model->tagIds)){
                $model->tagIds = array();
            }

            $transaction = $model->getDbConnection()->beginTransaction();

            try {
                $model->user = $this->getUserOrCreate();
                if ((!$model->user || $model->user->save()) && $model->save()) {
                    $cpc->platform_id = $model->id;
                    if ($success = $cpc->save()) {
                        $transaction->commit();
                    }
                }

                if(!$success) $transaction->rollback();

            } catch (Exception $e) {
                $transaction->rollback();
                Yii::log($e->getMessage(), CLogger::LEVEL_ERROR);
                throw new Exception($e->getMessage());
            }
        }
        $models = array($model);
        if($model->user !== null) $models[] = $model->user;
        if(isset($cpc)) $models[] = $cpc;

        echo json_encode(array('success' => $success, 'id' => (int) $model->id, 'html' => CHtml::errorSummary($models)));
        Yii::app()->end();
    }

    /**
     * @return Users
     */
    private function getUserOrCreate()
    {
        if (isset($_POST['Users']) && !empty($_POST['Users']['email'])) {
            $user = Users::model()->findByAttributes(array('email' => $_POST['Users']['email'], 'role' => Users::ROLE_PLATFORM));
            if (!$user) {
                $user = new Users();
                $user->role = Users::ROLE_PLATFORM;
            }
            $user->attributes = $_POST['Users'];
            if(empty($user->billing_details_type)){
                $user->billing_details_type = $user->billing_details_text = null;
            }
            if($user->getIsNewRecord() && empty($user->password)){
                $user->password = Yii::app()->params->PlatformDefaultUserPassword;
            }
            if(!empty($user->password)){
                $user->repeat_password = $user->password;
            }
            return $user;
        }

        return null;
    }

    /**
     * @param  array $report
     * @return CArrayDataProvider
     */
    private function getTrafficDataProvider(array $report)
    {
        $total = $report['total'];
        $total['date'] = 'весь период';
        $report['rows'][] = $total;
        $statsProvider = new CArrayDataProvider(
            $report['rows'],
            array(
                'keyField' => 'date',
                'sort' => array(
                    'attributes' => array(
                        'date', 'shows', 'clicks', 'ctr', 'price', 'cost', 'clickfraud'
                    ),
                    'defaultOrder' => array(
                        'date' => CSort::SORT_ASC,
                    )
                ),
                'pagination' => false
            )
        );
        return $statsProvider;
    }

    /**
     * @param  array $report
     * @return CArrayDataProvider
     */
    private function getNewsDataProvider(array $report)
    {
        $provider = Yii::app()->user->role == Users::ROLE_PLATFORM ? 'CArrayDataProvider' : 'CNotSortedArrayDataProvider';
        return new $provider(
            $this->convertReportDataForProvider($report),
            array(
                'keyField' => false,
                'sort' => array(
                    'attributes' => array('name', 'is_active', 'shows', 'clicks', 'ctr'),
                    'defaultOrder' => array(
                        'ctr' => CSort::SORT_DESC,
                    )
                ),
                'pagination' => false
            )
        );
    }

    /**
     * Преобразует данные отчета для дата провайдера
     *
     * @param $report
     *
     * @return array
     */
    private function convertReportDataForProvider($report)
    {
        $result = array();

        foreach ($report as $campaign) {

            $teasers = $campaign['teasers'];
            unset($campaign['teasers']);

            if(Yii::app()->user->role == Users::ROLE_ADMIN){
                $result[] = array_merge($campaign, array(
                    'campaign_name' => $campaign['name'],
                    'campaign_id'   => $campaign['id'],
                    'class'         => 'campaign',
                    'teasers_count' => count($teasers),
                ));
            }

            array_walk($teasers, function(&$item) use ($campaign) {
                $item['class'] = 'teaser';
                $item['campaign_name']  = $campaign['name'];
            });
            $result = array_merge($result,$teasers);
        }
        return $result;
    }

    /**
     * @return ExcelReportPlatformByPeriod
     */
    private function reportPartner()
    {
        $model = $this->loadModel($_POST['platform_id']);
        return new ExcelReportPlatformByPeriod(
            $model,
            $_POST['partner_date_from'],
            $_POST['partner_date_to']
        );
    }
}
