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
     public function get_brand4select($status= 0){
      $this->db->select();
      $this->db->from("brand");
      if($status)
        $this->db->where('status', $status);
        $data = $this->db->get()->result_array();
        return $data;
	 }

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
            $data['username'] = trim($this->input->post('username'));
            if(!$data['username'])
                return $this->fun_fail('用户名不能为空！');
            $check_ = $this->db->select('*')->from('brand')->where(array('username' => $data['username'], 'id <>' => $id))->get()->row_array();
            if($check_)
                return $this->fun_fail('此用户名已注册,不可使用！');
            if(strlen($data['username']) < 6)
                return $this->fun_fail('用户名长度不能小于6位!');
            if(!ctype_alnum($data['username']))
                return $this->fun_fail('用户名只能为字母和数字!');
            $this->db->where('id', $id)->update('brand', $data);
        }else{
            $data['username'] = $this->get_username();
            $data['password'] = sha1('123456');
            $this->db->insert('brand', $data);
        }
        return $this->fun_success('保存成功!');
    }

    public function refresh_brand_password($admin_id){
        $id = $this->input->post('id');
        if(!$id)
            return $this->fun_fail('信息缺失!');
        $this->db->where(array('id' => $id))->update('brand', array('password' => sha1('123456')));
        return $this->fun_success('重置成功!');
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

      /**
     *********************************************************************************************
     * 赎楼业务相关
     *********************************************************************************************
     */
      /**
     * 合同列表
     * @author yangyang
     * @date 2021-01-12
     */

    public function contract_list($page = 1){
        $data['limit'] = $this->limit;

        //获取总记录数
        $this->db->select('count(1) num')->from('contract a');

        $num = $this->db->get()->row();
        $data['total_rows'] = $num->num;

        //获取详细列
        $this->db->select('a.*')->from('contract a');

        $this->db->limit($this->limit, $offset = ($page - 1) * $this->limit);
        $this->db->order_by('a.ht_id','desc');
        $data['res_list'] = $this->db->get()->result_array();
        return $data;
    }

    public function contract_edit($id){
        $detail =  $this->readByID('contract', 'ht_id', $id);
        return $detail;
    }

    public function contract_save(){
        $data =array(
            'ht_name'=>trim($this->input->post('ht_name')),
            'ht_text'=>trim($this->input->post('ht_text')),
            'status' => trim($this->input->post('status')) ? trim($this->input->post('status')) : -1
        );
        if(!$data['ht_name'])
            return $this->fun_fail('合同名称不能为空！');
        $id = $this->input->post('ht_id');
        if($id){
            //unset($data['create_time']);
            $this->db->where('ht_id', $id)->update('contract', $data);
        }else{
         
            $this->db->insert('contract', $data);
        }
        return $this->fun_success('保存成功!');
     }

    /**
     * 监管项目
     * @author yangyang
     * @date 2021-02-19
     */

    public function supervise_list($page = 1){
        $data['limit'] = $this->limit;

        //获取总记录数
        $this->db->select('count(1) num')->from('supervise a');

        $num = $this->db->get()->row();
        $data['total_rows'] = $num->num;

        //获取详细列
        $this->db->select('a.*')->from('supervise a');

        $this->db->limit($this->limit, $offset = ($page - 1) * $this->limit);
        $this->db->order_by('a.id','desc');
        $data['res_list'] = $this->db->get()->result_array();
        return $data;
    }

       /**
     * 赎楼列表
     * @author yangyang
     * @date 2021-02-18
     */

    public function loan_list($page = 1, $where = array()){
        $data['limit'] = $this->limit;
        $data['keyword'] = trim($this->input->get('keyword')) ? trim($this->input->get('keyword')) : null;
        $data['order_type'] = trim($this->input->get('type_id')) ? trim($this->input->get('type_id')) : null;
        $data['status'] = trim($this->input->get('status')) ? trim($this->input->get('status')) : null;
        $data['flag'] = trim($this->input->get('flag')) ? trim($this->input->get('flag')) : null;
        $data['brand_id'] = trim($this->input->get('brand_id')) ? trim($this->input->get('brand_id')) : null;
        $data['s_date'] = trim($this->input->get('s_date')) ? trim($this->input->get('s_date')) : '';
        $data['e_date'] = trim($this->input->get('e_date')) ? trim($this->input->get('e_date')) : '';
        //获取总记录数
       
        $this->db->select('count(DISTINCT a.loan_id) num');
        $this->db->from('loan_master a');
        $this->db->join('loan_borrowers b', 'a.loan_id = b.loan_id', 'left');
        if($where)
            $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('b.borrower_name', $data['keyword']);
            $this->db->or_like('b.borrower_card', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['status']){
            $this->db->where('a.status', $data['status']);
        }
        if ($data['s_date']) {
            $this->db->where('a.create_time >=', strtotime($data['s_date'] . " 00:00:00"));
        }
        if ($data['e_date']) {
            $this->db->where('a.create_time <=', strtotime($data['e_date'] . " 23:59:59"));
        }
        if($data['brand_id']){
            $this->db->where('a.brand_id', $data['brand_id']);
        }
        $num = $this->db->get()->row();
        $data['total_rows'] = $num->num;

        //获取详细列
       $this->db->select("a.loan_id,a.work_no,a.loan_money,a.is_td_ng,a.order_type,a.is_err,a.need_mx,a.status,a.flag,
        u.rel_name handle_name,u.mobile handle_mobile,
        u1.rel_name create_name,u1.mobile create_mobile,
        fk.admin_name fk_name,qz.admin_name qz_name,fc.admin_name fc_name,
         bd.brand_name,FROM_UNIXTIME(a.create_time) loan_cdate, mx.admin_name mx_name,mx.phone mx_phone,a.appointment_date");
        $this->db->from('loan_master a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('loan_borrowers b', 'a.loan_id = b.loan_id', 'left');
        $this->db->join('admin mx', 'a.mx_admin_id = mx.admin_id', 'left');
        $this->db->join('admin fk', 'a.fk_admin_id = fk.admin_id', 'left');
        $this->db->join('admin qz', 'a.qz_admin_id = qz.admin_id', 'left');
        $this->db->join('admin fc', 'a.fc_admin_id = fc.admin_id', 'left');
        if($where)
            $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('b.borrower_name', $data['keyword']);
            $this->db->or_like('b.borrower_card', $data['keyword']);
            $this->db->group_end();
        }
        if($data['flag']){
            $this->db->where('a.flag', $data['flag']);
        }
        if($data['status']){
            $this->db->where('a.status', $data['status']);
        }
        if ($data['s_date']) {
            $this->db->where('a.create_time >=', strtotime($data['s_date'] . " 00:00:00"));
        }
        if ($data['e_date']) {
            $this->db->where('a.create_time <=', strtotime($data['e_date'] . " 23:59:59"));
        }
        if($data['brand_id']){
            $this->db->where('a.brand_id', $data['brand_id']);
        }
      
        $this->db->order_by('a.loan_id', 'desc'); //给个默认排序
        $this->db->group_by('a.loan_id');
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $data['res_list'] = $this->db->get()->result_array();
        //die(var_dump($this->db->last_query()));
        return $data;
    }

    public function loan_edit($loan_id, $where = array()){
      //这里可能需要加入role_id 权限
      $select = "a.*,FROM_UNIXTIME(a.create_time) loan_cdate,
     
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
        DATE_FORMAT(a.appointment_date,'%Y-%m-%d') appointment_date_handle_,
        DATE_FORMAT(a.redeem_date,'%Y-%m-%d') redeem_date_handle_,
        u.rel_name handle_name,u.mobile handle_mobile,
        u1.rel_name create_name,u1.mobile create_mobile,
        qz.admin_name qz_name,zs.admin_name zs_name,err.admin_name err_name,ww.admin_name ww_name,fc.admin_name fc_name,
        bd.brand_name, mx.admin_name mx_name, fk.admin_name fk_name,mx.phone mx_phone";
        $this->db->select($select)->from('loan_master a');
        $this->db->join('users u','a.user_id = u.user_id','left');
        $this->db->join('users u1','a.create_user_id = u1.user_id','left');
        $this->db->join('brand bd','a.brand_id = bd.id','left');
        $this->db->join('admin mx', 'a.mx_admin_id = mx.admin_id', 'left');
        $this->db->join('admin fk', 'a.fk_admin_id = fk.admin_id', 'left');
        $this->db->join('admin qz', 'a.qz_admin_id = qz.admin_id', 'left');
        $this->db->join('admin fc', 'a.fc_admin_id = fc.admin_id', 'left');
        $this->db->join('admin zs', 'a.zs_admin_id = zs.admin_id', 'left');
        $this->db->join('admin err', 'a.err_admin_id = err.admin_id', 'left');
        $this->db->join('admin ww', 'a.ww_admin_id = ww.admin_id', 'left');
        if($where)
            $this->db->where($where);
        //$this->db->join('admin cw', 'a.cw_admin_id = cw.admin_id', 'left');
        $loan_info = $this->db->where('a.loan_id', $loan_id)->get()->row_array();
        if(!$loan_info)
            return $this->fun_fail('未找到相关订单!');
        $this->db->select('*');
        $this->db->from('loan_borrowers');
        $this->db->where('loan_id', $loan_id);
        $loan_info['borrowers_list'] = $this->db->get()->result_array();

        // 其实与Loan_model的代码重复
        $this->db->select("s.*,ls.option_value,ifnull(ls.id,-1) is_check")->from('supervise s');
        $this->db->join('loan_supervise ls','s.id = ls.option_id and ls.loan_id = '. $loan_id,'left');
        $this->db->where('s.status', 1);
        $loan_supervise = $this->db->order_by('s.id','asc')->get()->result_array();
        foreach($loan_supervise as $k => $v) {
            if($loan_supervise[$k]['is_check'] != -1){
                $loan_supervise[$k]['is_check'] = 1;
            }
        }
        $loan_info['loan_supervise'] = $loan_supervise;
        return $this->fun_success('获取成功!', $loan_info);
	}

    public function save_fk_report($admin_id, $role_id){
        $loan_id = $this->input->post('loan_id');
        if(!$loan_id || $loan_id <= 0)
            return $this->fun_fail('未传入必要信息!');
        $loan_info_ = $this->db->select("fk_admin_id, status, flag")->from("loan_master")->where("loan_id", $loan_id)->get()->row_array();
        if(!$loan_info_){
            return $this->fun_fail('信息不存在!');
        }
        if($role_id != 2)
            return $this->fun_fail('只有风控经理才可操作!');
        if($loan_info_['fk_admin_id'] != $admin_id)
            return $this->fun_fail('只有指定风控经理才可操作!');
        if($loan_info_['status'] != 2 || $loan_info_['flag'] != 1)
            return $this->fun_fail('当前已不是风控审核状态!');
        $fk_report = $this->input->post("fk_report");
        $update_ = array(
            'fk_report' => $fk_report
        );
        $this->db->where(array('loan_id' => $loan_id, 'status' => 2))->update('loan_master', $update_);
        return $this->fun_success('保存成功!');
    }

     /**
     *********************************************************************************************
     * 以下代码为系统记录模块
     *********************************************************************************************
     */

    /**
     * 短信日志列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2019-07-23
     */
    public function sms_list($page = 1){
        $data['limit'] = $this->limit;//每页显示多少调数据
        $data['mobile'] = trim($this->input->get('mobile')) ? trim($this->input->get('mobile')) : null;
        $data['s_date'] = trim($this->input->get('s_date')) ? trim($this->input->get('s_date')) : '';
        $data['e_date'] = trim($this->input->get('e_date')) ? trim($this->input->get('e_date')) : '';
        $where_ = array('sl.id >' => 0);
        if ($data['s_date']) {
            $where_['sl.add_time >='] = strtotime($data['s_date'] . " 00:00:00");
        }
        if ($data['e_date']) {
            $where_['sl.add_time <='] = strtotime($data['e_date'] . " 00:00:00");
        }
        if ($data['mobile']) {
            $where_['sl.mobile like'] = '%' . $data['mobile'] . '%';
        }
        $this->db->select('count(1) num');
        $this->db->from('sms_log sl');
        $this->db->where($where_);
        $rs_total = $this->db->get()->row();
        //die(var_dump($this->db->last_query()));
        //总记录数
        $total_rows = $rs_total->num;
        $data['total_rows'] = $total_rows;
        //list
        $this->db->select('sl.*');
        $this->db->from('sms_log sl');
        $this->db->where($where_);
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $this->db->order_by('sl.add_time', 'desc');
        $data['res_list'] = $this->db->get()->result_array();
        return $data;
    }

    /**
     * 同盾日志列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2019-07-23
     */
    public function tongdun_log_list($page = 1){
        $data['limit'] = $this->limit;//每页显示多少调数据
        $data['keyword'] = trim($this->input->get('keyword')) ? trim($this->input->get('keyword')) : null;
        $data['keyword2'] = trim($this->input->get('keyword2')) ? trim($this->input->get('keyword2')) : null;
        $data['s_date'] = trim($this->input->get('s_date')) ? trim($this->input->get('s_date')) : '';
        $data['e_date'] = trim($this->input->get('e_date')) ? trim($this->input->get('e_date')) : '';

        $this->db->select('count(1) num');
        $this->db->from('tongdun_log tl');
        $this->db->join('users us', 'tl.user_id = us.user_id', 'left');
        if ($data['keyword']) {
            $this->db->group_start();
            $this->db->like('tl.account_name', $data['keyword']);
            $this->db->or_like('tl.id_number', $data['keyword']);
            $this->db->group_end();
        }
        if ($data['keyword2']) {
            $this->db->group_start();
            $this->db->like('us.rel_name', $data['keyword2']);
            $this->db->or_like('us.mobile', $data['keyword2']);
            $this->db->group_end();
        }
        if ($data['s_date']) {
            $this->db->where('tl.add_time >=', strtotime($data['s_date'] . " 00:00:00"));
        }
        if ($data['e_date']) {
            $this->db->where('tl.add_time <=', strtotime($data['e_date'] . " 23:59:59"));
        }
        $rs_total = $this->db->get()->row();
        //总记录数
        $total_rows = $rs_total->num;
        $data['total_rows'] = $total_rows;
        //list
        $this->db->select('tl.*, us.rel_name us_rel_name_, us.mobile us_mobile_');
        $this->db->from('tongdun_log tl');
        $this->db->join('users us', 'tl.user_id = us.user_id', 'left');
        if ($data['keyword']) {
            $this->db->group_start();
            $this->db->like('tl.account_name', $data['keyword']);
            $this->db->or_like('tl.id_number', $data['keyword']);
            $this->db->group_end();
        }
        if ($data['keyword2']) {
            $this->db->group_start();
            $this->db->like('us.rel_name', $data['keyword2']);
            $this->db->or_like('us.mobile', $data['keyword2']);
            $this->db->group_end();
        }
        if ($data['s_date']) {
            $this->db->where('tl.add_time >=', strtotime($data['s_date'] . " 00:00:00"));
        }
        if ($data['e_date']) {
            $this->db->where('tl.add_time <=', strtotime($data['e_date'] . " 23:59:59"));
        }
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $this->db->order_by('tl.add_time', 'desc');
        $data['res_list'] = $this->db->get()->result_array();
        return $data;
    }

    /**
     * 同盾数据列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2019-07-25
     */
    public function tongdun_info_list($page = 1){
        $data['limit'] = $this->limit;//每页显示多少调数据
        $data['keyword'] = trim($this->input->get('keyword')) ? trim($this->input->get('keyword')) : null;
        $data['keyword2'] = trim($this->input->get('keyword2')) ? trim($this->input->get('keyword2')) : null;
        $data['s_date'] = trim($this->input->get('s_date')) ? trim($this->input->get('s_date')) : '';
        $data['e_date'] = trim($this->input->get('e_date')) ? trim($this->input->get('e_date')) : '';

        $this->db->select('count(1) num');
        $this->db->from('tongdun_info ti');
        $this->db->join('users us', 'ti.user_id = us.user_id', 'left');
        if ($data['keyword']) {
            $this->db->group_start();
            $this->db->like('ti.account_name', $data['keyword']);
            $this->db->or_like('ti.id_number', $data['keyword']);
            $this->db->group_end();
        }
        if ($data['keyword2']) {
            $this->db->group_start();
            $this->db->like('us.rel_name', $data['keyword2']);
            $this->db->or_like('us.mobile', $data['keyword2']);
            $this->db->group_end();
        }
        if ($data['s_date']) {
            $this->db->where('ti.add_time >=', strtotime($data['s_date'] . " 00:00:00"));
        }
        if ($data['e_date']) {
            $this->db->where('ti.add_time <=', strtotime($data['e_date'] . " 23:59:59"));
        }
        $rs_total = $this->db->get()->row();
        //总记录数
        $total_rows = $rs_total->num;
        $data['total_rows'] = $total_rows;
        //list
        $this->db->select('ti.*, us.rel_name us_rel_name_, us.mobile us_mobile_');
        $this->db->from('tongdun_info ti');
        $this->db->join('users us', 'ti.user_id = us.user_id', 'left');
        if ($data['keyword']) {
            $this->db->group_start();
            $this->db->like('ti.account_name', $data['keyword']);
            $this->db->or_like('ti.id_number', $data['keyword']);
            $this->db->group_end();
        }
        if ($data['keyword2']) {
            $this->db->group_start();
            $this->db->like('us.rel_name', $data['keyword2']);
            $this->db->or_like('us.mobile', $data['keyword2']);
            $this->db->group_end();
        }
        if ($data['s_date']) {
            $this->db->where('ti.add_time >=', strtotime($data['s_date'] . " 00:00:00"));
        }
        if ($data['e_date']) {
            $this->db->where('ti.add_time <=', strtotime($data['e_date'] . " 23:59:59"));
        }
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $this->db->order_by('ti.add_time', 'desc');
        $data['res_list'] = $this->db->get()->result_array();
        $td_deadline_ = $this->config->item('td_deadline'); //缓存数据使用限期,这里是秒为单位的
        foreach($data['res_list'] as $k_ => $item){
            if($item['add_time'] + $td_deadline_ < time()){
                $data['res_list'][$k_]['gq_flag'] = 1;   //过期标记位
            }else{
                $data['res_list'][$k_]['gq_flag'] = -1;  //过期标记位
            }
        }
        return $data;
    }

    //同盾数据详情
    public function tongdun_info_detail($id){
        $this->db->select('ti.*, us.rel_name us_rel_name_, us.mobile us_mobile_');
        $this->db->from('tongdun_info ti');
        $this->db->join('users us', 'ti.user_id = us.user_id', 'left');
        $this->db->where('id', $id);
        $data = $this->db->get()->row_array();
        return $data;
    }

}
