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

    public function logout(){
        $this->db->where('user_id',$this->session->userdata('wx_user_id'))->update('users',array('openid'=>''));
        $this->db->where('m_id',$this->session->userdata('wx_m_id'))->update('members',array('openid'=>''));
        $this->session->unset_userdata('wx_user_id');
        $this->session->unset_userdata('wx_m_id');
        $this->session->unset_userdata('wx_class');
        $this->session->sess_destroy();
    }

    public function admin_login(){

        $data = array(
            'user' => trim($this->input->post('user')),
            'password' => password(trim($this->input->post('password'))),
        );
        $row = $this->db->select()->from('admin')->where($data)->get()->row_array();
        if ($row) {
            return $this->fun_success('操作成功',$row);
        } else {
            return $this->fun_fail('登录失败');
        }
    }

    //门店注册
    public function user_logon(){
        $user_data = array(
            'mobile' => trim($this->input->post('mobile')),
            'rel_name' => trim($this->input->post('rel_name')),
            'reg_time' => time(),
            'brand_id' => trim($this->input->post('brand_id')) ? trim($this->input->post('brand_id')) : -1,
            'shop_name' => trim($this->input->post('shop_name')),
        );
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
        if(!$user_data['shop_name']){
            return $this->fun_fail('门店地址不能为空!');
        }
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
        $row = $this->db->select()->from('users')->where(array('mobile' => $data['mobile'], 'status' => 1))->get()->row_array();
        if ($row) {
            $code = $this->input->post('code');
            $re_openid = $this->get_mini_openid4log($code);
            if($re_openid['status'] == 1){
                $openid = $re_openid['result']['openid'];
                $this->delOpenidById($row['user_id'], $openid, 'users');
                $this->db->where(array('user_id' => $row['user_id']))->update('users', array('mini_openid' => $openid));
            }
            return $this->fun_success('操作成功',$row);
        } else {
            return $this->fun_fail('登录失败');
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
      $re = $this->db->select("brand_name,id")->from('brand')->where(array('status' => 1))->get()->result_array();
      return $this->fun_success('获取成功', $re);
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