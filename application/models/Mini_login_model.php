<?php
/**
 * Created by PhpStorm.
 * User: yangyang
 * Date: 16/6/3
 * Time: 下午3:22
 */
class Mini_login_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function logout(){
        $this->db->where('user_id',$this->session->userdata('wx_user_id'))->update('users',array('openid'=>''));
        $this->db->where('m_id',$this->session->userdata('wx_m_id'))->update('members',array('openid'=>''));
        $this->session->unset_userdata('wx_user_id');
        $this->session->unset_userdata('wx_m_id');
        $this->session->unset_userdata('wx_class');
        $this->session->sess_destroy();
    }

    public function admin_login(){

        $data = array(
            'user' => trim($this->input->post('user')),
            'password' => password(trim($this->input->post('password'))),
        );
        $row = $this->db->select()->from('admin')->where($data)->get()->row_array();
        if ($row) {
            return $this->fun_success('操作成功',$row);
        } else {
            return $this->fun_fail('登录失败');
        }
    }

}