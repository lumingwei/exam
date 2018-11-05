<?php

// +----------------------------------------------------------------------
// | 后台管理员模型
// +----------------------------------------------------------------------

namespace Home\Model;


class UserModel extends Model {

    //array(验证字段,验证规则,错误提示,[验证条件,附加规则,验证时间])
    protected $_validate = array(
        array('unumber', 'require', '用户名不能为空！'),
        array('password', 'require', '密码不能为空！', 0, 'regex', 1),
    );
    //array(填充字段,填充内容,[填充条件,附加规则])
    protected $_auto = array(
        array('create_time', 'time', 1, 'function'),
        array('update_time', 'time', 3, 'function'),
    );

    /**
     * 获取用户信息
     * @param type $identifier 用户名或者用户ID
     * @return boolean|array
     */
    public function getUserInfo($unumber, $password = NULL) {

        $map = array();
        $map['unumber'] = $unumber;

        $userInfo = $this->where($map)->find();
        if (empty($userInfo)) {
            return false;
        }
        //密码验证
        if (!empty($password) && $this->hashPassword($password) != $userInfo['pwd']) {
            return false;
        }
        return $userInfo;
    }

    /**
     * 更新登录状态信息
     * @param type $userId
     * @return type
     */
    public function loginStatus($userId) {
        $this->find((int) $userId);
        $this->last_login_time = time();
        $this->last_login_ip = get_client_ip();
        return $this->save();
    }

    /**
     * 对明文密码，进行加密，返回加密后的密文密码
     * @param string $password 明文密码
     * @param string $verify 认证码
     * @return string 密文密码
     */
    public function hashPassword($password, $verify = "") {
        //return md5($password . md5($verify));
        return md5($password);
    }


    /**
     * 插入成功后的回调方法
     * @param type $data 数据
     * @param type $options 表达式
     */
    protected function _after_insert($data, $options) {
        //添加信息后，更新密码字段
        $this->where(array('id' => $data['id']))->save(array(
            'password' => $this->hashPassword($data['password'], $data['verify']),
        ));
    }

}
