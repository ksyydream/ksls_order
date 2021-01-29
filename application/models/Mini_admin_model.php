<?php
/**
 * Created by PhpStorm.
 * User: yangyang
 * Date: 16/6/3
 * Time: 下午3:22
 */
class Mini_admin_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function check_token($admin_id, $token){
        $admin_info_ = $this->db->select()->from('admin')->where(array('token' => $token))->get()->row_array();
        if(!$admin_info_){
            return array(array('status' => -100, 'msg' => '未找到登录信息!', "result" => ''));
        }
        if(time() - $admin_info_['last_login_time'] > 60 * 30){
            return array(array('status' => -101, 'msg' => '请登录!', "result" => ''));
        }
        if($admin_id != $admin_info_){
            return array(array('status' => -101, 'msg' => '异常!', "result" => ''));
        }
        return $this->fun_success('登录成功',$admin_info_['admin_id']);
    }

    public function get_admin_info($admin_id){
        $row = $this->db->select("user,phone")->from('admin')->where(array('admin_id' => $admin_id))->get()->row_array();
        return $this->fun_success('获取成功',$row);
    }

    public function update_admin_tt($admin_id,$token = ''){
        $update_data = array('last_login_time' => time());
        if($token){
            $update_data['token'] = $token;
        }
        $this->db->where('admin_id', $admin_id)->update('admin', $update_data);
    }

}