<?php
/**
 * Created by PhpStorm.
 * User: yangyang
 * Date: 16/6/3
 * Time: 下午3:22
 */
class Mini_user_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function check_token($token, $user_id = 0){
        $user_info_ = $this->db->select()->from('users')->where(array('token' => $token))->get()->row_array();
        if(!$user_info_){
            return array('status' => -100, 'msg' => '未找到登录信息!', "result" => '');
        }
        if(time() - $user_info_['mini_last_login'] > 60 * 30){
            return array('status' => -101, 'msg' => '请登录!', "result" => '');
        }
        if($user_id != $user_info_['user_id']){
            return array('status' => -101, 'msg' => '异常!', "result" => '');
        }
        return $this->fun_success('登录成功',$user_info_['user_id']);
    }

    public function get_user_info($user_id){
        $row = $this->db->select("rel_name")->from('users')->where(array('user_id' => $user_id))->get()->row_array();
        return $this->fun_success('获取成功',$row);
    }

    public function update_user_tt($user_id,$token = ''){
        $update_data = array('mini_last_login' => time());
        if($token){
            $update_data['token'] = $token;
        }
        $this->db->where('user_id', $user_id)->update('users', $update_data);
    }

}