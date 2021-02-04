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

        $borrowers = $this->input->post("borrowers");
        if(!$borrowers || !is_array($borrowers))
            return $this->fun_fail('借款人不能为空!');
        foreach($borrowers as $k_ => $v_){
            if(trim($v_['borrower_name']) == "")
                return $this->fun_fail('存在借款人姓名为空!');
            if(trim($v_['borrower_phone']) == "")
                return $this->fun_fail('存在借款人电话为空!');
            if(trim($v_['borrower_card']) == "")
                return $this->fun_fail('存在借款人身份证为空!');
        }
        $data = array(
            'user_id' => $user_id,
            'create_user_id' => $user_id,
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
        $this->db->insert('loan_master', $data);
        $loan_id = $this->db->insert_id();
        $borrowers_insert_ = array();
        foreach($borrowers as $k => $v){
            $b_insert_ = array(
                'borrower_name' => $v['borrower_name'],
                'borrower_phone' => $v['phone'],
                'borrower_card' => $v['borrower_card'],
                'loan_id' => $loan_id
            );
            $borrower_td_info_ = $this->get_tongdun_info($v['borrower_name'], $v['borrower_card'], $v['borrower_phone']);
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
                $this->db->where('loan_id', $loan_id)->update('loan_master', array('flag' => -2));
            }

            $borrowers_insert_[] = $b_insert_;
        }
        $this->db->insert_batch('loan_borrowers', $borrowers_insert_);
        return $this->fun_success('操作成功');
	}
}