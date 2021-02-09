<?php
/**
 * Created by PhpStorm.
 * User: yangyang
 * Date: 16/6/3
 * Time: 下午3:22
 */
class Mini_brand_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function check_token($token, $brand_id = 0){
        $brand_info_ = $this->db->select()->from('brand')->where(array('token' => $token))->get()->row_array();
        if(!$brand_info_){
            return array('status' => -100, 'msg' => '未找到登录信息!', "result" => '');
        }
        if(time() - $brand_info_['mini_last_login'] > 60 * 60 * 24 * 30){
            return array('status' => -101, 'msg' => '请登录!', "result" => '');
        }
        if($brand_id != $brand_info_['id']){
            return array('status' => -101, 'msg' => '异常!', "result" => '');
        }
        if($brand_info_['status'] != 1){
            return array('status' => -101, 'msg' => '账号异常!', "result" => '');
        }
       
        return $this->fun_success('登录成功',$brand_info_);
    }

    public function get_brand_info($brand_id){
        $row = $this->db->select("brand_name,m_brand_name")->from('brand')->where(array('id' => $brand_id))->get()->row_array();
        $this->db->select('count(a.user_id) num');
        $this->db->from('users');
        $this->db->where('brand_id', $brand_id);
        $this->db->where('status', 1);
        $num = $this->db->get()->row();
        $row['total_users'] = $num->num;
        return $this->fun_success('获取成功',$row);
    }

    public function update_brand_tt($brand_id,$token = ''){
        $update_data = array('mini_last_login' => time());
        if($token){
            $update_data['token'] = $token;
        }
        $this->db->where('id', $brand_id)->update('brand', $update_data);
    }


}