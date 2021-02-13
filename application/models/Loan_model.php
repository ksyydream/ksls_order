<?php
/**
 * Created by PhpStorm.
 * User: bin.shen
 * Date: 6/2/16
 * Time: 21:22
 */

class Loan_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }


    //新建赎楼业务
    public function save_loan($user_id){

        $borrowers = $this->input->post("borrowers");;
        if(!is_array($borrowers)){
            $borrowers = json_decode($borrowers,true);
        }
        if(!$borrowers)
            return $this->fun_fail('借款人不能为空!');
        foreach($borrowers as $k_ => $v_){
            if(!isset($v_['borrower_name']) || trim($v_['borrower_name']) == "")
                return $this->fun_fail('存在借款人姓名为空!');
            if(!isset($v_['borrower_phone']) || trim($v_['borrower_phone']) == "")
                return $this->fun_fail('存在借款人电话为空!');
            if(!isset($v_['borrower_card']) || trim($v_['borrower_card']) == "")
                return $this->fun_fail('存在借款人身份证为空!');
        }
        //获取门店 大客户品牌
        $user_info = $this->readByID("users", 'user_id', $user_id);
        if(!$user_info)
            return $this->fun_fail('异常!');
        $brand_id = $user_info['brand_id'] ? $user_info['brand_id'] : -1;
        $data = array(
            'user_id' => $user_id,
            'create_user_id' => $user_id,
            'brand_id' => $brand_id,
            'create_time' => time(),
            'loan_money' => trim($this->input->post('loan_money')),
            'appointment_date' => trim($this->input->post('appointment_date')),
            'old_loan_money' => trim($this->input->post('old_loan_money')) ? trim($this->input->post('old_loan_money')) : null,
            'old_mortgage_money_one' => trim($this->input->post('old_mortgage_money_one')) ? trim($this->input->post('old_mortgage_money_one')) : null,
            'old_mortgage_money_two' => trim($this->input->post('old_mortgage_money_two')) ? trim($this->input->post('old_mortgage_money_two')) : null,
            'old_mortgage_bank_one' => trim($this->input->post('old_mortgage_bank_one')) ? trim($this->input->post('old_mortgage_bank_one')) : null,
            'old_mortgage_bank_two' => trim($this->input->post('old_mortgage_bank_two')) ? trim($this->input->post('old_mortgage_bank_two')) : null,
            'houses_price' => trim($this->input->post('houses_price')) ? trim($this->input->post('houses_price')) : null,
            'buyer_loan' => trim($this->input->post('buyer_loan')) ? trim($this->input->post('buyer_loan')) : null
		);
        //先验证关键数据是否有效
        if(!$data['loan_money'] || $data['loan_money'] <= 0){
            return $this->fun_fail('借款金额不能为空!');
        }

        if(!$data['old_loan_money']){
            return $this->fun_fail('老贷金额不能为空!');
        }

        if(!$data['old_mortgage_bank_one']){
            return $this->fun_fail('老贷一抵机构名称 不能为空!');
        }

        if(!$data['old_mortgage_money_one']){
            return $this->fun_fail('老贷一抵金额 不能为空!');
        }

        if(!$data['houses_price'] || $data['loan_money'] <= 0){
            return $this->fun_fail('房屋总价 不能为空!');
        }

        if(!$data['buyer_loan']  || $data['loan_money'] <= 0){
            return $this->fun_fail('买方贷款金额 不能为空!');
        }

        if(!$data['appointment_date']){
            return $this->fun_fail('面签预约时间 不能为空!');
        }
        $data['work_no'] = $this->get_workno();
        $data['mx_admin_id'] = $this->get_mx_admin_id();
        $this->db->insert('loan_master', $data);
        $loan_id = $this->db->insert_id();
        $borrowers_insert_ = array();
        foreach($borrowers as $k => $v){
            $b_insert_ = array(
                'borrower_name' => $v['borrower_name'],
                'borrower_phone' => $v['borrower_phone'],
                'borrower_card' => $v['borrower_card'],
                'loan_id' => $loan_id,
                'td_status' => 1
            );
            $borrower_td_info_ = $this->get_tongdun_info($v['borrower_name'], $v['borrower_card'], $v['borrower_phone'], $user_id);
            if($borrower_td_info_ && $borrower_td_info_['status'] == 1){
                $td_info = $borrower_td_info_['result'];
                $b_insert_['td_id'] = $td_info['id'];
                $json_data = json_decode($td_info['json_data']);
                if($json_data->success == true){
                    $b_insert_['td_score'] = $json_data->result_desc->ANTIFRAUD->final_score;
                    $b_insert_['td_decision'] = $json_data->result_desc->ANTIFRAUD->final_decision;
                }
            }
            if(!isset($b_insert_['td_decision']) || !in_array($b_insert_['td_decision'], array('REVIEW', 'PASS'))){
                //只要存在一个借款人 不满足同盾条件,订单就改成 同盾拒单
                $this->db->where('loan_id', $loan_id)->update('loan_master', array('is_td_ng' => 1));
                $b_insert_['td_status'] = -1;
            }

            $borrowers_insert_[] = $b_insert_;
        }
        $this->db->insert_batch('loan_borrowers', $borrowers_insert_);
        return $this->fun_success('操作成功');
	}

    //赎楼申请单列表 大客户端
    public function loan_list4brand($brand_id){
        $where_ = array('a.brand_id' => $brand_id);
        $order_1 = 'a.create_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    //赎楼业务列表 私有 共用方法
    private function loan_list($where, $order_1 = 'a.create_time', $order_2 = 'desc'){
        $res = array();
        $data['limit'] = $this->mini_limit;//每页显示多少调数据
        $data['keyword'] = $this->input->post('keyword')?trim($this->input->post('keyword')):null;
        $data['brand_id'] = $this->input->post('brand_id')?trim($this->input->post('brand_id')):null;
        $data['user_id'] = $this->input->post('user_id')?trim($this->input->post('user_id')):null;
        $data['flag'] = $this->input->post('flag')?trim($this->input->post('flag')):null;
        $page = $this->input->post('page')?trim($this->input->post('page')):1;
        $this->db->select('count(DISTINCT a.loan_id) num');
        $this->db->from('loan_master a');
        $this->db->join('loan_borrowers b', 'a.loan_id = b.loan_id', 'left');
        $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('b.borrowe_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['user_id']){
            $this->db->where('a.user_id', $data['user_id']);
        }
        if($data['brand_id']){
            $this->db->where('a.brand_id', $data['brand_id']);
        }
        $num = $this->db->get()->row();
        $res['total_rows'] = $num->num;
        $res['total_page'] = ceil($res['total_rows'] / $data['limit']);
        $this->db->select('a.loan_id,a.work_no,a.loan_money,u.rel_name handle_user, u1.rel_name create_user, bd.brand_name,FROM_UNIXTIME(a.create_time) loan_cdate');
        $this->db->from('loan_master a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('loan_borrowers b', 'a.loan_id = b.loan_id', 'left');
        $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('b.borrower_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['brand_id']){
            $this->db->where('a.brand_id', $data['brand_id']);
        }
        if($data['user_id']){
            $this->db->where('a.user_id', $data['user_id']);
        }
        $this->db->order_by($order_1, $order_2);
        $this->db->group_by('a.loan_id');
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $res['res_list'] = $this->db->get()->result_array();
        foreach($res['res_list'] as $k => $v){
            $this->db->select('borrower_name,borrower_phone');
            $this->db->from('loan_borrowers');
            $this->db->where('loan_id', $v['loan_id']);
            $res['res_list'][$k]['borrowers_list'] = $this->db->get()->result_array();
        }
        return $res;
    }

    //赎楼业务详情
    public function loan_info($loan_id, $select = "*"){
        $select = "a.*,u.rel_name handle_user, u1.rel_name create_user, bd.brand_name";
        $this->db->select($select)->from('loan_master a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $loan_info = $this->db->where('a.loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('未找到相关订单!');
        $this->db->select('*');
        $this->db->from('loan_borrowers');
        $this->db->where('loan_id', $loan_id);
        $loan_info['borrowers_list'] = $this->db->get()->result_array();
        return $this->fun_success('获取成功!', $loan_info);
	}

    //单独获取借款人信息
    public function loan_borrower_info($b_id){
        $this->db->select('a.brand_id, a.status, a.flag, a.user_id, b.*')->from('loan_master a');
        $this->db->join('loan_borrowers b','a.loan_id = b.loan_id','left');
        $this->db->where('b.id', $b_id);
        $info_ = $this->db->get()->row_array();
        if($info_){
            return $this->fun_success('获取成功!', $info_);
        }else{
            return $this->fun_fail('信息不存在!');
        }

    }

    /**
     *********************************************************************************************
     * 以下代码为门店端 专用
     *********************************************************************************************
     */

    //赎楼申请单列表 门店账号端
    public function loan_list4user($user_id){
        $where_ = array('a.user_id' => $user_id);
        $order_1 = 'a.create_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    /**
     *********************************************************************************************
     * 以下代码为管理员端 专用
     *********************************************************************************************
     */
    //修改借款人信息,因为验证的东西比较多,所以单独做模块操作
    public function edit_borrower_info4admin($admin_id){
        $b_id = $this->input->post('b_id');
        if(!$b_id){
            return $this->fun_fail('缺少参数!');
        }
        $rs_ = $this->loan_borrower_info($b_id);
        if($rs_['status'] != -1){
            return $this->fun_fail('信息不存在!');
        }

        $borrower_info_ = $rs_['result'];
        if($borrower_info_['status'] != 1 || $borrower_info_['flag'] != 1){
            return $this->fun_fail('申请单状态 不允许变更信息!');
        }

        if($borrower_info_['mx_admin_id'] != $admin_id){
            return $this->fun_fail('您无权限操作此单!');
        }

        //开始修改信息操作
        $update_ = array(
            'borrower_name' => trim($this->input->post('borrower_name')) ? trim($this->input->post('borrower_name')) : '',
            'borrower_phone' => trim($this->input->post('borrower_phone')) ? trim($this->input->post('borrower_phone')) : '',
            'borrower_card' => trim($this->input->post('borrower_card')) ? trim($this->input->post('borrower_card')) : '',
            'td_status' => 1
        );
        if(!isset($update_['borrower_name']) || trim($update_['borrower_name']) == "")
            return $this->fun_fail('借款人姓名不能为空!');
        if(!isset($update_['borrower_phone']) || trim($update_['borrower_phone']) == "")
            return $this->fun_fail('借款人电话不能为空!');
        if(!isset($update_['borrower_card']) || trim($update_['borrower_card']) == "")
            return $this->fun_fail('借款人身份证不能为空!');

        //重新验证同盾
        $borrower_td_info_ = $this->get_tongdun_info($update_['borrower_name'], $update_['borrower_card'], $update_['borrower_phone'], $user_id);
        if($borrower_td_info_ && $borrower_td_info_['status'] == 1){
            $td_info = $borrower_td_info_['result'];
            $update_['td_id'] = $td_info['id'];
            $json_data = json_decode($td_info['json_data']);
            if($json_data->success == true){
                $update_['td_score'] = $json_data->result_desc->ANTIFRAUD->final_score;
                $update_['td_decision'] = $json_data->result_desc->ANTIFRAUD->final_decision;
            }
        }
        if(!isset($update_['td_decision']) || !in_array($update_['td_decision'], array('REVIEW', 'PASS'))){
            //只要存在一个借款人 不满足同盾条件,订单就改成 同盾拒单
            $update_['td_status'] = -1;
        }
        $this->db->trans_start();//--------开始事务
        $this->db->where('id', $b_id)->update('loan_borrowers', $update_);
        $check_td_ = $this->db->select()->from('loan_borrowers')->where(array(
            'loan_id' => $borrower_info_['loan_id'],
            'td_status' => -1
        ))->get()->row_array();
        if($check_td_){
            $this->db->where('loan_id', $borrower_info_['loan_id'])->update('loan_master', array('is_td_ng' => 1));
        }else{
            $this->db->where('loan_id', $borrower_info_['loan_id'])->update('loan_master', array('is_td_ng' => -1));
        }

        $this->db->trans_complete();//------结束事务
        if ($this->db->trans_status() === FALSE) {
            return $this->fun_fail('保存失败!');
        } else {
            return $this->fun_success('保存成功!');
        }
    }

    //赎楼申请单列表 管理员端, 面签经理
    public function loan_list4mx($admin_id){
        $where_ = array('a.mx_admin_id' => $admin_id);
        $order_1 = 'a.create_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }
}