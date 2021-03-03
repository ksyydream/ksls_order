<?php
/**
 * Created by PhpStorm.
 * User: bin.shen
 * Date: 5/2/16
 * Time: 09:56
 */

 if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once "Mini_controller.php";
class Mini_user extends Mini_controller {
    private $user_id;
    private $brand_id;
    private $user_info = [];
    public function __construct()
    {
        parent::__construct();
        $this->load->model('mini_user_model');
        $this->load->model('loan_model');
        //var_dump("asd");
        //die();
        $token = $this->get_header_token();
        if(!$token){
            $this->ajaxReturn(array('status' => -100, 'msg' => 'token缺失!', "result" => ''));
        }
        $user_id_ = $this->get_token_uid($token,"USER"); //可以不验证
        $check_re = $this->mini_user_model->check_token($token, $user_id_);
        if($check_re['status'] < 0){
            $this->ajaxReturn($check_re);
        }
        $this->user_id = $check_re['result']['user_id'];
        $this->brand_id = $check_re['result']['brand_id'];
        $this->mini_user_model->update_user_tt($this->user_id); //操作就更新登录时间
    }

    public function get_user_info(){
        $user_info = $this->mini_user_model->get_user_info($this->user_id);
        $this->ajaxReturn($user_info);
    }

    public function save_loan(){
        $rs = $this->loan_model->save_loan($this->user_id);
        $this->ajaxReturn($rs);
	}

    public function loan_list(){
        $rs = $this->loan_model->loan_list4user($this->user_id);
        $this->ajaxReturn($rs);
    }

    public function loan_info(){
        $loan_id = $this->input->post('loan_id');
        $rs = $this->loan_model->loan_info($loan_id);
        if ($rs['status'] != 1) {
	         $this->ajaxReturn($rs);
        }
        //验证权限
        $loan_info = $rs['result'];
        if($loan_info['user_id'] != $this->user_id){
             $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
		}

        //返回信息
        $this->ajaxReturn($this->loan_model->fun_success("获取成功！", $loan_info));
	}

    //获取借款人信息
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
        if($borrower_info['user_id'] != $this->user_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        $this->ajaxReturn($rs);
    }

    //获取业务总数
    public function loan_count(){
        $rs = $this->loan_model->loan_count4user($this->user_id);
        $this->ajaxReturn($rs);
    }

    //修改个人信息
    public function save_user_info(){
        $user_info = $this->mini_user_model->save_user_info($this->user_id);
        $this->ajaxReturn($user_info);
    }

    public function change_password(){
        $rs = $this->mini_user_model->change_password($this->user_id);
        $this->ajaxReturn($rs);
    }


}