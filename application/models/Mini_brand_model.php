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
        if(time() - $brand_info_['mini_last_login'] > 60 * 60 * 12){
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
        $this->db->select('count(user_id) num');
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

    public function change_password($brand_id){
        $new_password = $this->input->post('new_password') ? trim($this->input->post('new_password')) : '';
        if(!$new_password)
            return $this->fun_fail('新密码不能为空!');
        if(strlen($new_password) < 6)
            return $this->fun_fail('新密码长度不能小于6位!');
        if(!ctype_alnum($new_password))
            return $this->fun_fail('新密码只能为字母和数字!');
        $this->db->where(array('id' => $brand_id))->update('brand', array('password' => sha1($new_password)));
        return $this->fun_success('修改成功');
    }

    //获取门店二级列表
    public function get_store_list($brand_id){
        $this->db->select("store_name,store_id");
        $this->db->from('brand_stores');
        $this->db->where(array('status' => 1, 'brand_id' => $brand_id));
        $re = $this->db->get()->result_array();
        return $this->fun_success('获取成功', $re);
    }


}