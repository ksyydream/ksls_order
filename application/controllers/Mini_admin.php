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
        $this->load->model('loan_model');
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
        $this->mini_admin_model->save_admin_log($this->admin_id); //保存操作记录
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
        //验证面签经理权限
        if($this->role_id == 1 && $borrower_info['mx_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        //验证风控经理权限
        if($this->role_id == 2 && $borrower_info['fk_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        //验证权证权限
        if($this->role_id == 3 && $borrower_info['qz_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        //验证权证(交易中心)权限
        if($this->role_id == 7 && $borrower_info['fc_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        $this->ajaxReturn($rs);
    }

    //赎楼列表
    public function loan_list(){
        switch($this->role_id){
            case 1:
                $rs = $this->loan_model->loan_list4mx($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 2:
                $rs = $this->loan_model->loan_list4fk($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 3:
                //权证
                $rs = $this->loan_model->loan_list4qz($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 4:
                //财务
                $rs = $this->loan_model->loan_list4cw($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 5:
                //终审
                $rs = $this->loan_model->loan_list4zs($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            case 7:
                //权证 交易中心
                $rs = $this->loan_model->loan_list4fc($this->admin_id);
                $this->ajaxReturn($rs);
                break;
            default:
                $this->ajaxReturn($this->loan_model->fun_fail("未找到可用数据！"));

        }

    }

    public function loan_info(){
        $loan_id = $this->input->post('loan_id');
        $rs = $this->loan_model->loan_info($loan_id);
        if ($rs['status'] != 1) {
            $this->ajaxReturn($rs);
        }
        //验证权限
        $loan_info = $rs['result'];
        //验证面签经理权限
        if($this->role_id == 1 && $loan_info['mx_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        //验证风控经理权限
        if($this->role_id == 2 && $loan_info['fk_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        //验证权证(银行)权限
        if($this->role_id == 3 && $loan_info['qz_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }
        //验证权证(交易中心)权限
        if($this->role_id == 7 && $loan_info['fc_admin_id'] != $this->admin_id){
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        }


        //返回信息
        $this->ajaxReturn($this->loan_model->fun_success("获取成功！", $loan_info));
    }

    public function ht_list(){
        $rs = $this->mini_admin_model->ht_list();
        $this->ajaxReturn($rs);
    }

    //邀请门店账号列表
    public function invite_list(){
        $where = array('a.invite' => $this->admin_id);
        $rs = $this->mini_user_model->user_list($where);
        $this->ajaxReturn($rs);
    }

    //邀请门店账号列表
    public function invite_loan_list(){
        $rs = $this->loan_model->loan_list4invite($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //邀请门店账号列表
    public function invite_loan_info(){
        $loan_id = $this->input->post('loan_id');
        $rs = $this->loan_model->loan_info($loan_id);
        if ($rs['status'] != 1) {
            $this->ajaxReturn($rs);
        }
        //验证权限
        $loan_info = $rs['result'];
        if($loan_info['invite'] != $this->admin_id)
            $this->ajaxReturn($this->loan_model->fun_fail("您无权限操作此单！"));
        $this->ajaxReturn($rs);
    }

    /**
     *********************************************************************************************
     * 以下代码为面签 专用
     *********************************************************************************************
     */
    //修改面签时间
    public function edit_appointment_date(){
        $rs = $this->loan_model->edit_appointment_date4admin($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //修改赎楼基本信息
    public function edit_loan_info(){
        $rs = $this->loan_model->edit_loan_info4admin($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //修改借款人信息,账号和申请单状态 验证 在模块内处理
    public function edit_borrower_info(){
        $rs = $this->loan_model->edit_borrower_info4admin($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //删除借款人信息,账号和申请单状态 验证 在模块内处理
    public function del_borrower_info(){
        $rs = $this->loan_model->del_borrower_info4admin($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //增加借款人信息,账号和申请单状态 验证 在模块内处理
    public function add_borrower_info(){
        $rs = $this->loan_model->add_borrower_info4admin($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //面签审核
    public function handle_loan_mx(){
        $rs = $this->loan_model->handle_loan_mx($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //风控审核
    public function handle_loan_fk(){
        $rs = $this->loan_model->handle_loan_fk($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //终审审核
    public function handle_loan_zs(){
        $rs = $this->loan_model->handle_loan_zs($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //权证(银行)审核
    public function handle_loan_qz(){
        $rs = $this->loan_model->handle_loan_qz($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //权证(银行)审核
    public function handle_loan_fc(){
        $rs = $this->loan_model->handle_loan_fc($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //财务审核
    public function handle_loan_cw(){
        $rs = $this->loan_model->handle_loan_cw($this->admin_id);
        $this->ajaxReturn($rs);
    }

    //获取业务总数
    public function loan_count(){
        $rs = $this->loan_model->loan_count4admin($this->admin_id, $this->role_id);
        $this->ajaxReturn($rs);
    }

    //赎楼监管项
    public function show_loan_supervise(){
        $rs = $this->loan_model->show_loan_supervise();
        $this->ajaxReturn($rs);
    }

    //保存赎楼监管项
    public function save_loan_supervise(){
        $rs = $this->loan_model->save_loan_supervise($this->admin_id, $this->role_id);
        $this->ajaxReturn($rs);
    }

    public function change_password(){
        $rs = $this->mini_admin_model->change_password($this->admin_id);
        $this->ajaxReturn($rs);
    }

}