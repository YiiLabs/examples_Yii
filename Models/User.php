<?php

    namespace app\models;

    use app\components\inheritance\DBComponent;
    use Yii;
    use yii\db\ActiveQuery;

    class User extends DBComponent
    {
        public static function tableName() {
            return '{{%users}}';
        }

        public static function find() {
            return new UserQuery(get_called_class());
        }

        public function isAdmin() { return Yii::$app->controller->isAdmin(); }
        public function isClient() { return Yii::$app->controller->isClient(); }

        public function getCreationTime() {
            return $this->getTimestamp('creation_time', 'time_ago');
        }

        public function getVerificationHash() {
            return md5($this->id . 'asd1^%&*(&&^*');
        }

        public function getDomains() {
            return $this->hasMany(Domain::className(), ['user_id' => 'id']);
        }

        public function sendEmailVerification() {
            if (empty($this->email_sended) && \app\components\helpers\Helper::sendEmail(
                    $this->email,
                    'Подтверждение регистрации',
                    Yii::$app->controller->renderPartial('//index/verification')
                )) {
                $this->email_sended = date('Y-m-d H:i:s');
                $this->save();
            }
        }
    }

    class UserQuery extends ActiveQuery {
        public function search($id = false) {
            $User = Yii::$app->controller->data->User;

            if (!$User->isAdmin()) {
                $this->andWhere(['id' => $User->id]);
            }

            if (!empty($id)) {
                $this->andWhere(['id' => $id]);
            }

            $this->orderBy('id DESC');

            return $this;
        }

        public function byLogin($login) {
            $this->andWhere(['email'=>$login]);

            return $this;
        }

        public function byId($id) {
            $this->andWhere(['id'=>$id]);

            return $this;
        }
    }

?>