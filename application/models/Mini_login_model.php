<?php
/**
 * Created by PhpStorm.
 * User: yangyang
 * Date: 16/6/3
 * Time: 下午3:22
 */
class Mini_login_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function logout($token = ''){
        $code = $this->input->post('code');
   
        $re_openid = $this->get_mini_openid4log($code);
        //先解绑openid
        if($re_openid['status'] == 1){
             $openid = $re_openid['result']['openid'];
            //清除所有该openid关联的账号绑定记录，包括token
             $this->db->where(array('mini_openid' => $openid))->update('admin', array('mini_openid' => '', 'token' => ''));
             $this->db->where(array('mini_openid' => $openid))->update('users', array('mini_openid' => '', 'token' => ''));
            $this->db->where(array('mini_openid' => $openid))->update('brand', array('mini_openid' => '', 'token' => ''));

        }
        //再处理下token 
        if($token != ""){
             $this->db->where(array('token' => $token))->update('admin', array('mini_openid' => '', 'token' => ''));
             $this->db->where(array('token' => $token))->update('users', array('mini_openid' => '', 'token' => ''));
            $this->db->where(array('token' => $token))->update('brand', array('mini_openid' => '', 'token' => ''));
		}
        return $this->fun_success('操作成功');
    }

    //管理员登录
    public function admin_login(){

        $data = array(
            'user' => trim($this->input->post('user')),
            'password' => password(trim($this->input->post('password'))),
        );
        $row = $this->db->select()->from('admin')->where($data)->get()->row_array();
        if ($row) {
            if($row['status'] != 1)
                return $this->fun_fail('账号禁用');
            $code = $this->input->post('code');
            $re_openid = $this->get_mini_openid4log($code);
            if($re_openid['status'] == 1){
                $openid = $re_openid['result']['openid'];
                $this->delOpenidById($row['admin_id'], $openid, 'admin');
                $this->db->where(array('admin_id' => $row['admin_id']))->update('admin', array('mini_openid' => $openid));
            }
            return $this->fun_success('操作成功',$row);
        } else {
            return $this->fun_fail('账号未注册或密码错误');
        }
    }

    //大客户登录
    public function brand_login(){

        $data = array(
            'username' => trim($this->input->post('user')),
            'password' => sha1(trim($this->input->post('password'))),
        );
        $row = $this->db->select()->from('brand')->where($data)->get()->row_array();
        if ($row) {
            if($row['status'] != 1)
                return $this->fun_fail('账号禁用');
            $code = $this->input->post('code');
            $re_openid = $this->get_mini_openid4log($code);
            if($re_openid['status'] == 1){
                $openid = $re_openid['result']['openid'];
                $this->delOpenidById($row['id'], $openid, 'brand');
                $this->db->where(array('id' => $row['id']))->update('brand', array('mini_openid' => $openid));
            }
            return $this->fun_success('操作成功',$row);
        } else {
            return $this->fun_fail('账号未注册或密码错误!');
        }
    }

    //门店注册
    public function user_logon(){
        $user_data = array(
            'mobile' => trim($this->input->post('mobile')),
            'rel_name' => trim($this->input->post('rel_name')),
            'reg_time' => time(),
            'brand_id' => trim($this->input->post('brand_id')) ? trim($this->input->post('brand_id')) : -1,
            'store_id' => trim($this->input->post('store_id')) ? trim($this->input->post('store_id')) : -1,
            'invite' => trim($this->input->post('invite')) ? trim($this->input->post('invite')) : null,
            'shop_name' => trim($this->input->post('shop_name')),
            'other_brand' => trim($this->input->post('other_brand')) ? trim($this->input->post('other_brand')) : '',
        );
        $password_ = trim($this->input->post('password'));
        $sms_code = $this->input->post('sms_code');
        if(!$user_data['rel_name']){
            return $this->fun_fail('姓名不能为空!');
        }
        if(!$user_data['mobile']){
            return $this->fun_fail('手机号不能为空!');
        }
        if(!check_mobile($user_data['mobile'])){
            return $this->fun_fail('手机号不规范!');
        }
        if(!$sms_code){
            return $this->fun_fail('短信验证码不能为空!');
        }
        if(!$password_){
            return $this->fun_fail('密码不能为空!');
        }
        if(strlen($password_) < 6)
            return $this->fun_fail('新密码长度不能小于6位!');
        if(!ctype_alnum($password_))
            return $this->fun_fail('新密码只能为字母和数字!');
        if($user_data['invite']){
            $check_invite_ = $this->db->select()->from('admin')->where(array('admin_id' => $user_data['invite'], 'status' => 1))->get()->row_array();
            if(!$check_invite_)
                return $this->fun_fail('此邀请码不可使用!');
        }
        if($user_data['brand_id'] == -1){
            if(!$user_data['other_brand'])
                return $this->fun_fail('品牌不能为空!');
            if(!$user_data['shop_name'])
                return $this->fun_fail('门店地址不能为空!');
        }else{
            $brand_info_ = $this->db->select()->from('brand')->where(array('id' => $user_data['brand_id'], 'status' => 1))->get()->row_array();
            if(!$brand_info_)
                return $this->fun_fail('所选品牌无效!');
            $user_data['other_brand'] = '';
            if($user_data['store_id'] == -1){
                if(!$user_data['shop_name'])
                    return $this->fun_fail('门店地址不能为空!');
            }else{
                $store_info_ = $this->db->select()->from('brand_stores')->where(array('store_id' => $user_data['store_id'], 'status' => 1))->get()->row_array();
                if(!$store_info_)
                    return $this->fun_fail('所选门店二级无效!');
            }

        }
        $user_data['password'] = sha1($password_);
        $check_sms = $this->check_sms($user_data['mobile'], $sms_code, 1);
        if($check_sms['status'] != 1){
            return $check_sms;
        }
        $check_info_ = $this->db->select()->from('users')->where('mobile', $user_data['mobile'])->get()->row_array();
        if($check_info_)
            return $this->fun_fail('账号已存在,不可重复注册!');
        $this->db->insert('users', $user_data);
        $user_id = $this->db->insert_id();
        $row = $this->db->select()->from('users')->where(array('user_id' => $user_id))->get()->row_array();
        if ($row) {
            $code = $this->input->post('code');
            $re_openid = $this->get_mini_openid4log($code);
            if($re_openid['status'] == 1){
                $openid = $re_openid['result']['openid'];
                $this->delOpenidById($user_id, $openid, 'users');
                $this->db->where(array('user_id' => $user_id))->update('users', array('mini_openid' => $openid));
            }
            return $this->fun_success('操作成功',$row);
        } else {
            return $this->fun_fail('登录失败');
        }
    }

    //门店登录
    public function user_login(){
        $data = array(
            'mobile' => trim($this->input->post('mobile')),
            'sms_code' => trim($this->input->post('sms_code')),
        );
        if(!$data['mobile']){
            return $this->fun_fail('手机号不能为空!');
        }
        if(!check_mobile($data['mobile'])){
            return $this->fun_fail('手机号不规范!');
        }
        if(!$data['sms_code']){
            return $this->fun_fail('短信验证码不能为空!');
        }
        $check_sms = $this->check_sms($data['mobile'], $data['sms_code'], 2);
        if($check_sms['status'] != 1){
            return $check_sms;
        }
        $row = $this->db->select()->from('users')->where(array('mobile' => $data['mobile']))->get()->row_array();
        if ($row) {
            if($row['status'] != 1)
                return $this->fun_fail('账号禁用');
            $code = $this->input->post('code');
            $re_openid = $this->get_mini_openid4log($code);
            if($re_openid['status'] == 1){
                $openid = $re_openid['result']['openid'];
                $this->delOpenidById($row['user_id'], $openid, 'users');
                $this->db->where(array('user_id' => $row['user_id']))->update('users', array('mini_openid' => $openid));
            }
            return $this->fun_success('操作成功',$row);
        } else {
            return $this->fun_fail('账号未注册或密码错误');
        }
    }

    //门店登录
    public function user_login_password(){
        $data = array(
            'mobile' => trim($this->input->post('mobile')),
            'password' => trim($this->input->post('password')),
        );
        if(!$data['mobile']){
            return $this->fun_fail('手机号不能为空!');
        }
        if(!check_mobile($data['mobile'])){
            return $this->fun_fail('手机号不规范!');
        }
        if(!$data['password']){
            return $this->fun_fail('密码不能为空!');
        }

        $row = $this->db->select()->from('users')->where(array('mobile' => $data['mobile']))->get()->row_array();
        if ($row) {
            if($row['password'] != sha1($data['password']))
                return $this->fun_fail('账号未注册或密码错误!');
            if($row['status'] != 1)
                return $this->fun_fail('账号禁用');

            $code = $this->input->post('code');
            $re_openid = $this->get_mini_openid4log($code);
            if($re_openid['status'] == 1){
                $openid = $re_openid['result']['openid'];
                $this->delOpenidById($row['user_id'], $openid, 'users');
                $this->db->where(array('user_id' => $row['user_id']))->update('users', array('mini_openid' => $openid));
            }
            return $this->fun_success('操作成功',$row);
        } else {
            return $this->fun_fail('账号未注册或密码错误');
        }
    }

    //通过code获取openid 内部使用方法
    private function get_mini_openid4log($code_){
        if(!$code_){
            return $this->fun_fail('不可缺少code');
        }
        $config_ = array('appid' => $this->config->item('mini_appid'), 'appsecret' => $this->config->item('mini_appsecret'));
        $this->load->library('wechat/MiniAppUtil', $config_, 'MiniApp');
        $miniapp = $this->MiniApp->getSessionInfo($code_);
        if ($miniapp === false) {
            return $this->fun_fail($this->MiniApp->getError());
        }
        return $this->fun_success('操作成功', array('openid' => $miniapp['openid']));
    }

    //获取大客户列表
    public function get_brand_list(){
        $this->db->select("brand_name,id");
        $this->db->from('brand');
        if($status = $this->input->post('status'))
            $this->db->where(array('status' => $status));
        $re = $this->db->get()->result_array();
        return $this->fun_success('获取成功', $re);
	}

    //获取门店二级列表
    public function get_store_list(){
        if(!$brand_id = $this->input->post('brand_id'))
            return $this->fun_fail('参数错误');
        $brand_info_ = $this->db->select()->from('brand')->where(array('id' => $brand_id, 'status' => 1))->get()->row_array();
        if(!$brand_info_)
            return $this->fun_fail('大客户不可用');
        $this->db->select("store_name,store_id");
        $this->db->from('brand_stores');
        if($status = $this->input->post('status'))
            $this->db->where(array('status' => $status));
        $re = $this->db->get()->result_array();
        return $this->fun_success('获取成功', $re);
    }

    public function check_mini(){
        $code_ = $this->input->post('code');
        if(!$code_){
            return $this->fun_fail('不可缺少code');
        }
        $re_openid = $this->get_mini_openid4log($code_);
        if($re_openid['status'] == 1){
            $openid = $re_openid['result']['openid'];
            //逐一进行查看此openid所绑定信息;
            //由安全级别,先从门店账号判断,再到大客户账号,再到管理员账号
            $check_user_ = $this->db->select()->from('users')->where(array('mini_openid' => $openid, 'status' => 1))->get()->row_array();
            if($check_user_){
                $this->delOpenidById($check_user_['user_id'], $openid, 'users');
                $brand_info = $this->readByID("brand", 'id', $check_user_['brand_id']);
                if($brand_info && $brand_info['status'] != 1){

                }else{
                    $result_ = array('type' => 'user', 'id' => $check_user_['user_id'], 'role_id' => -1);
                    return $this->fun_success('成功获取账号', $result_);
                }
            }

            $check_brand_ = $this->db->select()->from('brand')->where(array('mini_openid' => $openid, 'status' => 1))->get()->row_array();
            if($check_brand_){
                $this->delOpenidById($check_brand_['id'], $openid, 'brand');
                $result_ = array('type' => 'brand', 'id' => $check_brand_['id'], 'role_id' => -1);
                return $this->fun_success('成功获取账号', $result_);
            }

            $check_admin_ = $this->db->select()->from('admin')->where(array('mini_openid' => $openid, 'status' => 1))->get()->row_array();
            if($check_admin_){
                $this->delOpenidById($check_admin_['admin_id'], $openid, 'admin');
                $result_ = array('type' => 'admin', 'id' => $check_admin_['admin_id'], 'role_id' => $check_admin_['role_id']);
                return $this->fun_success('成功获取账号', $result_);
            }
            return $this->fun_fail('为找到账号信息!');
        }else{
            return $re_openid;
        }

    }

    public function get_mini_openid(){
        $code_ = $this->input->post('code');
        if(!$code_){
            return $this->fun_fail('不可缺少code');
        }
        $config_ = array('appid' => $this->config->item('mini_appid'), 'appsecret' => $this->config->item('mini_appsecret'));
        $this->load->library('wechat/MiniAppUtil', $config_, 'MiniApp');
        $miniapp = $this->MiniApp->getSessionInfo($code_);
        if ($miniapp === false) {
            return $this->fun_fail($this->MiniApp->getError());
        }
        return $this->fun_success('操作成功', array('openid' => $miniapp['openid']));
    }

}