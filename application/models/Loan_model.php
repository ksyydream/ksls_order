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
            'other_brand' => $user_info['other_brand'],
            'temp_rel_name' => $user_info['rel_name'],
            'temp_shop_name' => $user_info['shop_name'],
            'modify_time' => time(),
            'create_time' => time(),
            'loan_money' => trim($this->input->post('loan_money')),
            'appointment_date' => trim($this->input->post('appointment_date')),
            //'old_loan_money' => trim($this->input->post('old_loan_money')) ? trim($this->input->post('old_loan_money')) : null,
            //'old_mortgage_money_one' => trim($this->input->post('old_mortgage_money_one')) ? trim($this->input->post('old_mortgage_money_one')) : null,
            //'old_mortgage_money_two' => trim($this->input->post('old_mortgage_money_two')) ? trim($this->input->post('old_mortgage_money_two')) : null,
            //'old_mortgage_bank_one' => trim($this->input->post('old_mortgage_bank_one')) ? trim($this->input->post('old_mortgage_bank_one')) : null,
            //'old_mortgage_bank_two' => trim($this->input->post('old_mortgage_bank_two')) ? trim($this->input->post('old_mortgage_bank_two')) : null,
            'houses_price' => trim($this->input->post('houses_price')) ? trim($this->input->post('houses_price')) : null,
            'buyer_loan' => trim($this->input->post('buyer_loan')) ? trim($this->input->post('buyer_loan')) : null,
            'still_pay_back' => trim($this->input->post('still_pay_back')) ? trim($this->input->post('still_pay_back')) : null,
            'mortgage_agency' => trim($this->input->post('mortgage_agency')) ? trim($this->input->post('mortgage_agency')) : null,
            'buyer_mortgage_bank' => trim($this->input->post('buyer_mortgage_bank')) ? trim($this->input->post('buyer_mortgage_bank')) : null,

		);
        if($user_info['store_id'])
            $data['store_id'] = $user_info['store_id'];
        //先验证关键数据是否有效
        if(!$data['loan_money'] || $data['loan_money'] <= 0){
            return $this->fun_fail('借款金额不能为空!');
        }


        if(!$data['still_pay_back'])
            return $this->fun_fail('需还抵押贷款金额不能为空!');
        if(!$data['mortgage_agency'])
            return $this->fun_fail('抵押机构不能为空!');
        if(!$data['buyer_mortgage_bank'])
            return $this->fun_fail('买方按揭机构不能为空!');

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
        return $this->fun_success('操作成功',array('loan_id' => $loan_id));
	}



    //赎楼业务列表 私有 共用方法
    private function loan_list($where, $order_1 = 'a.create_time', $order_2 = 'desc'){
        $res = array();
        $data['limit'] = $this->mini_limit;//每页显示多少调数据
        $data['keyword'] = $this->input->post('keyword')?trim($this->input->post('keyword')):null;
        $data['brand_id'] = $this->input->post('brand_id')?trim($this->input->post('brand_id')):null;
        $data['store_id'] = $this->input->post('store_id')?trim($this->input->post('store_id')):null;
        $data['user_id'] = $this->input->post('user_id')?trim($this->input->post('user_id')):null;
        $data['flag'] = $this->input->post('flag') ? trim($this->input->post('flag')) : null; //默认查进行中 取消默认
        $data['status'] = $this->input->post('status') ? trim($this->input->post('status')) : null;
        $data['is_err'] = $this->input->post('is_err') ? trim($this->input->post('is_err')) : null;

        $page = $this->input->post('page')?trim($this->input->post('page')):1;
        $this->db->select('count(DISTINCT a.loan_id) num');
        $this->db->from('loan_master a');
        $this->db->join('loan_borrowers b', 'a.loan_id = b.loan_id', 'left');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('b.borrower_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['is_err']){
            $this->db->where('a.is_err', $data['is_err']);
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
        if($data['store_id']){
            $this->db->where('a.store_id', $data['store_id']);
        }
        $num = $this->db->get()->row();
        $res['total_rows'] = $num->num;
        $res['total_page'] = ceil($res['total_rows'] / $data['limit']);
        $this->db->select("a.loan_id,a.work_no,a.loan_money,a.is_td_ng,a.order_type,a.is_err,a.need_mx,a.status,a.flag,a.other_brand,
        u.rel_name handle_name,u.mobile handle_mobile,
        u1.rel_name create_name,u1.mobile create_mobile,
        DATE_FORMAT(a.appointment_date,'%Y-%m-%d') appointment_date_handle_,
        DATE_FORMAT(a.redeem_date,'%Y-%m-%d') redeem_date_handle_,
         FROM_UNIXTIME(a.err_time) err_date_,
        FROM_UNIXTIME(a.ww_time) ww_date_,
        FROM_UNIXTIME(a.mx_time) mx_date_,
        FROM_UNIXTIME(a.fk_time) fk_date_,
        FROM_UNIXTIME(a.zs_time) zs_date_,
        FROM_UNIXTIME(a.wq_time) wq_date_,
        FROM_UNIXTIME(a.tg_time) tg_date_,
        FROM_UNIXTIME(a.nj_time) nj_date_,
        FROM_UNIXTIME(a.make_loan_time) make_loan_date_,
        FROM_UNIXTIME(a.gh_time) gh_date_,
        FROM_UNIXTIME(a.returned_money_time) returned_money_date_,
        mx.admin_name mx_name,mx.phone mx_phone,
        fk.admin_name fk_name,fk.phone fk_phone,
        qz.admin_name qz_name,qz.phone qz_phone,
        fc.admin_name fc_name,fc.phone fc_phone,
         bd.brand_name,FROM_UNIXTIME(a.create_time) loan_cdate,a.appointment_date,a.store_id,a.store_name");
        $this->db->from('loan_master a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('brand_stores s','a.store_id = s.store_id','left');
        $this->db->join('loan_borrowers b', 'a.loan_id = b.loan_id', 'left');
        $this->db->join('admin mx', 'a.mx_admin_id = mx.admin_id', 'left');
        $this->db->join('admin fk', 'a.fk_admin_id = fk.admin_id', 'left');
        $this->db->join('admin qz', 'a.qz_admin_id = qz.admin_id', 'left');
        $this->db->join('admin fc', 'a.fc_admin_id = fc.admin_id', 'left');
        $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('b.borrower_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['is_err']){
            $this->db->where('a.is_err', $data['is_err']);
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
        if($data['store_id']){
            $this->db->where('a.store_id', $data['store_id']);
        }
        $this->db->order_by($order_1, $order_2);
        $this->db->order_by('a.loan_id', 'desc'); //给个默认排序
        $this->db->group_by('a.loan_id');
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $res['res_list'] = $this->db->get()->result_array();
        foreach($res['res_list'] as $k => $v){
            $this->db->select('borrower_name,borrower_phone');
            $this->db->from('loan_borrowers');
            $this->db->where('loan_id', $v['loan_id']);
            $res['res_list'][$k]['borrowers_list'] = $this->db->get()->result_array();

            $this->db->select("s.id")->from('supervise s');
            $this->db->join('loan_supervise ls','s.id = ls.option_id and ls.loan_id = '. $v['loan_id'],'left');
            $this->db->where('s.status', 1);
            $this->db->where('ls.id', null);
            $check_supervise_ = $this->db->order_by('s.id','asc')->get()->row_array();
            if($check_supervise_ && $v['flag'] == 1){
                $res['res_list'][$k]['need_supervise_'] = 1;
            }else{
                $res['res_list'][$k]['need_supervise_'] = -1;
            }
            if($res['res_list'][$k]['brand_name'] == ''){
                $res['res_list'][$k]['brand_name'] = '其他(' .$res['res_list'][$k]['other_brand'] . ')';
            }
        }
        return $res;
    }

    //赎楼业务详情
    public function loan_info($loan_id, $select = "*"){
        $select = "a.*,FROM_UNIXTIME(a.create_time) loan_cdate,
        DATE_FORMAT(a.appointment_date,'%Y-%m-%d') appointment_date_handle_,
        DATE_FORMAT(a.redeem_date,'%Y-%m-%d') redeem_date_handle_,
         FROM_UNIXTIME(a.err_time) err_date_,
        FROM_UNIXTIME(a.ww_time) ww_date_,
        FROM_UNIXTIME(a.mx_time) mx_date_,
        FROM_UNIXTIME(a.fk_time) fk_date_,
        FROM_UNIXTIME(a.zs_time) zs_date_,
        FROM_UNIXTIME(a.wq_time) wq_date_,
        FROM_UNIXTIME(a.tg_time) tg_date_,
        FROM_UNIXTIME(a.nj_time) nj_date_,
        FROM_UNIXTIME(a.make_loan_time) make_loan_date_,
        FROM_UNIXTIME(a.gh_time) gh_date_,
        FROM_UNIXTIME(a.returned_money_time) returned_money_date_,
        u.rel_name handle_name,u.mobile handle_mobile,u.invite,
        u1.rel_name create_name,u1.mobile create_mobile,
        mx.admin_name mx_name,mx.phone mx_phone,
        fk.admin_name fk_name,fk.phone fk_phone,
        qz.admin_name qz_name,qz.phone qz_phone,
        fc.admin_name fc_name,fc.phone fc_phone,
        bd.brand_name";
        $this->db->select($select)->from('loan_master a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('admin mx', 'a.mx_admin_id = mx.admin_id', 'left');
        $this->db->join('admin fk', 'a.fk_admin_id = fk.admin_id', 'left');
         $this->db->join('admin qz', 'a.qz_admin_id = qz.admin_id', 'left');
        $this->db->join('admin fc', 'a.fc_admin_id = fc.admin_id', 'left');
        $loan_info = $this->db->where('a.loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('未找到相关订单!');
        $this->db->select('*');
        $this->db->from('loan_borrowers');
        $this->db->where('loan_id', $loan_id);
        $loan_info['borrowers_list'] = $this->db->get()->result_array();
        $this->db->select("s.id")->from('supervise s');
        $this->db->join('loan_supervise ls','s.id = ls.option_id and ls.loan_id = '. $loan_id,'left');
        $this->db->where('s.status', 1);
        $this->db->where('ls.id', null);
        $check_supervise_ = $this->db->order_by('s.id','asc')->get()->row_array();
        if($check_supervise_ && $loan_id == 1){
            $loan_info['need_supervise_'] = 1;
        }else{
            $loan_info['need_supervise_'] = -1;
        }
        if($loan_info['brand_name'] == ''){
            $loan_info['brand_name'] = '其他(' .$loan_info['other_brand'] . ')';
        }
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

    private function loan_count($where){
        $mx_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 1, 'status' => 1))->where($where)->get()->row();
        $fk_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 1, 'status' => 2))->where($where)->get()->row();
        $zs_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 1, 'status' => 3))->where($where)->get()->row();
        $wq_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 1, 'status' => 4))->where($where)->get()->row();
        $tg_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 1, 'status' => 5))->where($where)->get()->row();
        $nj_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 1, 'status' => 6))->where($where)->get()->row();
        $make_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 1, 'status' => 7))->where($where)->get()->row();
        $gh_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 1, 'status' => 8))->where($where)->get()->row();
        $returned_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 1, 'status' => 9))->where($where)->get()->row();
        //$err_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => -1))->where($where)->get()->row();
        //$success_num = $this->db->select('count(1) num')->from('loan_master')->where(array('flag' => 2))->where($where)->get()->row();
        $result = array(
            'mx_num' => $mx_num->num,
            'fk_num' => $fk_num->num,
            'zs_num' => $zs_num->num,
            'wq_num' => $wq_num->num,
            'tg_num' => $tg_num->num,
            'nj_num' => $nj_num->num,
            'make_num' => $make_num->num,
            'gh_num' => $gh_num->num,
            'returned_num' => $returned_num->num,
            //'err_num' => $err_num->num,
            //'success_num' => $success_num->num,
        );
        return $this->fun_success('获取成功!', $result);
    }

    //管理员审核操作记录
    private function save_loan_log4admin($loan_id, $admin_id, $action_type){
        $check_status_ = $this->db->select('status')->from('loan_master')->where('loan_id', $loan_id)->get()->row_array();
        if($check_status_){
            $insert_= array(
                'admin_id' => $admin_id,
                'loan_id' => $loan_id,
                'action_type' => $action_type,
                'status' => $check_status_['status'],
                'cdate' => time()
            );
            $this->db->insert('loan_log', $insert_);
        }

    }

    /**
     *********************************************************************************************
     * 以下代码为大客户端 专用
     *********************************************************************************************
     */
