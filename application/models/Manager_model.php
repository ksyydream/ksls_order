<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Manager_model extends MY_Model
{

    /**
     * 管理员操作Model
     * @version 1.0
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-29
     * @Copyright (C) 2017, Tianhuan Co., Ltd.
     */

    public function __construct() {
        parent::__construct();
    }

    public function check_login() {
        if (strtolower($this->input->post('verify')) != strtolower($this->session->flashdata('cap')))
            return -1;
        $data = array(
            'user' => trim($this->input->post('user')),
            'password' => password(trim($this->input->post('password'))),
        );
        $row = $this->db->select()->from('admin')->where($data)->get()->row_array();
        if ($row) {
            $data['admin_info'] = $row;
            $this->session->set_userdata($data);
            return 1;
        } else {
            return -2;
        }
    }

    /**
     * 获取用户所能显示的菜单
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-30
     */
    public function get_menu4admin($admin_id = 0) {
        $admin_info = $this->db->select()->from('auth_group g')
            ->join('auth_group_access a', 'g.id=a.group_id', 'left')
            ->where('a.admin_id', $admin_id)->get()->row_array();
        if (!$admin_info) {
            return array();
        }
        $menu_access_arr = explode(",", $admin_info['rules']);
        $this->db->select('id,title,pid,name,icon');
        $this->db->from('auth_rule');
        $this->db->where('islink', 1);
        $this->db->where('status', 1);
        if ($admin_info['group_id'] != 1) {
            $this->db->where_in('id', $menu_access_arr);
        }
        $menu = $this->db->order_by('o asc')->get()->result_array();
        return $menu;
    }

    public function get_action_menu($controller = null, $action = null) {
        $action_new = str_replace('edit', 'list', $action);
        $action_new = str_replace('add', 'list', $action_new);
        $this->db->select('s.id,s.title,s.name,s.tips,s.pid,p.pid as ppid,p.title as ptitle');
        $this->db->from('auth_rule s');
        $this->db->join('auth_rule p', 'p.id = s.pid', 'left');
        $this->db->where('s.name', $controller . '/' . $action_new);
        $row = $this->db->get()->row_array();
        if (!$row) {
            $this->db->select('s.id,s.title,s.name,s.tips,s.pid,p.pid as ppid,p.title as ptitle');
            $this->db->from('auth_rule s');
            $this->db->join('auth_rule p', 'p.id = s.pid', 'left');
            $this->db->where('s.name', $controller . '/' . $action);
            $row = $this->db->get()->row_array();
        }
        return $row;
    }

    public function get_admin($admin_id) {
        $admin_info = $this->db->select('a.*,b.group_id,c.title')->from('admin a')
            ->join('auth_group_access b', 'a.admin_id = b.admin_id', 'left')
            ->join('auth_group c', 'c.id = b.group_id', 'left')
            ->where('a.admin_id', $admin_id)->get()->row_array();
        return $admin_info;
    }

    /**
     *********************************************************************************************
     * 以下代码为系统设置模块
     *********************************************************************************************
     */

    /**
     * 查找所有可添加的菜单
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function get_menu_all() {
        $this->db->select('id,title,pid,name,icon,islink,o');
        $this->db->from('auth_rule');
        $this->db->where('status', 1);
        $menu = $this->db->order_by('o asc')->get()->result_array();
        return $menu;
    }

    /**
     * 获取后台菜单详情
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function menu_info($id) {
        $menu_info = $this->db->select()->from('auth_rule')->where('id', $id)->get()->row_array();
        return $menu_info;
    }

    /**
     * 保存管理员管理
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function menu_save() {
        $data = array(
            'pid' => trim($this->input->post('pid')) ? trim($this->input->post('pid')) : 0,
            'title' => trim($this->input->post('title')) ? trim($this->input->post('title')) : null,
            'name' => trim($this->input->post('name')) ? trim($this->input->post('name')) : '',
            'icon' => trim($this->input->post('icon')) ? trim($this->input->post('icon')) : '',
            'islink' => trim($this->input->post('islink')) ? trim($this->input->post('islink')) : 0,
            'o' => trim($this->input->post('o')) ? trim($this->input->post('o')) : 0,
            'tips' => trim($this->input->post('tips')) ? trim($this->input->post('tips')) : '',
            'cdate' => date('Y-m-d H:i:s', time()),
            'mdate' => date('Y-m-d H:i:s', time())
        );
        if (!$data['title'])
            return -2;//信息不全
        if ($id = $this->input->post('id')) {
            unset($data['cdate']);
            $this->db->where('id', $id)->update('auth_rule', $data);
        } else {
            $this->db->insert('auth_rule', $data);
        }
        return 1;
    }

    /**
     * 删除管理员
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function menu_del($id) {
        if (!$id)
            return -1;
        $rs = $this->db->where('id', $id)->delete('auth_rule');
        if ($rs)
            return 1;
        return -1;
    }

    /**
     *********************************************************************************************
     * 以下代码为个人中心模块
     *********************************************************************************************
     */

    /**
     * 管理员管理
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */

    public function admin_list($page = 1) {
        $data['limit'] = $this->limit;//每页显示多少调数据
        $data['keyword'] = trim($this->input->get('keyword')) ? trim($this->input->get('keyword')) : null;
        $data['field'] = trim($this->input->get('field')) ? trim($this->input->get('field')) : 1;// 1是用户名,2是电话,3是QQ,4是邮箱
        $data['order'] = trim($this->input->get('order')) ? trim($this->input->get('order')) : 1;// 1是desc,2是asc
        $this->db->select('count(1) num');
        $this->db->from('admin a');
        $this->db->join('auth_group_access b', 'a.admin_id = b.admin_id', 'left');
        $this->db->join('auth_group c', 'c.id = b.group_id', 'left');
        if ($data['keyword']) {
            switch ($data['field']) {
                case '1':
                    $this->db->like('a.user', $data['keyword']);
                    break;
                case '2':
                    $this->db->like('a.phone', $data['keyword']);
                    break;
                case '3':
                    $this->db->like('a.qq', $data['keyword']);
                    break;
                case '4':
                    $this->db->like('a.email', $data['keyword']);
                    break;
                default:
                    $this->db->like('a.user', $data['keyword']);
                    break;
            }
        }
        $rs_total = $this->db->get()->row();
        //总记录数
        $total_rows = $rs_total->num;
        $data['total_rows'] = $total_rows;
        //list
        $this->db->select('a.*,b.group_id,c.title,wr.name role_name');
        $this->db->from('admin a');
        $this->db->join('auth_group_access b', 'a.admin_id = b.admin_id', 'left');
        $this->db->join('auth_group c', 'c.id = b.group_id', 'left');
        $this->db->join('work_role wr', 'wr.id = a.role_id', 'left');
        if ($data['keyword']) {
            switch ($data['field']) {
                case '1':
                    $this->db->like('a.user', $data['keyword']);
                    break;
                case '2':
                    $this->db->like('a.phone', $data['keyword']);
                    break;
                case '3':
                    $this->db->like('a.qq', $data['keyword']);
                    break;
                case '4':
                    $this->db->like('a.email', $data['keyword']);
                    break;
                default:
                    $this->db->like('a.user', $data['keyword']);
                    break;
            }
        }
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        if ($data['order'] == 1) {
            $this->db->order_by('a.t', 'desc');
        } else {
            $this->db->order_by('a.t', 'asc');
        }
        $data['res_list'] = $this->db->get()->result_array();
        return $data;
    }

    /**
     * 查找所有可添加的用户组
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function get_group_all() {
        $this->db->select('id,title');
        $this->db->from('auth_group');
        $this->db->where('status', 1);
        $menu = $this->db->order_by('id asc')->get()->result_array();
        return $menu;
    }

    /**
     * 保存管理员管理
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function admin_save() {
        $data = array(
            'role_id' => trim($this->input->post('role_id')) ? trim($this->input->post('role_id')) : -1,
            'user' => trim($this->input->post('user')) ? trim($this->input->post('user')) : null,
            'sex' => $this->input->post('sex') ? $this->input->post('sex') : 0,
            'head' => $this->input->post('head') ? $this->input->post('head') : null,
            'admin_name' => $this->input->post('admin_name') ? $this->input->post('admin_name') : null,
            'phone' => trim($this->input->post('phone')) ? trim($this->input->post('phone')) : null,
            'qq' => trim($this->input->post('qq')) ? trim($this->input->post('qq')) : null,
            'email' => trim($this->input->post('email')) ? trim($this->input->post('email')) : null,
            'birthday' => trim($this->input->post('birthday')) ? trim($this->input->post('birthday')) : null,
            't' => time()
        );
        if (!$data['user'] || !$data['head'] || !$data['phone'] || !$data['admin_name'])
            return $this->fun_fail('信息不全!');
        if (!file_exists(dirname(SELF) . '/upload_files/head/' . $data['head'])) {
            return $this->fun_fail('信息不全,头像异常!');
        }
        if (!$group_id = $this->input->post('group_id')) {
            return $this->fun_fail('需要选择用户组!');
        }
        if (trim($this->input->post('password'))) {
            if (strlen(trim($this->input->post('password'))) < 6) {
                return $this->fun_fail('密码长度不可小于6位!');
            }
            if (is_numeric(trim($this->input->post('password')))) {
                return $this->fun_fail('密码不可是纯数字!');
            }
            $data['password'] = password(trim($this->input->post('password')));
        }
        if ($admin_id = $this->input->post('admin_id')) {
            unset($data['t']);
            $check_ = $this->db->select()->from('admin')
                ->where('user', $data['user'])
                ->where('admin_id <>', $admin_id)
                ->get()->row_array();
            if ($check_) {
                return $this->fun_fail('新建或修改的用户名已存在!');
            }
            $this->db->where('admin_id', $admin_id)->update('admin', $data);
        } else {
            if (!trim($this->input->post('password'))) {
                return $this->fun_fail('新建用户需要设置密码!');
            }
            $check_ = $this->db->select()->from('admin')->where('user', $data['user'])->get()->row_array();
            if ($check_) {
                return $this->fun_fail('新建或修改的用户名已存在!');
            }
            $this->db->insert('admin', $data);
            $admin_id = $this->db->insert_id();
        }
        $this->db->where('admin_id', $admin_id)->delete('auth_group_access');
        $this->db->insert('auth_group_access', array('admin_id' => $admin_id, 'group_id' => $group_id));

        //$work_role_ids = $this->input->post('work_role_ids');
        //$this->db->where('admin_id', $admin_id)->delete('admin_work_role');
        //if ($work_role_ids) {
            //if (is_array($work_role_ids)) {
                //foreach ($work_role_ids as $item) {
                    //$this->db->insert('admin_work_role', array('admin_id' => $admin_id, 'r_id' => $item));
                //}
            //}
        //}
        return $this->fun_success('保存成功');
    }

    /**
     * 删除管理员
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function admin_del($id) {
        if (!$id)
            return -1;
        $admin_info = $this->get_admin($id);
        if (!$admin_info)
            return -1;
        if ($admin_info['group_id'] == 1)
            return -2;
        $rs = $this->db->where('admin_id', $id)->delete('admin');
        if ($rs)
            return 1;
        return -1;
    }

    /**
     * 获取用户组信息
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function get_group_detail($id = 0) {
        $group_detail = $this->db->select()->from('auth_group')->where('id', $id)->get()->row_array();
        if (!$group_detail) {
            return -1;
        }
        $group_detail['rules'] = explode(',', $group_detail['rules']);
        return $group_detail;
    }

    /**
     * 保存用户组
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function group_save() {
        $data = array(
            'title' => trim($this->input->post('title')) ? trim($this->input->post('title')) : null,
            'status' => $this->input->post('status') ? $this->input->post('status') : -1,
        );
        if ($data['title'] == "") {
            return -1;
        }
        $rules = $this->input->post('rules') ? $this->input->post('rules') : 0;
        if (is_array($rules)) {
            foreach ($rules as $k => $v) {
                $rules[$k] = intval($v);
            }
            $rules = implode(',', $rules);
        }
        $data['rules'] = $rules;
        if ($group_id = $this->input->post('id')) {
            $this->db->where('id', $group_id)->update('auth_group', $data);
        } else {
            $this->db->insert('auth_group', $data);
        }
        return 1;
    }

    /**
     * 用户组列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function group_list($page = 1) {
        $data['limit'] = $this->limit;//每页显示多少调数据
        $this->db->select('count(1) num');
        $this->db->from('auth_group a');
        $rs_total = $this->db->get()->row();
        //总记录数
        $total_rows = $rs_total->num;
        $data['total_rows'] = $total_rows;

        //list
        $this->db->select('a.*');
        $this->db->from("auth_group a");
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $this->db->order_by('id', 'asc');
        $data['res_list'] = $this->db->get()->result_array();
        return $data;
    }

    /**
     * 删除用户组
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function group_del($id) {
        if (!$id)
            return -1;
        if ($id == 1)
            return -2;
        $rs = $this->db->where('id', $id)->delete('auth_group');
        if ($rs)
            return 1;
        return -1;
    }

    /**
     * 保存管理员管理
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function personal_save($admin_id) {
        $data = array(
            'user' => trim($this->input->post('user')) ? trim($this->input->post('user')) : null,
            'sex' => $this->input->post('sex') ? $this->input->post('sex') : 0,
            'head' => $this->input->post('head') ? $this->input->post('head') : null,
            'phone' => trim($this->input->post('phone')) ? trim($this->input->post('phone')) : null,
            'admin_name' => trim($this->input->post('admin_name')) ? trim($this->input->post('admin_name')) : null,
            'qq' => trim($this->input->post('qq')) ? trim($this->input->post('qq')) : null,
            'email' => trim($this->input->post('email')) ? trim($this->input->post('email')) : null,
            'birthday' => trim($this->input->post('birthday')) ? trim($this->input->post('birthday')) : null,
        );
        if (!$data['user'] || !$data['head'] || !$data['phone'] || !$data['admin_name'])
            return $this->fun_fail('信息不全!');
        if (!file_exists(dirname(SELF) . '/upload_files/head/' . $data['head'])) {
            return $this->fun_fail('信息不全!');
        }
        if (trim($this->input->post('password'))) {
            if (strlen(trim($this->input->post('password'))) < 6) {
                return $this->fun_fail('密码长度不可小于6位!');
            }
            if (is_numeric(trim($this->input->post('password')))) {
                return $this->fun_fail('密码不可是纯数字!');
            }
            $data['password'] = password(trim($this->input->post('password')));
        }
        $this->db->where('admin_id', $admin_id)->update('admin', $data);
        return $this->fun_success('保存成功!');
    }

    /**
     *********************************************************************************************
     * 大客户模块
     *********************************************************************************************
     */

    /**
     * 大客户列表
     * @author yangyang
     * @date 2021-01-12
     */
    public function brand_list($page = 1){
        $data['limit'] = $this->limit;

        //获取总记录数
        $this->db->select('count(1) num')->from('brand a');

        $num = $this->db->get()->row();
        $data['total_rows'] = $num->num;

        //获取详细列
        $this->db->select('a.*')->from('brand a');

        $this->db->limit($this->limit, $offset = ($page - 1) * $this->limit);
        $this->db->order_by('a.id','desc');
        $data['res_list'] = $this->db->get()->result_array();
        return $data;
    }

    public function brand_edit($id){
        $detail =  $this->readByID('brand', 'id', $id);
        return $detail;
    }

    public function brand_save(){
        $data =array(
            'brand_name'=>trim($this->input->post('brand_name')),
            'm_brand_name'=>trim($this->input->post('m_brand_name')),
            'create_time' => time(),
            'status' => trim($this->input->post('status')) ? trim($this->input->post('status')) : -1
        );
        if(!$data['brand_name'])
            return $this->fun_fail('大客户名称不能为空！');
        $id = $this->input->post('id');
        if($id){
            unset($data['create_time']);
            $this->db->where('id', $id)->update('brand', $data);
        }else{
            $data['username'] = $this->get_username();
            $data['password'] = sha1('123456');
            $this->db->insert('brand', $data);
        }
        return $this->fun_success('保存成功!');
    }

    /**
     * 会员列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function users_list($page = 1) {
        $data['limit'] = $this->limit;//每页显示多少调数据
        $data['keyword'] = trim($this->input->get('keyword')) ? trim($this->input->get('keyword')) : null;
        $data['type_id'] = trim($this->input->get('type_id')) ? trim($this->input->get('type_id')) : null;
        $data['status'] = trim($this->input->get('status')) ? trim($this->input->get('status')) : null;
        $data['s_date'] = trim($this->input->get('s_date')) ? trim($this->input->get('s_date')) : '';
        $data['e_date'] = trim($this->input->get('e_date')) ? trim($this->input->get('e_date')) : '';

        $this->db->select('count(1) num');
        $this->db->from('users us');
        $this->db->join('brand b','us.brand_id = b.id','left');
        if ($data['keyword']) {
            $this->db->group_start();
            $this->db->like('us.rel_name', $data['keyword']);
            $this->db->or_like('us.mobile', $data['keyword']);
            $this->db->group_end();
        }
        if ($data['s_date']) {
            $this->db->where('us.reg_time >=', strtotime($data['s_date'] . " 00:00:00"));
        }
        if ($data['e_date']) {
            $this->db->where('us.reg_time <=', strtotime($data['e_date'] . " 23:59:59"));
        }
        if ($data['type_id']) {
            $this->db->where('us.type_id', $data['type_id']);
        }
        if ($data['status']) {
            $this->db->where('us.status', $data['status']);
        }

        $rs_total = $this->db->get()->row();
        //总记录数
        $total_rows = $rs_total->num;
        $data['total_rows'] = $total_rows;
        //list
        $this->db->select('us.*,b.brand_name,r1.name r1_name,r2.name r2_name,r3.name r3_name,r4.name r4_name, m.rel_name m_rel_name_,m.mobile m_mobile_');
        $this->db->from('users us');
        $this->db->join('brand b','us.brand_id = b.id','left');
        $this->db->join('region r1', 'us.province = r1.id', 'left');
        $this->db->join('region r2', 'us.city = r2.id', 'left');
        $this->db->join('region r3', 'us.district = r3.id', 'left');
        $this->db->join('region r4', 'us.twon = r4.id', 'left');
        $this->db->join('members m', 'm.m_id = us.invite', 'left');
        if ($data['keyword']) {
            $this->db->group_start();
            $this->db->like('us.rel_name', $data['keyword']);
            $this->db->or_like('us.mobile', $data['keyword']);
            $this->db->group_end();
        }
        if ($data['s_date']) {
            $this->db->where('us.reg_time >=', strtotime($data['s_date'] . " 00:00:00"));
        }
        if ($data['e_date']) {
            $this->db->where('us.reg_time <=', strtotime($data['e_date'] . " 23:59:59"));
        }
        if ($data['type_id']) {
            $this->db->where('us.type_id', $data['type_id']);
        }
        if ($data['status']) {
            $this->db->where('us.status', $data['status']);
        }

        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $this->db->order_by('us.reg_time', 'desc');
        $data['res_list'] = $this->db->get()->result_array();

        return $data;
    }

    /**
     * 会员详情
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-07-22
     */
    public function users_edit($user_id){
        $this->db->select('us.*,b.brand_name,r1.name r1_name,r2.name r2_name,r3.name r3_name,r4.name r4_name, m.rel_name m_rel_name_,m.mobile m_mobile_');
        $this->db->from('users us');
        $this->db->join('brand b','us.brand_id = b.id','left');
        $this->db->join('region r1', 'us.province = r1.id', 'left');
        $this->db->join('region r2', 'us.city = r2.id', 'left');
        $this->db->join('region r3', 'us.district = r3.id', 'left');
        $this->db->join('region r4', 'us.twon = r4.id', 'left');
        $this->db->join('members m', 'm.m_id = us.invite', 'left');
        $user_info = $this->db->where('user_id', $user_id)->get()->row_array();
        if(!$user_info)
            return $user_info;
        $this->db->select()->from('members');
        $this->db->group_start();
        $this->db->where_in('level', array(2,3));
        $this->db->where('status', 1);
        $this->db->group_end();
        $this->db->or_group_start();
        $this->db->where('m_id', $user_info['invite']);
        $this->db->group_end();
        $user_info['sel_member_list'] = $this->db->get()->result_array();
        return $user_info;
    }

    /**
     * 保存会员
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2019-07-22
     */
    public function users_save(){
        $user_id = $this->input->post('user_id');
        $update = array(
            'status' => $this->input->post('status') ? $this->input->post('status') : -1,
            'remark' => $this->input->post('remark')
        );
        if($update['status'] != 1)
            $update['openid'] = '';
        if(!$user_id){
            return $this->fun_fail('操作失败');
        }
        if(!in_array($update['status'], array(1, -1))){
            return $this->fun_fail('请选择状态');
        }

        $this->db->where('user_id', $user_id)->update('users', $update);
        return $this->fun_success('操作成功');
    }


}
