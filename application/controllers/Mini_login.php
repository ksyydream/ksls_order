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
        $this->load->model('mini_brand_model');
        $this->load->model('mini_user_model');
    }

    //公司人员登录
    public function admin_login(){
        $rs = $this->mini_login_model->admin_login();
        if($rs['status'] == 1){
            $admin_id = $rs['result']['admin_id'];
            $role_id = $rs['result']['role_id'];
            $token = $this->set_token_uid($admin_id,'ADMIN');
            $this->mini_admin_model->update_admin_tt($admin_id,$token);
            $rs['result'] = array('token' => $token, 'role_id' => $role_id);
        }
        $this->ajaxReturn($rs);
    }

    //公司人员登录
    public function brand_login(){
        $rs = $this->mini_login_model->brand_login();
        if($rs['status'] == 1){
            $brand_id = $rs['result']['id'];
            $token = $this->set_token_uid($brand_id,'BRAND');
            $this->mini_brand_model->update_brand_tt($brand_id,$token);
            $rs['result'] = array('token' => $token);
        }
        $this->ajaxReturn($rs);
    }

    //门店手机短信请求 type 是1的时候代表注册，2的时候代表登录
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

    //门店公司账号 注册
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

    //公司人员登录
    public function user_login(){
        $rs = $this->mini_login_model->user_login();
        if($rs['status'] == 1){
            $user_id = $rs['result']['user_id'];
            $token = $this->set_token_uid($user_id,'USER');
            $this->mini_user_model->update_user_tt($user_id,$token);
            $rs['result'] = array('token' => $token);
        }
        $this->ajaxReturn($rs);
    }

    //公司人员登录 密码登录
    public function user_login_password(){
        $rs = $this->mini_login_model->user_login_password();
        if($rs['status'] == 1){
            $user_id = $rs['result']['user_id'];
            $token = $this->set_token_uid($user_id,'USER');
            $this->mini_user_model->update_user_tt($user_id,$token);
            $rs['result'] = array('token' => $token);
        }
        $this->ajaxReturn($rs);
    }

    public function get_brand_list(){
        $rs = $this->mini_login_model->get_brand_list();
        $this->ajaxReturn($rs);
	}

    //获取门店二级 列表
    public function get_store_list(){
        $rs = $this->mini_login_model->get_store_list();
        $this->ajaxReturn($rs);
    }

    //账号退出 三种账号均可以使用
    public function logout(){
        $token = $this->get_header_token();
        $rs = $this->mini_login_model->logout($token);
        $this->ajaxReturn($rs);
	}

    //效验人员信息 通过code
    public function check_mini(){
        $check_mini_ = $this->mini_login_model->check_mini();
        if($check_mini_['status'] == 1){
            $result_ = array();
            $id_ = $check_mini_['result']['id'];
            switch($check_mini_['result']['type']){
                case 'user':
                    $token = $this->set_token_uid($id_,'USER');
                    $this->mini_user_model->update_user_tt($id_,$token);
                    $result_ = array('token' => $token, 'type' => $check_mini_['result']['type'], 'role_id' => -1);
                    break;
                case 'brand':
                    $token = $this->set_token_uid($id_,'BRAND');
                    $this->mini_brand_model->update_brand_tt($id_,$token);
                    $result_ = array('token' => $token, 'type' => $check_mini_['result']['type'], 'role_id' => -1);
                    break;
                case 'admin':
                    $token = $this->set_token_uid($id_,'ADMIN');
                    $this->mini_admin_model->update_admin_tt($id_,$token);
                    $result_ = array('token' => $token, 'type' => $check_mini_['result']['type'], 'role_id' => $check_mini_['result']['role_id']);
                    break;
                default:
                    $this->ajaxReturn($this->mini_login_model->fun_fail("未寻找到账号信息"));
            }
            $this->ajaxReturn($this->mini_login_model->fun_success("获取成功", $result_));
        }
        $this->ajaxReturn($check_mini_);
    }

    public function get_mini_openid(){
        $rs = $this->mini_login_model->get_mini_openid();
        $this->ajaxReturn($rs);
    }

    public function qr_code_raw4agent($invite)
    {
        require_once (APPPATH . 'libraries/phpqrcode/phpqrcode.php');
        $data = array('invite' => $invite);
        $str_ = json_encode($data, JSON_UNESCAPED_UNICODE);
        QRcode::png($str_);
    }



}