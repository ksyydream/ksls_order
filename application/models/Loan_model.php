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
            'modify_time' => time(),
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
        $data['mx_admin_id'] = $this->get_role_admin_id(1);
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
                if(isset($b_insert_['td_decision'])){
                    $b_insert_['td_status'] = -1;
                }
            }else{
                $b_insert_['td_status'] = 2;
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
        $data['flag'] = $this->input->post('flag') ? trim($this->input->post('flag')) : 1; //默认查进行中
        $data['status'] = $this->input->post('status') ? trim($this->input->post('status')) : null;

        $page = $this->input->post('page')?trim($this->input->post('page')):1;
        $this->db->select('count(DISTINCT a.loan_id) num');
        $this->db->from('loan_master a');
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
        if($data['status']){
            $this->db->where('a.status', $data['status']);
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
        $this->db->select('a.loan_id,a.work_no,a.loan_money,
        u.rel_name handle_name,u.mobile handle_mobile,
        u1.rel_name create_name,u1.mobile create_mobile,
         bd.brand_name,FROM_UNIXTIME(a.create_time) loan_cdate, mx.admin_name mx_name,mx.phone mx_phone,a.appointment_date');
        $this->db->from('loan_master a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('loan_borrowers b', 'a.loan_id = b.loan_id', 'left');
        $this->db->join('admin mx', 'a.mx_admin_id = mx.admin_id', 'left');
        $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('b.borrower_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['status']){
            $this->db->where('a.status', $data['status']);
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
        $select = "a.*,FROM_UNIXTIME(a.create_time) loan_cdate,
        u.rel_name handle_name,u.mobile handle_mobile,
        u1.rel_name create_name,u1.mobile create_mobile,
        bd.brand_name, mx.admin_name mx_name, fk.admin_name fk_name,mx.phone mx_phone";
        $this->db->select($select)->from('loan_master a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('admin mx', 'a.mx_admin_id = mx.admin_id', 'left');
        $this->db->join('admin fk', 'a.fk_admin_id = fk.admin_id', 'left');
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
        $this->db->select('a.brand_id, a.status, a.flag, a.user_id, a.mx_admin_id, a.fk_admin_id, a.qz_admin_id,b.*')->from('loan_master a');
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
        if($rs_['status'] != 1){
            return $this->fun_fail('信息不存在!');
        }
        $borrower_info_ = $rs_['result'];
        $check_role4admin_ = $this->check_role4admin($admin_id, $borrower_info_['loan_id']);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;

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

        //验证下身份证号码是否已存在
        $check_has_ = $this->db->select()->from('loan_borrowers')->where(array('loan_id' => $borrower_info_['loan_id'], 'id <>' => $b_id, 'borrower_card' => $update_['borrower_card']))->get()->row_array();
        if($check_has_)
            return $this->fun_fail('此借款人身份证号已存在!');

        //重新验证同盾
        $borrower_td_info_ = $this->get_tongdun_info($update_['borrower_name'], $update_['borrower_card'], $update_['borrower_phone'], -1);
        if($borrower_td_info_ && $borrower_td_info_['status'] == 1){
            $td_info = $borrower_td_info_['result'];
            $update_['td_id'] = $td_info['id'];
            $json_data = json_decode($td_info['json_data']);
            if($json_data->success == true){
                $update_['td_score'] = $json_data->result_desc->ANTIFRAUD->final_score;
                $update_['td_decision'] = $json_data->result_desc->ANTIFRAUD->final_decision;
            }
        }
        if(isset($b_insert_['td_decision'])){
            if(!in_array($update_['td_decision'], array('REVIEW', 'PASS'))){
                //只要存在一个借款人 不满足同盾条件,订单就改成 同盾拒单
                $update_['td_status'] = -1;
            }else{
                $update_['td_status'] = 2;
            }
        }

        $this->db->trans_start();//--------开始事务
        $this->db->where('id', $b_id)->update('loan_borrowers', $update_);
        $check_td_ = $this->db->select()->from('loan_borrowers')->where(array(
            'loan_id' => $borrower_info_['loan_id'],
            'td_status <' => 1
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

    public function del_borrower_info4admin($admin_id){
        $b_id = $this->input->post('b_id');
        if(!$b_id){
            return $this->fun_fail('缺少参数!');
        }
        $rs_ = $this->loan_borrower_info($b_id);
        if($rs_['status'] != 1){
            return $this->fun_fail('信息不存在!');
        }

        $borrower_info_ = $rs_['result'];
        $check_role4admin_ = $this->check_role4admin($admin_id, $borrower_info_['loan_id']);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;

        //检查是否是最后一个 借款人
        $check_has_ = $this->db->select()->from('loan_borrowers')->where(array('loan_id' => $borrower_info_['loan_id'], 'id <>' => $b_id))->get()->row_array();
        if(!$check_has_)
            return $this->fun_fail('不可删除最后一个借款人!');

        $this->db->trans_start();//--------开始事务
        $this->db->where('id', $b_id)->delete('loan_borrowers');
        $check_td_ = $this->db->select()->from('loan_borrowers')->where(array('loan_id' => $borrower_info_['loan_id'], 'td_status <' => 1))->get()->row_array();
        if($check_td_){
            $this->db->where('loan_id', $borrower_info_['loan_id'])->update('loan_master', array('is_td_ng' => 1));
        }else{
            $this->db->where('loan_id', $borrower_info_['loan_id'])->update('loan_master', array('is_td_ng' => -1));
        }

        $this->db->trans_complete();//------结束事务
        if ($this->db->trans_status() === FALSE) {
            return $this->fun_fail('删除失败!');
        } else {
            return $this->fun_success('删除成功!');
        }
    }

    public function add_borrower_info4admin($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;

        //开始修改信息操作
        $insert_ = array(
            'loan_id' => $loan_id,
            'borrower_name' => trim($this->input->post('borrower_name')) ? trim($this->input->post('borrower_name')) : '',
            'borrower_phone' => trim($this->input->post('borrower_phone')) ? trim($this->input->post('borrower_phone')) : '',
            'borrower_card' => trim($this->input->post('borrower_card')) ? trim($this->input->post('borrower_card')) : '',
            'td_status' => 1
        );
        if(!isset($insert_['borrower_name']) || trim($insert_['borrower_name']) == "")
            return $this->fun_fail('借款人姓名不能为空!');
        if(!isset($insert_['borrower_phone']) || trim($insert_['borrower_phone']) == "")
            return $this->fun_fail('借款人电话不能为空!');
        if(!isset($insert_['borrower_card']) || trim($insert_['borrower_card']) == "")
            return $this->fun_fail('借款人身份证不能为空!');

        //验证下身份证号码是否已存在
        $check_has_ = $this->db->select()->from('loan_borrowers')->where(array('loan_id' => $loan_id, 'borrower_card' => $insert_['borrower_card']))->get()->row_array();
        if($check_has_)
            return $this->fun_fail('此借款人身份证号已存在!');

        //重新验证同盾
        $borrower_td_info_ = $this->get_tongdun_info($insert_['borrower_name'], $insert_['borrower_card'], $insert_['borrower_phone'], -1);
        if($borrower_td_info_ && $borrower_td_info_['status'] == 1){
            $td_info = $borrower_td_info_['result'];
            $insert_['td_id'] = $td_info['id'];
            $json_data = json_decode($td_info['json_data']);
            if($json_data->success == true){
                $insert_['td_score'] = $json_data->result_desc->ANTIFRAUD->final_score;
                $insert_['td_decision'] = $json_data->result_desc->ANTIFRAUD->final_decision;
            }
        }
        if(isset($b_insert_['td_decision'])){
            if(!in_array($insert_['td_decision'], array('REVIEW', 'PASS'))){
                //只要存在一个借款人 不满足同盾条件,订单就改成 同盾拒单
                $insert_['td_status'] = -1;
            }else{
                $insert_['td_status'] = 2;
            }
        }

        $this->db->trans_start();//--------开始事务
        $this->db->insert('loan_borrowers', $insert_);
        $check_td_ = $this->db->select()->from('loan_borrowers')->where(array('loan_id' => $loan_id, 'td_status <' => 1))->get()->row_array();
        if($check_td_){
            $this->db->where('loan_id', $loan_id)->update('loan_master', array('is_td_ng' => 1));
        }else{
            $this->db->where('loan_id', $loan_id)->update('loan_master', array('is_td_ng' => -1));
        }
        $this->db->trans_complete();//------结束事务
        if ($this->db->trans_status() === FALSE) {
            return $this->fun_fail('保存失败!');
        } else {
            return $this->fun_success('保存成功!');
        }

    }

    public function edit_appointment_date4admin($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;

        $appointment_date_ = $this->input->post('appointment_date');
        if(!$appointment_date_){
            return $this->fun_fail('面签时间不可为空!');
        }

        $this->db->update('loan_master', array('modify_time' => time(), 'appointment_date' => $appointment_date_));
        return $this->fun_success('操作成功!');

    }

    //面签 修改赎楼基本信息
    public function edit_loan_info4admin($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $update_ = array(
            'modify_time' => time(),
            'loan_money' => trim($this->input->post('loan_money')),
            'old_loan_money' => trim($this->input->post('old_loan_money')) ? trim($this->input->post('old_loan_money')) : null,
            'old_mortgage_money_one' => trim($this->input->post('old_mortgage_money_one')) ? trim($this->input->post('old_mortgage_money_one')) : null,
            'old_mortgage_money_two' => trim($this->input->post('old_mortgage_money_two')) ? trim($this->input->post('old_mortgage_money_two')) : null,
            'old_mortgage_bank_one' => trim($this->input->post('old_mortgage_bank_one')) ? trim($this->input->post('old_mortgage_bank_one')) : null,
            'old_mortgage_bank_two' => trim($this->input->post('old_mortgage_bank_two')) ? trim($this->input->post('old_mortgage_bank_two')) : null,
            'houses_price' => trim($this->input->post('houses_price')) ? trim($this->input->post('houses_price')) : null,
            'buyer_loan' => trim($this->input->post('buyer_loan')) ? trim($this->input->post('buyer_loan')) : null
        );

        //先验证关键数据是否有效
        if(!$update_['loan_money'] || $update_['loan_money'] <= 0)
            return $this->fun_fail('借款金额不能为空!');
        if(!$update_['old_loan_money'])
            return $this->fun_fail('老贷金额不能为空!');
        if(!$update_['old_mortgage_bank_one'])
            return $this->fun_fail('老贷一抵机构名称 不能为空!');
        if(!$update_['old_mortgage_money_one'])
            return $this->fun_fail('老贷一抵金额 不能为空!');
        if(!$update_['houses_price'] || $update_['loan_money'] <= 0)
            return $this->fun_fail('房屋总价 不能为空!');
        if(!$update_['buyer_loan']  || $update_['loan_money'] <= 0)
            return $this->fun_fail('买方贷款金额 不能为空!');

        $this->db->where(array(
            'loan_id' => $loan_id,
            'status' => 1,
            'flag' => 1,
            'mx_admin_id' => $admin_id
        ))->update('loan_master', $update_);
        return $this->fun_success('操作成功');
    }

    public function handle_loan_mx($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, 1);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $action_type_= $this->input->post('action_type');
        switch($action_type_){
            case 'pass':
                $pass_data_ = array(
                    'ht_id' => $this->input->post('ht_id'),
                    'jj_price' => $this->input->post('jj_price') ? $this->input->post('jj_price') : 0,
                    'mx_time' => time(),
                    'mx_remark' => $this->input->post('mx_remark'),
                    'status' => 2
                );
                if(!$pass_data_['ht_id'])
                    return $this->fun_fail('请选择合同版本!');
                if($pass_data_['jj_price'] < 0)
                    return $this->fun_fail('请输入居间服务费!');
                if(!$pass_data_['mx_remark'])
                    return $this->fun_fail('请填写面签意见!');
                $ht_info_ = $this->readByID('contract', 'ht_id', $pass_data_['ht_id']);
                if(!$ht_info_ || $ht_info_['status'] != 1)
                    return $this->fun_fail('请选择有效合同版本!');
                $pass_data_['ht_name'] = $ht_info_['ht_name'];
                $pass_data_['fk_admin_id'] = $this->get_role_admin_id(2);
                if($pass_data_['fk_admin_id'] == -1)
                    return $this->fun_fail('缺少有效的风控人员,请联系技术部!');
                $this->db->where('loan_id', $loan_id)->update('loan_master', $pass_data_);
                break;
            case 'ww':
                $ww_data_ = array(
                    'order_type' => -1,
                    'flag' => -1,
                    'ww_time' => time(),
                    'mx_time' => time(),
                    'mx_remark' => $this->input->post('mx_remark')
                );
                if(!$ww_data_['mx_remark'])
                    return $this->fun_fail('请填写面签意见!');
                $this->db->where('loan_id', $loan_id)->update('loan_master', $ww_data_);
                break;
            case 'cancel':
                $cancel_data_ = array(
                    'flag' => -1,
                    'mx_time' => time(),
                    'mx_remark' => $this->input->post('mx_remark')
                );
                if(!$cancel_data_['mx_remark'])
                    return $this->fun_fail('请填写面签意见!');
                $this->db->where('loan_id', $loan_id)->update('loan_master', $cancel_data_);
                break;
            default:
                return $this->fun_fail('请求错误!');
        }

        return $this->fun_success('操作成功!');
    }

    public function handle_loan_fk($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, 2);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $action_type_= $this->input->post('action_type');
        switch($action_type_){
            case 'pass':
                $pass_data_ = array(
                    'fk_time' => time(),
                    'fk_remark' => $this->input->post('fk_remark'),
                    'status' => 3
                );
                if(!$pass_data_['fk_remark'])
                    return $this->fun_fail('请填写风控意见!');
                $this->db->where('loan_id', $loan_id)->update('loan_master', $pass_data_);
                break;
            case 'ww':
                $ww_data_ = array(
                    'order_type' => -1,
                    'flag' => -1,
                    'ww_time' => time(),
                    'fk_time' => time(),
                    'fk_remark' => $this->input->post('fk_remark')
                );
                if(!$ww_data_['fk_remark'])
                    return $this->fun_fail('请填写风控意见!');
                $this->db->where('loan_id', $loan_id)->update('loan_master', $ww_data_);
                break;
            case 'cancel':
                $cancel_data_ = array(
                    'flag' => -1,
                    'fk_time' => time(),
                    'fk_remark' => $this->input->post('fk_remark')
                );
                if(!$cancel_data_['fk_remark'])
                    return $this->fun_fail('请填写风控意见!');
                $this->db->where('loan_id', $loan_id)->update('loan_master', $cancel_data_);
                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        return $this->fun_success('操作成功!');
    }

    public function handle_loan_zs($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, 3);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $action_type_= $this->input->post('action_type');
        switch($action_type_){
            case 'pass':
                $pass_data_ = array(
                    'zs_time' => time(),
                    'zs_remark' => $this->input->post('zs_remark'),
                    'status' => 4
                );
                if(!$pass_data_['zs_remark'])
                    return $this->fun_fail('请填写终审意见!');
                $pass_data_['qz_admin_id'] = $this->get_role_admin_id(3);
                if($pass_data_['qz_admin_id'] == -1)
                    return $this->fun_fail('缺少有效的权证人员,请联系技术部!');
                $this->db->where('loan_id', $loan_id)->update('loan_master', $pass_data_);
                break;
            case 'ww':
                $ww_data_ = array(
                    'order_type' => -1,
                    'flag' => -1,
                    'ww_time' => time(),
                    'zs_time' => time(),
                    'zs_remark' => $this->input->post('zs_remark')
                );
                if(!$ww_data_['zs_remark'])
                    return $this->fun_fail('请填写终审意见!');
                $this->db->where('loan_id', $loan_id)->update('loan_master', $ww_data_);
                break;
            case 'cancel':
                $cancel_data_ = array(
                    'flag' => -1,
                    'zs_time' => time(),
                    'zs_remark' => $this->input->post('zs_remark')
                );
                if(!$cancel_data_['zs_remark'])
                    return $this->fun_fail('请填写终审意见!');
                $this->db->where('loan_id', $loan_id)->update('loan_master', $cancel_data_);
                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        return $this->fun_success('操作成功!');
    }

    //赎楼操作权限验证
    private function check_role4admin($admin_id, $loan_id = '', $check_status = 0){
        if(!$loan_id){
            return $this->fun_fail('缺少参数!');
        }
        $loan_info_ = $this->readByID('loan_master', 'loan_id', $loan_id);
        if(!$loan_info_){
            return $this->fun_fail('信息不存在!');
        }
        if($loan_info_['flag'] != 1)
            return $this->fun_fail('单据不在进行中!');
        //$check_status 判断赎楼是否在指定状态
        if($check_status != 0 && $check_status != $loan_info_['status'])
            return $this->fun_fail('单据状态不可操作!');
        $admin_info_ = $this->readByID('admin', 'admin_id', $admin_id);
        switch($loan_info_['status']) {
            case 1:
                //面签权限
                if ($loan_info_['mx_admin_id'] != $admin_id && $admin_info_['role_id'] != 1)
                    return $this->fun_fail('您无权限操作此单!');
                break;
            case 2:
                //风控初审权限
                if ($loan_info_['fk_admin_id'] != $admin_id && $admin_info_['role_id'] != 2)
                    return $this->fun_fail('您无权限操作此单!');
                break;
            case 3:
                //风控终审权限
                if($admin_info_['role_id'] != 5)
                    return $this->fun_fail('您无权限操作此单!');
                break;
            default:
                return $this->fun_fail('单据状态不可操作!');
        }

        return $this->fun_success('可操作!');
    }

    //赎楼申请单列表 管理员端, 面签经理
    public function loan_list4mx($admin_id){
        $where_ = array('a.mx_admin_id' => $admin_id);
        $order_1 = 'a.create_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    //赎楼申请单列表 管理员端, 风控经理
    public function loan_list4fk($admin_id){
        $where_ = array('a.fk_admin_id' => $admin_id);
        $order_1 = 'a.mx_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    //赎楼申请单列表 管理员端, 终审
    public function loan_list4qz($admin_id){
        $where_ = array('a.qz_admin_id' => $admin_id);
        $order_1 = 'a.zs_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }
}