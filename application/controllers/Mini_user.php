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
    private $user_info = [];
    public function __construct()
    {
        parent::__construct();
        $this->load->model('mini_user_model');
        $this->load->model('loan_model');
        $token = $this->input->get('token');
        if(!$token){
            $this->ajaxReturn(array('status' => -100, 'msg' => 'token缺失!', "result" => ''));
        }
        $user_id_ = $this->get_token_uid($token,"USER"); //可以不验证
        $check_re = $this->mini_user_model->check_token($token, $user_id_);
        if($check_re['status'] < 0){
            $this->ajaxReturn($check_re);
        }
        $this->user_id = $check_re['result'];
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





}