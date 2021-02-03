<?php
/**
 * Created by PhpStorm.
 * User: bin.shen
 * Date: 5/2/16
 * Time: 09:56
 */

 if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once "Mini_controller.php";
class Mini_login extends Mini_controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('mini_login_model');
        $this->load->model('mini_admin_model');
        $this->load->model('mini_user_model');
    }

    //公司人员登录
    public function admin_login(){
        $rs = $this->mini_login_model->admin_login();
        if($rs['status'] == 1){
            $admin_id = $rs['result']['admin_id'];
            $token = $this->set_token_uid($admin_id,'ADMIN');
            $this->mini_admin_model->update_admin_tt($admin_id,$token);
            $rs['result'] = array('token' => $token);
        }
        $this->ajaxReturn($rs);
    }

    //门店手机短信请求
    public function get_sms(){
        $type = $this->input->post('type');
        $mobile = $this->input->post('mobile');
        if(!$mobile){
            $this->ajaxReturn($this->mini_login_model->fun_fail("电话号码不能为空"));
        }
        if(!check_mobile($mobile)){
            $this->ajaxReturn($this->mini_login_model->fun_fail("手机号不规范"));
        }
        $this->load->model('sms_model');
        //随机一个验证码
        $code = rand(10000, 99999);
        $res = $this->sms_model->send_code($mobile, '房猫服务中心', $code, $type);
        $this->ajaxReturn($res);
    }

    public function user_logon(){
        $rs = $this->mini_login_model->user_logon();
        if($rs['status'] == 1){
            $user_id = $rs['result']['user_id'];
            $token = $this->set_token_uid($user_id,'USER');
            $this->mini_user_model->update_user_tt($user_id,$token);
            $rs['result'] = array('token' => $token);
        }
        $this->ajaxReturn($rs);
    }

    public function get_mini_openid(){
        $rs = $this->mini_login_model->get_mini_openid();
        $this->ajaxReturn($rs);
    }



}