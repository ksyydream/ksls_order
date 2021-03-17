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

    public function check_token($token, $admin_id = 0){
        $admin_info_ = $this->db->select()->from('admin')->where(array('token' => $token))->get()->row_array();
        if(!$admin_info_){
            return array('status' => -101, 'msg' => '未找到登录信息!', "result" => '');
        }
        if(time() - $admin_info_['mini_last_login'] > 60 * 60 * 12){
            return array('status' => -101, 'msg' => '请登录!', "result" => '');
        }
        if($admin_id != $admin_info_['admin_id']){
            return array('status' => -101, 'msg' => '异常!', "result" => '');
        }
        if($admin_info_['role_id'] <= 0){
            return array('status' => -101, 'msg' => '账号无小程序权限!', "result" => '');
        }
        $result_ = array('admin_id' => $admin_info_['admin_id'], 'role_id' => $admin_info_['role_id']);
        return $this->fun_success('登录成功', $result_);
    }

    public function get_admin_info($admin_id){
        $row = $this->db->select("a.user,a.phone,a.role_id,w.name role_name")
            ->from('admin a')
            ->join('work_role w','a.role_id = w.id','left')
            ->where(array('a.admin_id' => $admin_id))->get()->row_array();
        return $this->fun_success('获取成功',$row);
    }

    public function update_admin_tt($admin_id,$token = ''){
        $update_data = array('mini_last_login' => time());
        if($token){
            $update_data['token'] = $token;
        }
        $this->db->where('admin_id', $admin_id)->update('admin', $update_data);
    }

    public function ht_list(){
        $this->db->select()->from("contract");
        $this->db->where('status', 1);
        $list_ = $this->db->get()->result_array();
        return $this->fun_success('获取成功',$list_);
    }

    public function change_password($admin_id){
        $new_password = $this->input->post('new_password') ? trim($this->input->post('new_password')) : '';
        if(!$new_password)
            return $this->fun_fail('新密码不能为空!');
        if(strlen($new_password) < 6)
            return $this->fun_fail('新密码长度不能小于6位!');
        if(!ctype_alnum($new_password))
            return $this->fun_fail('新密码只能为字母和数字!');
        $this->db->where(array('admin_id' => $admin_id))->update('admin', array('password' => password($new_password)));
        return $this->fun_success('修改成功');
    }
}