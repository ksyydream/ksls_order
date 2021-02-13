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
    private $role_id;
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
        $this->admin_id = $check_re['result']['admin_id'];
        $this->role_id = $check_re['result']['role_id'];
        $this->mini_admin_model->update_admin_tt($this->admin_id); //操作就更新登录时间
    }

    public function get_admin_info(){
        $admin_info = $this->mini_admin_model->get_admin_info($this->admin_id);
        $this->ajaxReturn($admin_info);
    }

    //获取借款人信息 根据token自动判断权限
    public function loan_borrower_info(){
        //获取借款人信息,只是查看 所以不做太多验证
        $b_id = $this->input->post('b_id');
        if(!$b_id){
            $this->ajaxReturn($this->loan_model->fun_fail("缺少参数！"));
        }
        $rs = $this->loan_model->loan_borrower_info($b_id);
        if ($rs['status'] != 1) {
            $this->ajaxReturn($rs);
        }
        $borrower_info = $rs['result'];
        if($borrower_info['mx_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        $this->ajaxReturn($rs);
    }
    /**
     *********************************************************************************************
     * 以下代码为面签 专用
     *********************************************************************************************
     */
    //面签经理 赎楼列表
    public function loan_list4mq(){
        $rs = $this->loan_model->loan_list4mq($this->admin_id);
        $this->ajaxReturn($rs);
    }


}