//赎楼申请单列表 大客户端
    public function loan_list4brand($brand_id){
        $where_ = array('a.brand_id' => $brand_id);
        $order_1 = 'a.create_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    public function loan_count4brand($brand_id){
        $where = array('brand_id' => $brand_id);
        $rs = $this->loan_count($where);
        return $rs;
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

    public function loan_count4user($user_id){
        $where = array('user_id' => $user_id);
        $rs = $this->loan_count($where);
        return $rs;
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
        $check_role4admin_ = $this->check_role4admin($admin_id, $borrower_info_['loan_id'], array(1,2,3));
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
        $check_role4admin_ = $this->check_role4admin($admin_id, $borrower_info_['loan_id'], array(1,2,3));
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
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, array(1,2,3));
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
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, array(1));
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;

        $appointment_date_ = $this->input->post('appointment_date');
        if(!$appointment_date_){
            return $this->fun_fail('面签时间不可为空!');
        }

        $this->db->update('loan_master', array('modify_time' => time(), 'appointment_date' => $appointment_date_));
        return $this->fun_success('操作成功!');

    }

    //面签,风控,终审 修改赎楼基本信息
    public function edit_loan_info4admin($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, array(1,2,3));
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $update_ = array(
            'modify_time' => time(),
            'loan_money' => trim($this->input->post('loan_money')),
            //'old_loan_money' => trim($this->input->post('old_loan_money')) ? trim($this->input->post('old_loan_money')) : null,
            //'old_mortgage_money_one' => trim($this->input->post('old_mortgage_money_one')) ? trim($this->input->post('old_mortgage_money_one')) : null,
            //'old_mortgage_money_two' => trim($this->input->post('old_mortgage_money_two')) ? trim($this->input->post('old_mortgage_money_two')) : null,
            //'old_mortgage_bank_one' => trim($this->input->post('old_mortgage_bank_one')) ? trim($this->input->post('old_mortgage_bank_one')) : null,
            //'old_mortgage_bank_two' => trim($this->input->post('old_mortgage_bank_two')) ? trim($this->input->post('old_mortgage_bank_two')) : null,
            'houses_price' => trim($this->input->post('houses_price')) ? trim($this->input->post('houses_price')) : null,
            'buyer_loan' => trim($this->input->post('buyer_loan')) ? trim($this->input->post('buyer_loan')) : null,
            'still_pay_back' => trim($this->input->post('still_pay_back')) ? trim($this->input->post('still_pay_back')) : null,
            'mortgage_agency' => trim($this->input->post('mortgage_agency')) ? trim($this->input->post('mortgage_agency')) : null,
            'buyer_mortgage_bank' => trim($this->input->post('buyer_mortgage_bank')) ? trim($this->input->post('buyer_mortgage_bank')) : null,
        );

        //先验证关键数据是否有效
        if(!$update_['loan_money'] || $update_['loan_money'] <= 0)
            return $this->fun_fail('借款金额不能为空!');

        if(!$update_['still_pay_back'])
            return $this->fun_fail('需还抵押贷款金额不能为空!');
        if(!$update_['mortgage_agency'])
            return $this->fun_fail('抵押机构不能为空!');
        if(!$update_['buyer_mortgage_bank'])
            return $this->fun_fail('买方按揭机构不能为空!');

        if(!$update_['houses_price'] || $update_['loan_money'] <= 0)
            return $this->fun_fail('房屋总价 不能为空!');
        if(!$update_['buyer_loan']  || $update_['loan_money'] <= 0)
            return $this->fun_fail('买方贷款金额 不能为空!');

        $this->db->where(array(
            'loan_id' => $loan_id,
            'flag' => 1,
        ))->update('loan_master', $update_);
        return $this->fun_success('操作成功');
    }

    //面签审核
    public function handle_loan_mx($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, array(1));
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $action_type_= $this->input->post('action_type');
        $update_data_ = array();
        switch($action_type_) {
            case 'pass':
            case 'pass_need':
                $update_data_ = array(
                    'mx_time' => time(),
                    'mx_remark' => $this->input->post('mx_remark'),
                    'status' => 2
                );
                if ($action_type_ == 'pass_need') {
                    $update_data_['need_mx'] = 1;
                }
                $update_data_['ht_id'] = $this->input->post('ht_id');
                $update_data_['jj_price'] = $this->input->post('jj_price') ? $this->input->post('jj_price') : 0;
                if (!$update_data_['ht_id'])
                    return $this->fun_fail('请选择合同版本!');
                if ($update_data_['jj_price'] < 0)
                    return $this->fun_fail('请输入居间服务费!');
                $ht_info_ = $this->readByID('contract', 'ht_id', $update_data_['ht_id']);
                if (!$ht_info_ || $ht_info_['status'] != 1)
                    return $this->fun_fail('请选择有效合同版本!');
                $update_data_['ht_name'] = $ht_info_['ht_name'];
                $check_fk_ = $this->db->select('fk_admin_id')->from('loan_master')->where('loan_id', $loan_id)->get()->row_array();
                if ($check_fk_ && $check_fk_['fk_admin_id'] > 0) {

                } else {
                    $update_data_['fk_admin_id'] = $this->get_role_admin_id(2);
                    if ($update_data_['fk_admin_id'] == -1)
                        return $this->fun_fail('缺少有效的风控人员,请联系技术部!');
                }

                break;
            case 'ww':
                $update_data_ = array(
                    'order_type' => 2,
                    'flag' => -1,
                    'ww_time' => time(),
                    'mx_time' => time(),
                    'ww_admin_id' => $admin_id,
                    'ww_remark' => $this->input->post('mx_remark')
                );
                //if(!$update_data_['ww_remark'])
                //return $this->fun_fail('请填写委外意见!');
                break;
            case 'cancel':
                $update_data_ = array(
                    'flag' => -1,
                    'mx_time' => time(),
                    'err_remark' => $this->input->post('mx_remark'),
                    'err_admin_id' => $admin_id,
                    'err_time' => time()
                );
                //if(!$update_data_['mx_remark'])
                //return $this->fun_fail('请填写面签意见!');
                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        $this->save_loan_log4admin($loan_id, $admin_id, $action_type_);
        $this->db->where('loan_id', $loan_id)->update('loan_master', $update_data_);
        return $this->fun_success('操作成功!');
    }

    //风控审核
    public function handle_loan_fk($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, array(2));
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $update_data_ = array();
        $action_type_= $this->input->post('action_type');
        switch($action_type_){
            case 'pass':
                $update_data_ = array(
                    'fk_time' => time(),
                    'fk_remark' => $this->input->post('fk_remark'),
                    'status' => 3
                );
                //if(!$update_data_['fk_remark'])
                    //return $this->fun_fail('请填写风控意见!');

                break;
            case 'ww':
                $update_data_ = array(
                    'order_type' => 2,
                    'flag' => -1,
                    'ww_time' => time(),
                    'fk_time' => time(),
                    'ww_admin_id' => $admin_id,
                    'ww_remark' => $this->input->post('mx_remark')
                );
                //if(!$update_data_['ww_remark'])
                    //return $this->fun_fail('请填写风控意见!');
                break;
            case 'cancel':
                $update_data_ = array(
                    'flag' => -1,
                    'fk_time' => time(),
                    'err_remark' => $this->input->post('fk_remark'),
                    'err_admin_id' => $admin_id,
                    'err_time' => time()
                );
                //if(!$cancel_data_['err_remark'])
                    //return $this->fun_fail('请填写风控意见!');

                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        $this->save_loan_log4admin($loan_id, $admin_id, $action_type_);
        $this->db->where('loan_id', $loan_id)->update('loan_master', $update_data_);
        return $this->fun_success('操作成功!');
    }

    //终审审核
    public function handle_loan_zs($admin_id){
        $loan_id = $this->input->post('loan_id');
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, array(3));
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $update_data_ = array();
        $action_type_= $this->input->post('action_type');
        switch($action_type_){
            case 'pass':
                $update_data_ = array(
                    'zs_time' => time(),
                    'zs_remark' => $this->input->post('zs_remark'),
                    'status' => 4
                );
                //if(!$update_data_['zs_remark'])
                    //return $this->fun_fail('请填写终审意见!');
                //如果已分配权证(银行)经理就不再做自动分配
                $check_qz_ = $this->db->select('qz_admin_id')->from('loan_master')->where('loan_id', $loan_id)->get()->row_array();
                if($check_qz_ && $check_qz_['qz_admin_id'] > 0){

                }else{
                    $update_data_['qz_admin_id'] = $this->get_role_admin_id(3);
                    if($update_data_['qz_admin_id'] == -1)
                        return $this->fun_fail('缺少有效的权证(银行)人员,请联系技术部!');
                }
                break;
            case 'ww':
                $update_data_ = array(
                    'order_type' => 2,
                    'flag' => -1,
                    'ww_time' => time(),
                    'zs_time' => time(),
                    'ww_admin_id' => $admin_id,
                    'ww_remark' => $this->input->post('zs_remark')
                );
                //if(!$update_data_['ww_remark'])
                    //return $this->fun_fail('请填写终审意见!');
                break;
            case 'cancel':
                $update_data_ = array(
                    'flag' => -1,
                    'zs_time' => time(),
                    'err_remark' => $this->input->post('zs_remark'),
                    'err_admin_id' => $admin_id,
                    'err_time' => time()
                );
                //if(!$update_data_['err_remark'])
                    //return $this->fun_fail('请填写终审意见!');

                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        $this->save_loan_log4admin($loan_id, $admin_id, $action_type_);
        $this->db->where('loan_id', $loan_id)->update('loan_master', $update_data_);
        return $this->fun_success('操作成功!');
    }

    //权证(银行)审核
    public function handle_loan_qz($admin_id){
        $loan_id = $this->input->post('loan_id');
        $action_type_= $this->input->post('action_type');
        $check_status = array(-99);
        switch($action_type_){
            case 'wq':
                $check_status = array(4);
                break;
            case 'tg':
                $check_status = array(5);
                break;
            case 'nj':
                $check_status = array(6);
                break;
            case 'err':
                $check_status = array(4,5,6);
                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, $check_status);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $update_data_ = array();
        switch($action_type_){
            case 'wq':
                $update_data_ = array(
                    'wq_time' => time(),
                    'has_wq' => 1,
                    'status' => 5
                );
                break;
            case 'tg':
                //需要写入 托管的 借款 回款 银行卡号
                $update_data_ = array(
                    'tg_time' => time(),
                    'make_loan_bank' => trim($this->input->post('make_loan_bank')) ? trim($this->input->post('make_loan_bank')): '',
                    'make_loan_card' => trim($this->input->post('make_loan_card')) ? trim($this->input->post('make_loan_card')): '',
                    'returned_money_bank' => trim($this->input->post('returned_money_bank')) ? trim($this->input->post('returned_money_bank')): '',
                    'returned_money_card' => trim($this->input->post('returned_money_card')) ? trim($this->input->post('returned_money_card')): '',
                    'has_tg' => 1,
                    'status' => 6
                );
                if(!$update_data_['make_loan_bank'])
                    return $this->fun_fail('请填写借款银行!');
                if(!$update_data_['make_loan_card'])
                    return $this->fun_fail('请填写借款银行卡!');
                if(!$update_data_['returned_money_bank'])
                    return $this->fun_fail('请填写回款银行!');
                if(!$update_data_['returned_money_card'])
                    return $this->fun_fail('请填写回款银行卡!');

                break;
            case 'nj':
                //需要写入 预约放款时间
                $update_data_ = array(
                    'nj_time' => time(),
                    'redeem_date' => trim($this->input->post('redeem_date')) ? trim($this->input->post('redeem_date')): '',
                    'has_nj' => 1,
                    'status' => 7
                );
                if(!$update_data_['redeem_date'])
                    return $this->fun_fail('请填写预约赎楼时间!');
                break;
            case 'err':
                $update_data_ = array(
                    'err_remark' => $this->input->post('err_remark'),
                    'is_err' => 1,
                    'flag' => -1,
                    'err_admin_id' => $admin_id,
                    'err_time' => time()
                );
                if(!$update_data_['err_remark'])
                    return $this->fun_fail('请填写异常原因!');
                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        $this->save_loan_log4admin($loan_id, $admin_id, $action_type_);
        $this->db->where('loan_id', $loan_id)->update('loan_master', $update_data_);
        return $this->fun_success('操作成功!');
    }

    //权证(交易中心)审核
    public function handle_loan_fc($admin_id){
        $loan_id = $this->input->post('loan_id');
        $action_type_= $this->input->post('action_type');
        $check_status = array(-99);
        switch($action_type_){
            case 'gh':
                $check_status = array(8);
                break;
            case 'err':
                $check_status = array(8);
                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, $check_status);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $update_data_ = array();
        switch($action_type_){
            case 'gh':
                $update_data_ = array(
                    'gh_time' => time(),
                    'has_gh' => 1,
                    'status' => 9
                );
                break;
            case 'err':
                $update_data_ = array(
                    'err_remark' => $this->input->post('err_remark'),
                    'is_err' => 1,
                    'flag' => -1,
                    'err_admin_id' => $admin_id,
                    'err_time' => time()
                );
                if(!$update_data_['err_remark'])
                    return $this->fun_fail('请填写异常原因!');
                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        $this->save_loan_log4admin($loan_id, $admin_id, $action_type_);
        $this->db->where('loan_id', $loan_id)->update('loan_master', $update_data_);
        return $this->fun_success('操作成功!');
    }

    //财务审核
    public function handle_loan_cw($admin_id){
        $loan_id = $this->input->post('loan_id');
        $action_type_= $this->input->post('action_type');
        $check_status = array(-99);
        switch($action_type_){
            case 'make':
                $check_status = array(7);
                break;
            case 'returned':
                $check_status = array(9);
                break;
            case 'err':
                $check_status = array(7,9);
                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        $check_role4admin_ = $this->check_role4admin($admin_id, $loan_id, $check_status);
        if($check_role4admin_['status'] != 1)
            return $check_role4admin_;
        $update_data_ = array();
        switch($action_type_){
            case 'make':
                $update_data_ = array(
                    'make_loan_time' => time(),
                    'has_make_loan' => 1,
                    'status' => 8
                );
                //如果已分配权证(交易中心)经理就不再做自动分配
                $check_fc_ = $this->db->select('fc_admin_id')->from('loan_master')->where('loan_id', $loan_id)->get()->row_array();
                if($check_fc_ && $check_fc_['fc_admin_id'] > 0){

                }else{
                    $update_data_['fc_admin_id'] = $this->get_role_admin_id(7);
                    if($update_data_['fc_admin_id'] == -1)
                        return $this->fun_fail('缺少有效的权证(交易中心)人员,请联系技术部!');
                }
                break;
            case 'returned':
                $update_data_ = array(
                    'returned_money_time' => time(),
                    'has_returned_money' => 1,
                    'status' => 10,
                    'flag' => 2
                );
                break;
            case 'err':
                $update_data_ = array(
                    'err_remark' => $this->input->post('err_remark'),
                    'is_err' => 1,
                    'flag' => -1,
                    'err_admin_id' => $admin_id,
                    'err_time' => time()
                );
                if(!$update_data_['err_remark'])
                    return $this->fun_fail('请填写异常原因!');
                break;
            default:
                return $this->fun_fail('请求错误!');
        }
        $this->save_loan_log4admin($loan_id, $admin_id, $action_type_);
        $this->db->where('loan_id', $loan_id)->update('loan_master', $update_data_);
        return $this->fun_success('操作成功!');
    }

    /** 管理员权限 验证 重要*/
    //赎楼操作权限验证
    private function check_role4admin($admin_id, $loan_id = '', $check_status = array()){
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
        if(!is_array($check_status))
            return $this->fun_fail('权限验证异常!');
        if($check_status && !in_array($loan_info_['status'], $check_status))
            return $this->fun_fail('单据状态不可操作!');
        $admin_info_ = $this->readByID('admin', 'admin_id', $admin_id);
        switch($loan_info_['status']) {
            case 1:
                //面签权限
                if ($loan_info_['mx_admin_id'] != $admin_id && $admin_info_['role_id'] != 1)
                    return $this->fun_fail('您无权限操作此单!');
                break;
            case 2:
                //风控权限
                if ($loan_info_['fk_admin_id'] != $admin_id && $admin_info_['role_id'] != 2)
                    return $this->fun_fail('您无权限操作此单!');
                break;
            case 3:
                //终审权限
                if($admin_info_['role_id'] != 5)
                    return $this->fun_fail('您无权限操作此单!');
                break;
            case 4:
            case 5:
            case 6:
                //权证(银行)权限
                if($admin_info_['role_id'] != 3)
                    return $this->fun_fail('您无权限操作此单!');
                break;
            case 7:
            case 9:
                //财务权限
                if($admin_info_['role_id'] != 4)
                    return $this->fun_fail('您无权限操作此单!');
                break;
            case 8:
                //权证(交易中心)权限
                if($admin_info_['role_id'] != 7)
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

    //赎楼申请单列表 管理员端, 权证(银行)
    public function loan_list4qz($admin_id){
        $where_ = array('a.qz_admin_id' => $admin_id);
        $order_1 = 'a.zs_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    //赎楼申请单列表 管理员端, 权证(交易中心)
    public function loan_list4fc($admin_id){
        $where_ = array('a.fc_admin_id' => $admin_id);
        $order_1 = 'a.make_loan_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    public function loan_list4zs($admin_id){
        $where_ = array('a.loan_id >' => 0);
        $order_1 = 'a.fk_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    public function loan_list4cw($admin_id){
        $where_ = array('a.loan_id >' => 0);
        $order_1 = 'a.nj_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    public function loan_list4invite($admin_id){
        $where_ = array('u.invite' => $admin_id);
        $order_1 = 'a.create_time';
        $order_2 = 'desc';
        $res_ = $this->loan_list($where_,$order_1,$order_2);
        return $this->fun_success('操作成功', $res_);
    }

    public function loan_count4admin($admin_id, $role_id){
        $where = array('loan_id >' => 0);
        switch($role_id){
            case 1:
                $where = array('mx_admin_id' => $admin_id);
                break;
            case 2:
                $where = array('fk_admin_id' => $admin_id);
                break;
            case 3:
                //权证(银行)
                $where = array('qz_admin_id' => $admin_id);
                break;
            case 4:
                //财务
                break;
            case 5:
                //终审
                break;
            case 7:
                //权证(交易中心)
                $where = array('fc_admin_id' => $admin_id);
                break;
            default:
                return $this->fun_fail("未找到可用数据！");

        }
        $rs = $this->loan_count($where);
        return $rs;
    }

    public function show_loan_supervise(){
        $loan_id = $this->input->post('loan_id');
        if(!$loan_id || $loan_id <= 0)
            return $this->fun_fail('未传入必要信息!');
        $this->db->select("s.*,ls.option_value,ifnull(ls.id,-1) is_check")->from('supervise s');
        $this->db->join('loan_supervise ls','s.id = ls.option_id and ls.loan_id = '. $loan_id,'left');
        $this->db->where('s.status', 1);
        $loan_supervise = $this->db->order_by('s.id','asc')->get()->result_array();
        foreach($loan_supervise as $k => $v) {
            if($loan_supervise[$k]['is_check'] != -1){
                $loan_supervise[$k]['is_check'] = true;
            }else{
                $loan_supervise[$k]['is_check'] = false;
            }
        }
        $data_ = $this->db->select("supervise_remark")->from('loan_master')->where('loan_id', $loan_id)->get()->row_array();
        $rs['list'] = $loan_supervise;
        $rs['supervise_remark'] = $data_ ? $data_['supervise_remark'] : '';
        return $this->fun_success('获取成功!', $rs);

    }

    public function save_loan_supervise($admin_id,$role_id){
        $loan_id = $this->input->post('loan_id');
        $supervise_remark = trim($this->input->post('supervise_remark')) ? trim($this->input->post('supervise_remark')) : '';
        if(!$loan_id || $loan_id <= 0)
            return $this->fun_fail('未传入必要信息!');
        $loan_info_ = $this->readByID('loan_master', 'loan_id', $loan_id);
        if(!$loan_info_){
            return $this->fun_fail('信息不存在!');
        }
        if($role_id != 4)
            return $this->fun_fail('您无权限操作!');
        $supervise_arr = $this->input->post("supervise_arr");
        if(!is_array($supervise_arr)){
            $supervise_arr = json_decode($supervise_arr,true);
        }
        $supervise_list_ = $this->db->select()->from('supervise')->where('status', 1)->get()->result_array();
        $check_super_ = array();
        foreach($supervise_list_ as $k => $v) {
            $check_super_[$v['id']] = $v;
        }
        $insert_arr_ = array();
        foreach($supervise_arr as $k => $v) {
            $s_insert_ = array(
                'option_id' => $v['id'],
                'option_value' => isset($v['option_value']) ? trim($v['option_value']) : '',
                'cdate' => time(),
                'loan_id' => $loan_id,
                'admin_id' => $admin_id
            );
            $is_check_ = isset($v['is_check']) ? $v['is_check'] : false;
            if(!$is_check_){
                if(!$supervise_remark){
                    return $this->fun_fail('存在未选择项时必须填写备注!');
                }else{
                    continue;
                }
            }

            if(isset($check_super_[$v['id']]) && $check_super_[$v['id']]['type'] == 2 && $s_insert_['option_value'] == '')
                return $this->fun_fail($check_super_[$v['id']]['name'] . ' 必须有详细说明');
            $insert_arr_[] = $s_insert_;
        }
        $this->db->where('loan_id', $loan_id)->delete('loan_supervise');
        if($insert_arr_){
            $this->db->insert_batch('loan_supervise', $insert_arr_);
        }
        $this->db->where('loan_id', $loan_id)->update('loan_master', array('supervise_remark' => $supervise_remark));
        return $this->fun_success('保存成功!');


    }

    /**
     *********************************************************************************************
     * 以下代码为PC端 专用, 以防万一 以后小程序也需要
     *********************************************************************************************
     */

    //修改面签经理
    public function mx_admin_change4loan($admin_id,$role_id){
        $loan_id = $this->input->post('loan_id');
        if(!$loan_id || $loan_id <= 0)
            return $this->fun_fail('未传入必要信息!');
        $loan_info = $this->db->select("flag,status")->from("loan_master")->where('loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('申请单不存在!');
        $mx_admin_id = $this->input->post('mx_admin_id');
        if(!$mx_admin_id)
            return $this->fun_fail('未传入面签经理信息!');
        $admin_info = $this->readByID('admin','admin_id', $mx_admin_id);
        if(!$admin_info || $admin_info['status'] != 1 || $admin_info['role_id'] != 1)
            return $this->fun_fail('未选择有效的面签经理!');
        $this->db->where('loan_id', $loan_id)->update('loan_master', array('mx_admin_id' => $mx_admin_id));
        return $this->fun_success('操作成功!');
    }

    //修改风控经理
    public function fk_admin_change4loan($admin_id,$role_id){
        $loan_id = $this->input->post('loan_id');
        if(!$loan_id || $loan_id <= 0)
            return $this->fun_fail('未传入必要信息!');
        $loan_info = $this->db->select("flag,status")->from("loan_master")->where('loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('申请单不存在!');
        $fk_admin_id = $this->input->post('fk_admin_id');
        if(!$fk_admin_id)
            return $this->fun_fail('未传入风控经理信息!');
        $admin_info = $this->readByID('admin','admin_id', $fk_admin_id);
        if(!$admin_info || $admin_info['status'] != 1 || $admin_info['role_id'] != 2)
            return $this->fun_fail('未选择有效的风控经理!');
        $this->db->where('loan_id', $loan_id)->update('loan_master', array('fk_admin_id' => $fk_admin_id));
        return $this->fun_success('操作成功!');
    }

    //修改权证(银行)经理
    public function qz_admin_change4loan($admin_id,$role_id){
        $loan_id = $this->input->post('loan_id');
        if(!$loan_id || $loan_id <= 0)
            return $this->fun_fail('未传入必要信息!');
        $loan_info = $this->db->select("flag,status")->from("loan_master")->where('loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('申请单不存在!');
        $qz_admin_id = $this->input->post('qz_admin_id');
        if(!$qz_admin_id)
            return $this->fun_fail('未传入权证(银行)经理信息!');
        $admin_info = $this->readByID('admin','admin_id', $qz_admin_id);
        if(!$admin_info || $admin_info['status'] != 1 || $admin_info['role_id'] != 3)
            return $this->fun_fail('未选择有效的权证(银行)经理!');
        $this->db->where('loan_id', $loan_id)->update('loan_master', array('qz_admin_id' => $qz_admin_id));
        return $this->fun_success('操作成功!');
    }

    //修改权证(交易中心)经理
    public function fc_admin_change4loan($admin_id,$role_id){
        $loan_id = $this->input->post('loan_id');
        if(!$loan_id || $loan_id <= 0)
            return $this->fun_fail('未传入必要信息!');
        $loan_info = $this->db->select("flag,status")->from("loan_master")->where('loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('申请单不存在!');
        $fc_admin_id = $this->input->post('fc_admin_id');
        if(!$fc_admin_id)
            return $this->fun_fail('未传入权证(交易中心)经理信息!');
        $admin_info = $this->readByID('admin','admin_id', $fc_admin_id);
        if(!$admin_info || $admin_info['status'] != 1 || $admin_info['role_id'] != 7)
            return $this->fun_fail('未选择有效的权证(交易中心)经理!');
        $this->db->where('loan_id', $loan_id)->update('loan_master', array('fc_admin_id' => $fc_admin_id));
        return $this->fun_success('操作成功!');
    }

    //修改工作流节点,在修改时判断上一节点是否完成 且只能修改进行中的赎楼单
    public function status_change4loan($admin_id,$role_id){
        $loan_id = $this->input->post('loan_id');
        if(!$loan_id || $loan_id <= 0)
            return $this->fun_fail('未传入必要信息!');
        $loan_info = $this->db->select("flag,status,fk_admin_id,mx_time,fk_time,zs_time,has_wq,has_tg,has_nj,has_make_loan,has_gh")->from("loan_master")->where('loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('申请单不存在!');
        if($loan_info['flag'] != 1){
            return $this->fun_fail('此赎楼申请单不在进行中,不可修改工作流!');
        }
        $status = $this->input->post('status');
        if(!$status || !in_array($status, array(1,2,3,4,5,6,7,8,9)))
            return $this->fun_fail('未传入合法的工作流!');
        switch($status){
            case 1:
                break;
            case 2:
                if(!$loan_info['mx_time'])
                    return $this->fun_fail('未完成面签审核,不可直接修改为风控!');
                break;
            case 3:
                if(!$loan_info['fk_time'])
                    return $this->fun_fail('未完成风控审核,不可直接修改为终审!');
                break;
            case 4:
                if(!$loan_info['zs_time'])
                    return $this->fun_fail('未完成终审审核,不可直接修改为待网签!');
                break;
            case 5:
                if($loan_info['has_wq'] != 1)
                    return $this->fun_fail('网签未通过,不可直接修改 待托管!');
                break;
            case 6:
                if($loan_info['has_tg'] != 1)
                    return $this->fun_fail('托管未通过,不可直接修改 待按揭放款!');
                break;
            case 7:
                if($loan_info['has_nj'] != 1)
                    return $this->fun_fail('按揭放款未通过,不可直接修改 待赎楼借款放款!');
                break;
            case 8:
                if($loan_info['has_make_loan'] != 1)
                    return $this->fun_fail('赎楼借款放款未通过,不可直接修改 待过户!');
                break;
            case 9:
                if($loan_info['has_gh'] != 1)
                    return $this->fun_fail('过户未通过,不可直接修改 待回款!');
                break;
            default:
                break;

        }
        $this->db->where(array('loan_id' => $loan_id, 'flag' => 1))->update('loan_master', array('status' => $status));
        return $this->fun_success('操作成功!');
    }
}