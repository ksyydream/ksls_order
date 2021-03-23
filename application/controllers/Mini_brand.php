<?php
/**
 * Created by PhpStorm.
 * User: bin.shen
 * Date: 5/2/16
 * Time: 09:56
 */

 if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once "Mini_controller.php";
class Mini_brand extends Mini_controller {
    private $brand_id;
    private $brand_info = [];
    public function __construct()
    {
        parent::__construct();
        $this->load->model('mini_brand_model');
        $this->load->model('mini_user_model');
        $this->load->model('loan_model');
        $token = $this->get_header_token();
        if(!$token){
            $this->ajaxReturn(array('status' => -100, 'msg' => 'token缺失!', "result" => ''));
        }
        $brand_id_ = $this->get_token_uid($token,"BRAND"); //可以不验证
        $check_re = $this->mini_brand_model->check_token($token, $brand_id_);
        if($check_re['status'] < 0){
            $this->ajaxReturn($check_re);
        }
        $this->brand_id = $check_re['result']['id'];
        $this->mini_brand_model->update_brand_tt($this->brand_id); //操作就更新登录时间
    }

    public function get_brand_info(){
        $brand_info = $this->mini_brand_model->get_brand_info($this->brand_id);
        $this->ajaxReturn($brand_info);
    }

    public function get_store_list(){
        $brand_info = $this->mini_brand_model->get_store_list($this->brand_id);
        $this->ajaxReturn($brand_info);
    }

    public function loan_list(){
        $rs = $this->loan_model->loan_list4brand($this->brand_id);
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
        if($loan_info['brand_id'] != $this->brand_id){
             $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
		}

        //返回信息
        $this->ajaxReturn($this->loan_model->fun_success("获取成功！", $loan_info));
	}

    public function user_list(){
        $where = array('a.brand_id' => $this->brand_id);
        $rs = $this->mini_user_model->user_list($where);
        $this->ajaxReturn($rs);
    }

    //获取业务总数
    public function loan_count(){
        $rs = $this->loan_model->loan_count4brand($this->brand_id);
        $this->ajaxReturn($rs);
    }

    public function change_password(){
        $rs = $this->mini_brand_model->change_password($this->brand_id);
        $this->ajaxReturn($rs);
    }

}