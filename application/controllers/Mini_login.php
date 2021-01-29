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



}