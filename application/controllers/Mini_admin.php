<?php
/**
 * Created by PhpStorm.
 * User: bin.shen
 * Date: 5/2/16
 * Time: 09:56
 */

 if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once "Mini_controller.php";
class Mini_admin extends Mini_controller {
    private $admin_id;
    private $admin_info = [];
    public function __construct()
    {
        parent::__construct();
        $this->load->model('mini_admin_model');
        $token = $this->get_header_token();
        if(!$token){
            $this->ajaxReturn(array('status' => -100, 'msg' => 'token缺失!', "result" => ''));
        }
        $admin_id_ = $this->get_token_uid($token,"ADMIN"); //可以不验证
        $check_re = $this->mini_admin_model->check_token($token, $admin_id_);
        if($check_re['status'] < 0){
            $this->ajaxReturn($check_re);
        }
        $this->admin_id = $check_re['result'];
        $this->mini_admin_model->update_admin_tt($this->admin_id); //操作就更新登录时间
    }

    public function get_admin_info(){
        $admin_info = $this->mini_admin_model->get_admin_info($this->admin_id);
        $this->ajaxReturn($admin_info);
    }





}