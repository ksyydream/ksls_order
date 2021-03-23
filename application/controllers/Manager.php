<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Manager extends MY_Controller {
    /**
     * 管理员操作控制器
     * @version 2.0
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-30
     * @Copyright (C) 2018, Tianhuan Co., Ltd.
    */
    private $admin_id = 0;
    private $role_id = 0;
	public function __construct()
    {
        parent::__construct();
        $this->load->model('manager_model');
        $this->load->model('common4manager_model', 'c4m_model');
        $admin_info = $this->session->userdata('admin_info');
        $admin = $this->manager_model->get_admin($admin_info['admin_id']);
        if(!$admin){
           $this->logout();
        }
        if($admin['status'] != 1){
            $this->logout();
        }
        $this->manager_model->save_admin_log($admin_info['admin_id']);
        $this->admin_id = $admin_info['admin_id'];
        $this->role_id = $admin['role_id'];
        if ($admin['group_id'] != 1 && !$this->manager_model->check($this->uri->segment(1) . '/' . $this->uri->segment(2), $admin_info['admin_id'])){
            if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            {
              //echo -99;exit();
                $err_ = $this->manager_model->fun_fail('你没有操作权限');
                $this->ajaxReturn($err_);
            }
            else {
                $this->show_message('没有权限访问本页面!');
            }
        }
        $this->assign('admin', $admin);
        $current = $this->manager_model->get_action_menu($this->uri->segment(1),$this->uri->segment(2));
        $this->assign('current', $current);
        $menu = $this->manager_model->get_menu4admin($admin_info['admin_id']);
        $menu = $this->getMenu($menu);
        $this->assign('menu', $menu);
        $this->assign('self_url',$_SERVER['PHP_SELF']);
    }

    protected function getMenu($items, $id = 'id', $pid = 'pid', $son = 'children')
    {
        $tree = array();
        $tmpMap = array();
        //修复父类设置islink=0，但是子类仍然显示的bug @感谢linshaoneng提供代码
        foreach( $items as $item ){
            if( $item['pid']==0 ){
                $father_ids[] = $item['id'];
            }
        }
        //----
        foreach ($items as $item) {
            $tmpMap[$item[$id]] = $item;
        }

        foreach ($items as $item) {
            //修复父类设置islink=0，但是子类仍然显示的bug by shaoneng @感谢linshaoneng提供代码
            if( $item['pid']<>0 && !in_array( $item['pid'], $father_ids )){
                continue;
            }
            //----
            if (isset($tmpMap[$item[$pid]])) {
                $tmpMap[$item[$pid]][$son][] = &$tmpMap[$item[$id]];
            } else {
                $tree[] = &$tmpMap[$item[$id]];
            }
        }
        return $tree;
    }

    /**
     *********************************************************************************************
     * 以下代码为看板模块
     *********************************************************************************************
     */

    /**
     * 看板
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-30
     */
    public function index()
	{
        $this->display('manager/index/index.html');
	}


    /**
     *********************************************************************************************
     * 以下代码为系统设置模块
     *********************************************************************************************
     */

    /**
     * 后台菜单列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function menu_list(){
        $menu_all = $this->manager_model->get_menu_all();
        $data['res_list'] = $this->getMenu($menu_all);
        $this->assign('data', $data);
        $this->display('manager/menu/index.html');
    }

    /**
     * 新增后台菜单页面
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function menu_add(){
        $menu_all = $this->manager_model->get_menu_all();
        $data['res_list'] = $this->getMenu($menu_all);
        $this->assign('data', $data);
        $this->display('manager/menu/form.html');
    }

    /**
     * 编辑后台菜单
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function menu_edit($id){
        $data = $this->manager_model->menu_info($id);
        if(!$data){
            $this->show_message('未找到菜单信息!');
        }
        $menu_all = $this->manager_model->get_menu_all();
        $data['res_list'] = $this->getMenu($menu_all);
        $this->assign('data', $data);
        $this->display('manager/menu/form.html');
    }

    /**
     * 保存后台菜单
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function menu_save(){
        $res = $this->manager_model->menu_save();
        if($res == 1){
            $this->show_message('保存成功!', site_url('/manager/menu_list'));
        }elseif($res == -2){
            $this->show_message('信息不全,保存失败!');
        }else{
            $this->show_message('保存失败!');
        }
    }

    /**
     * 删除后台菜单
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function menu_del($id){
        echo $this->manager_model->menu_del($id);
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
    public function admin_list($page=1){
        $data = $this->manager_model->admin_list($page);
        $base_url = "/manager/admin_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->display('manager/admin/index.html');
    }

    /**
     * 新增管理员管理
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function admin_add(){
        $groups = $this->manager_model->get_group_all();
        $work_role_list = $this->c4m_model->get_work_role(1);
        $this->assign('work_role_list', $work_role_list);
        $this->assign('r_list', array());
        $this->assign('data', array());
        $this->assign('groups', $groups);
        $this->display('manager/admin/form.html');
    }

    /**
     * 编辑管理员管理
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function admin_edit($id){
        $data = $this->manager_model->get_admin($id);
        if(!$data){
            $this->show_message('未找到管理员信息!');
        }
        $groups = $this->manager_model->get_group_all();
        $work_role_list = $this->c4m_model->get_work_role(1);
        $this->assign('work_role_list', $work_role_list);
        $r_list = $this->manager_model->get_admin_r_list($data['admin_id']);
        $this->assign('r_list', $r_list);
        $this->assign('data', $data);
        $this->assign('groups', $groups);
        $this->display('manager/admin/form.html');
    }

    /**
     * 保存管理员管理
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function admin_save(){
        $res = $this->manager_model->admin_save();
        if($res['status'] == 1){
            $this->show_message($res['msg'], site_url('/manager/admin_list'));
        }else{
            $this->show_message($res['msg']);
        }
    }

    /**
     * 删除管理员
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function admin_del($id){
        echo $this->manager_model->admin_del($id);
        $res = $this->manager_model->refresh_brand_password($this->admin_id);
        $this->ajaxReturn($res);
    }

    public function refresh_admin_status(){
        $res = $this->manager_model->refresh_admin_status($this->admin_id);
        $this->ajaxReturn($res);
    }

    /**
     * 新增用户组
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function group_add(){
        $group = array();
        $group['rules']=array(1,48,49,50,55);//默认选择 5个菜单
        $menu_all = $this->manager_model->get_menu_all();
        $menu_all = $this->getMenu($menu_all);
        $this->assign('rule', $menu_all);
        $this->assign('group', $group);
        $this->display('manager/group/form.html');
    }

    /**
     * 编辑用户组
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function group_edit($id){
        $group =  $this->manager_model->get_group_detail($id);
        if($group == -1){
            $this->show_message('未找到用户组信息!', site_url('/manager/group_list'));
        }
        $menu_all = $this->manager_model->get_menu_all();
        $menu_all = $this->getMenu($menu_all);
        $this->assign('rule', $menu_all);
        $this->assign('group', $group);
        $this->display('manager/group/form.html');
    }

    /**
     * 保存用户组
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function group_save(){
        $res = $this->manager_model->group_save();
        if($res == 1){
            $this->show_message('保存成功!',site_url('/manager/group_list'));
        }else{
            $this->show_message('保存失败!');
        }
    }

    /**
     * 用户组列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function group_list($page=1){
        $data = $this->manager_model->group_list($page);
        $base_url = "/manager/group_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->display('manager/group/index.html');
    }

    /**
     * 删除用户组
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-31
     */
    public function group_del($id){
        echo $this->manager_model->group_del($id);
    }

    /**
     * 个人资料页面
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function personal_info(){
        $data = $this->manager_model->get_admin($this->admin_id);
        if(!$data){
            $this->show_message('未找到信息!');
        }
        $this->assign('data', $data);
        $this->display('manager/personal/profile.html');
    }

    /**
     * 保存管理员管理
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function personal_save(){
        $res = $this->manager_model->personal_save($this->admin_id);
        if($res['status'] == 1){
            $this->show_message($res['msg'], site_url('/manager/personal_info'));
        }else{
            $this->show_message($res['msg']);
        }
    }

    /**
     * 退出
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-03-30
     */
    public function logout(){
        $this->session->sess_destroy();
        redirect(base_url('/manager_login/index'));
    }

    /**
     *********************************************************************************************
     * 门店模块
     *********************************************************************************************
     */

    /**
     * 大客户列表
     * @author yangyang
     * @date 2021-01-12
     */
    public function brand_list($page = 1){
        $data = $this->manager_model->brand_list($page);
        $base_url = "/manager/brand_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->display('manager/business/brand_list.html');
    }

    public function brand_add(){
        $this->display('manager/business/brand_detail.html');
    }

    public function brand_edit($id){
        $data = $this->manager_model->brand_edit($id);
        if(!$data){
            $this->show_message('未找到信息!');
        }
        $this->assign('data', $data);
        $this->display('manager/business/brand_detail.html');
    }

    public function brand_save(){
        $res = $this->manager_model->brand_save($this->admin_id);
        if($res['status'] == 1){
            $this->show_message($res['msg'], site_url('/manager/brand_list'));
        }else{
            $this->show_message($res['msg']);
        }
    }

    public function refresh_brand_password(){
        $res = $this->manager_model->refresh_brand_password($this->admin_id);
        $this->ajaxReturn($res);
    }

    /**
     * 门店列表
     * @author yangyang
     * @date 2021-01-12
     */
    public function store_list($page = 1){
        $data = $this->manager_model->store_list($page);
        $base_url = "/manager/store_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $brand_list = $this->manager_model->get_brand4select();
        $this->assign('brand_list', $brand_list);
        $this->display('manager/business/store_list.html');
    }

    public function store_add(){
        $brand_list = $this->manager_model->get_brand4select();
        $this->assign('brand_list', $brand_list);
        $this->display('manager/business/store_add.html');
    }

    public function store_edit($id){
        $data = $this->manager_model->store_edit($id);
        if(!$data){
            $this->show_message('未找到信息!');
        }
        $this->assign('data', $data);
        $this->display('manager/business/store_detail.html');
    }

    public function store_save(){
        $res = $this->manager_model->store_save($this->admin_id);
        if($res['status'] == 1){
            $this->show_message($res['msg'], site_url('/manager/store_list'));
        }else{
            $this->show_message($res['msg']);
        }
    }



    /**
     * 会员列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function users_list($page = 1){
        $data = $this->manager_model->users_list($page);
        $base_url = "/manager/users_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->display('manager/users/index.html');
    }


    /**
     * 会员详情
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-07-22
     */
    public function users_edit($user_id){
        $data = $this->manager_model->users_edit($user_id);
        if(!$data){
            $this->show_message('未找到会员信息!');
        }
        $this->assign('data', $data);
        $this->display('manager/users/form.html');
    }

    /**
     * 保存会员
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2019-07-22
     */
    public function users_save(){
        $res = $this->manager_model->users_save();
        if($res['status'] == 1){
            $this->show_message('保存成功!', site_url('/manager/users_list'));
        }else{
            $this->show_message($res['msg']);
        }
    }

    public function refresh_users_password(){
        $res = $this->manager_model->refresh_users_password($this->admin_id);
        $this->ajaxReturn($res);
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
        $data = $this->manager_model->contract_list($page);
        $base_url = "/manager/contract_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->display('manager/loan/contract_list.html');
    }

    public function contract_add(){
        $this->display('manager/loan/contract_detail.html');
    }

    public function contract_edit($id){
        $data = $this->manager_model->contract_edit($id);
        if(!$data){
            $this->show_message('未找到信息!');
        }
        $this->assign('data', $data);
        $this->display('manager/loan/contract_detail.html');
    }

    public function contract_save(){
        $res = $this->manager_model->contract_save($this->admin_id);
        if($res['status'] == 1){
            $this->show_message($res['msg'], site_url('/manager/contract_list'));
        }else{
            $this->show_message($res['msg']);
        }
    }

    /**
     * 监管项目
     * @author yangyang
     * @date 2021-02-19
     */

    public function supervise_list($page = 1){
        $data = $this->manager_model->supervise_list($page);
        $base_url = "/manager/supervise_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->display('manager/loan/supervise_list.html');
    }

     /**
     * 赎楼申请单列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-04-01
     */
    public function loan_list($page = 1){
        $data = $this->manager_model->loan_list($page);
        $base_url = "/manager/loan_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $brand_list = $this->manager_model->get_brand4select();
        $this->assign('brand_list', $brand_list);
        $this->display('manager/loan/loan_list.html');
    }

    public function loan_edit($loan_id){
        $data = $this->manager_model->loan_edit($loan_id);
        if($data["status"] != 1){
            $this->show_message('未找到信息!');
        }
        $this->assign('data', $data["result"]);
        $this->display('manager/loan/loan_detail.html');
	}

    /**
     * 赎楼申请单列表(风控专属)
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2021-02-22
     */
    public function loan4fk_list($page = 1){
        $where = array('a.fk_admin_id' => $this->admin_id);
        $data = $this->manager_model->loan_list($page, $where);
        $base_url = "/manager/loan_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $brand_list = $this->manager_model->get_brand4select();
        $this->assign('brand_list', $brand_list);
        $this->display('manager/loan/loan_list.html');
    }

    /**
     * 赎楼申请单详情(风控专属)
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2021-02-22
     */
    public function loan4fk_edit($loan_id){
        $where = array('a.fk_admin_id' => $this->admin_id);
        $data = $this->manager_model->loan_edit($loan_id, $where);
        if($data["status"] != 1){
            $this->show_message('未找到信息!');
        }
        $this->assign('data', $data["result"]);
        $this->display('manager/loan/loan_detail.html');
    }

    //保存风控报告
    public function save_fk_report(){
        $rs = $this->manager_model->save_fk_report($this->admin_id,$this->role_id);
        $this->ajaxReturn($rs);
    }

    //修改面签经理
    public function mx_admin_change4loan(){
        $this->load->model('loan_model');
        $rs = $this->loan_model->mx_admin_change4loan($this->admin_id,$this->role_id);
        $this->ajaxReturn($rs);
    }

    //修改风控经理
    public function fk_admin_change4loan(){
        $this->load->model('loan_model');
        $rs = $this->loan_model->fk_admin_change4loan($this->admin_id,$this->role_id);
        $this->ajaxReturn($rs);
    }

    //修改权证(银行)经理
    public function qz_admin_change4loan(){
        $this->load->model('loan_model');
        $rs = $this->loan_model->qz_admin_change4loan($this->admin_id,$this->role_id);
        $this->ajaxReturn($rs);
    }

    //修改权证(交易中心)经理
    public function fc_admin_change4loan(){
        $this->load->model('loan_model');
        $rs = $this->loan_model->fc_admin_change4loan($this->admin_id,$this->role_id);
        $this->ajaxReturn($rs);
    }

    //修改工作流
    public function status_change4loan(){
        $this->load->model('loan_model');
        $rs = $this->loan_model->status_change4loan($this->admin_id,$this->role_id);
        $this->ajaxReturn($rs);
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
        $data = $this->manager_model->sms_list($page, 1);
        $base_url = "/manager/sms_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->display('manager/log_list/sms_list.html');
    }

    /**
     * 同盾日志列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2019-07-23
     */
    public function tongdun_log_list($page = 1){
        $data = $this->manager_model->tongdun_log_list($page, 1);
        $base_url = "/manager/tongdun_log_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->display('manager/log_list/tongdun_log_list.html');
    }

    /**
     * 同盾数据列表
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2019-07-23
     */
    public function tongdun_info_list($page = 1){
        $data = $this->manager_model->tongdun_info_list($page, 1);
        $base_url = "/manager/tongdun_info_list/";
        $pager = $this->pagination->getPageLink4manager($base_url, $data['total_rows'], $data['limit']);
        $this->assign('pager', $pager);
        $this->assign('page', $page);
        $this->assign('data', $data);
        $this->display('manager/log_list/tongdun_info_list.html');
    }

    public function tongdun_info_detail($id){
        $data = $this->manager_model->tongdun_info_detail($id);
        if(!$data){
            $this->show_message('未找到同盾信息!');
        }
        $this->assign('data', $data);
        $this->display('manager/log_list/tongdun_info_detail.html');
    }

}
