<?php

class UsersController extends Controller
{
    /**
     * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
     * using two-column layout. See 'protected/views/layouts/column2.php'.
     */
    public $menu1 = '';

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
                'actions' => array('index', 'view', 'create'),
                'roles' => array('admin'),
            ),
            array('allow',
                'actions' => array('update', 'returnForm'),
                'roles' => array('admin', 'platform'),
            ),
            array('allow',
                'actions' => array('delete'),
                'roles' => array('admin'),
            ),
            array('deny',
                'users' => array('*'),
            ),
        );
    }

    /**
     * Lists all models.
     */
    public function actionIndex()
    {
        $model = new Users('search');
        $model->unsetAttributes(); // clear any default values
        if (isset($_GET['Users']))
            $model->attributes = $_GET['Users'];

        $this->render('index', array(
            'model' => $model,
        ));
    }

    /**
     * Displays a particular model.
     * @param integer $id the ID of the model to be displayed
     */
    public function actionView($id)
    {
        $modelC = new Campaigns('search');
        $modelC->unsetAttributes(); // clear any default values

        if (isset($_GET[get_class($modelC)])) {
            $modelC->attributes = $_GET[get_class($modelC)];
        }

        $this->userData = Users::model()->findByPk($id);

        $this->render('view', array(
            'model' => $this->loadModel($id),
            'modelC' => $modelC,
        ));
    }

    /**
     * Creates a new model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     */
    public function actionCreate()
    {
        $this->save(new Users('create'));
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

        $this->redirect(array('users/index'));
    }

    public function actionReturnForm()
    {
        if (isset($_POST['update_id'])) {
            $model = $this->loadModel($_POST['update_id']);
        }  else {
            $model = new Users('create');
        }

        $width = Yii::app()->params->userImageWidth;
        $height = Yii::app()->params->userImageHeight;

        $this->disableClientScripts();
        $this->renderPartial('_form', array('model' => $model, 'width' => $width, 'height' => $height), false, true);
    }

    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     * @param integer $id the ID of the model to be loaded
     * @return Users the loaded model
     * @throws CHttpException
     */
    public function loadModel($id)
    {
        if(Yii::app()->user->role == 'platform'){
            $model = Users::model()->notDeleted()->findByPk(Yii::app()->user->id);
        }else{
            $model = Users::model()->notDeleted()->findByPk($id);
        }
        if ($model === null) {
            throw new CHttpException(404, 'The requested page does not exist.');
        }

        return $model;
    }

    /**
     * Создает/обновляет пользователя
     *
     * @param Users $model
     */
    private function save(Users $model)
    {
        if (isset($_POST[get_class($model)])) {
            if(Yii::app()->user->role !== Users::ROLE_ADMIN){
                unset($_POST[get_class($model)]['role']);
            }
            $model->attributes  = $_POST[get_class($model)];

            if (($file = CUploadedFile::getInstance($model, 'logo')))
            {
                $model->logo = $file;
            }

            $transaction = $model->getDbConnection()->beginTransaction();

            try {
                if ($model->save() && $model->saveLogo()) {
                    $transaction->commit();
                    echo json_encode(array('success' => true));
                    Yii::app()->end();
                } else {
                    $transaction->rollback();
                }
            } catch (Exception $e) {
                $transaction->rollback();
                Yii::log($e->getMessage(), CLogger::LEVEL_ERROR);
            }
        }

        echo json_encode(array('success' => false, 'html' => CHtml::errorSummary($model)));
        Yii::app()->end();
    }
}
