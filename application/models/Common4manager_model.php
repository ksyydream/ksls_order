<?php
/**
 * Created by PhpStorm.
 * User: yangyang
 * Date: 16/6/3
 * Time: 下午3:22
 */
class Common4manager_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }


    //获取工作权限列表
    public function get_work_role($status = null){
        $this->db->select()->from('work_role');
        if($status){
            $this->db->where('status', $status);
        }
        $res = $this->db->get()->result_array();
        return $res;
    }

    /** check fun */



}