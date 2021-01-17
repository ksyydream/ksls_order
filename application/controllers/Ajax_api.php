<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ajax_api extends CI_Controller {

    /**
     * Ajax控制器
     * @version 1.0
     * @author yaobin <bin.yao@thmarket.cn>
     * @date 2017-12-20n
     * @Copyright (C) 2017, Tianhuan Co., Ltd.
     */
	public function __construct()
    {
        parent::__construct();
        ini_set('date.timezone','Asia/Shanghai');
        $this->load->library('image_lib');
        $this->load->helper('directory');
        $this->load->model('manager_model');
    }


    /**
     * 上传头像
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function upload_head(){
        $admin_info = $this->session->userdata('admin_info');
        if(!$admin_info){
            echo -1;//如果没有登陆 不可上传,以免有人恶意上传图片占用服务器资源
        }
        $dir = FCPATH . '/upload_files/head';
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        $config['upload_path'] = './upload_files/head/';
        $config['allowed_types'] = 'gif|jpg|png|jpeg';
        $config['encrypt_name'] = true;
        $config['max_size'] = '3200';
        $this->load->library('upload', $config);

        if ( ! $this->upload->do_upload('userfile')){
            echo 1;
        }else{
            $pic_arr = $this->upload->data();
            echo $pic_arr['file_name'];
        }
    }

    /**
     * 上传头像
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_car_city(){
        $province = $this->input->post('province');
        $res = $this->manager_model->get_car_city(trim($province));
        echo json_encode($res);
        die;
    }

    /**
     * 获取散单商品明细
     * @author ChenYizhe
     * @date 2018-11-20
     */
    public function get_goodsBySid(){
        $swork_id = $this->input->post('swork_id');
        $data = $this->manager_model->get_goodsBySid($swork_id);
        echo json_encode($data);
        die;
    }

    /**
     * 添加散单作业人
     * @author ChenYizhe
     * @date 2018-11-23
     */
    public function add_workers(){
        $swork_id = $this->input->post('swork_id');
        $workers = $this->input->post('workers');
        $data = $this->manager_model->add_workers($swork_id,$workers);
        echo json_encode($data);
        die;
    }



    /**
     * 注册所使用的短信验证
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_register_code($phone){
        $check = $this->manager_model->get_userByPhone(trim($phone));
        if($check){
            echo -2;
        }else{
            $this->get_phone_code(trim($phone));
        }
        die;
    }

    /**
     * 司机审核认证所使用的短信验证
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-5-31
     */
    public function get_driver_apply_code($phone){
        $this->load->model('driver_model');
        $check = $this->driver_model->get_applyByPhone(trim($phone));
        if($check){
            echo -2;
        }else{
            $this->get_phone_code(trim($phone));
        }
        die;
    }

    /**
     * 修改信息所使用的短信验证
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_edit_code($phone){
        $check = $this->manager_model->get_userByPhone(trim($phone));
        if($check){
            if($check['id'] != $this->session->userdata('driver_id')){
                echo -2;exit();
            }
        }
        $this->get_phone_code(trim($phone));
        die;
    }

    /**
     * 登陆所使用的短信验证
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_login_code($phone){
        $check = $this->manager_model->get_userByPhone(trim($phone));
        if($check){
            if($check['status'] != 1){
                echo -4;//此会员已经禁用
                exit();
            }
            if($check['type'] != 1){
                echo -3;//此会员不是认证司机
                exit();
            }
            $this->get_phone_code(trim($phone));
        }else{
            echo -2;
        }
        die;
    }

    /**
     * 商户登陆所使用的短信验证
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_login_code4business($phone){
        $check = $this->manager_model->get_businessByPhone(trim($phone));
        if($check == 1){
            $this->get_phone_code(trim($phone));
        }else{
            echo $check;
        }
        die;
    }

    /**
     * 获取月台列表,通过仓库号和作业目的
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_locationByWid(){
        $location = $this->manager_model->get_locationByWid();
        echo json_encode($location);
    }

    /**
     * 获取库位楼层,通过仓库号
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function get_kwByWid(){
        $kw = $this->manager_model->get_kwByWid();
        echo json_encode($kw);
    }

    /**
     * API接口,用于分配月台,处理
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-3-31
     */
    public function api_distribution(){
        $show_msg =  $this->system_model->distribution(1,'','');
        die(var_dump($show_msg));
        echo json_encode(array('errcode' => 0, 'errmsg' => "分配成功", 'mengang' => '', 'data' => $show_msg));
    }

    /**
     * API接口，接收软杰的数据
     * @author yaobin <bin.yao@thmarket.cn>
     * @date 2018-4-10
     */
    public function receive_rj_api(){
        $type = $this->input->post('type');//1车辆入园2提交车辆信息
        if($type < 3){
            if($type == 2){
                if($car_no_ = $this->input->post('car_no')){
                    $this->system_model->car_out($car_no_, 1);
                }
            }
            if($type == 1)
                $this->system_model->save_car_in_tmp();
            $mengang = $this->input->post('LEDIPAddress');
            $rs = $this->system_model->distribution($type,'','');
            if(is_array($rs)){
                $data = json_encode($rs);
                echo json_encode(array('errcode' => 0, 'errmsg' => "分配成功", 'mengang' => $mengang, 'data' => $data));
                exit;
            }
            echo $rs;
        }elseif ($type == 3){//3车辆出园
            if(!$this->input->post('car_no')){
                echo json_encode(array('errcode' => 102, 'errmsg' => "车牌不能为空"));
                exit;
            }
            $rs = $this->system_model->car_out($this->input->post('car_no'));
            if(!$rs)
                echo json_encode(array('errcode' => 87, 'errmsg' => "出园失败"));
            else
                echo json_encode(array('errcode' => 0, 'errmsg' => "出园成功"));
            exit;
        }else{
            echo json_encode(array('errcode' => 80, 'errmsg' => "状态不存在"));
        }
    }

    /**
     * API接口，接收微信端门岗登记数据
     * @author yaobin <bin.yao@thmarket.cn>
     * @date 2018-4-10
     */
    public function receive_wechat_api(){
        if($car_no_ = $this->input->post('car_no')){
            $this->system_model->car_out($car_no_, 1);
        }
        $rs = $this->system_model->distribution(2);
        $mengang = $this->input->post('LEDIPAddress');
        if(is_array($rs)){
            $data = $rs;
            $post_data = json_encode(array(
                'CarNo' => $this->input->post('car_no'),
                'LEDIPAddress' => $mengang,
                'LEDContents' => $data
            ), JSON_UNESCAPED_UNICODE);

            $url = "http://172.16.1.11/TianHuanAPI/Device/CarRegisteredNotification";
//            $url = '';
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,$url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "data=".$post_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//            curl_exec ($ch);
            $response = curl_exec ($ch);
            curl_close ($ch);
            $this->system_model->save_rj_api_log(json_encode($post_data),$response);
            echo json_encode($data);
            exit;
        }
        echo $rs;
    }

    //月台释放
    public function release_location($w_id, $location){
        $rs = $this->system_model->distribution3($w_id, $location);
        if(is_array($rs)){
            //TODO推送消息
            echo json_encode(array('errmsg'=>'释放成功','errcode'=>0));
        }else{
            echo $rs;
        }
    }

    //月台占用
    public function occupy_location($w_id, $location, $car_no = '', $driver_status = -1){
        $car_no = urldecode($car_no);
        $rs = $this->system_model->occupy_location($w_id, $location, $car_no, $driver_status);
        if($rs){
            echo json_encode(array('errmsg'=>'占用成功','errcode'=>0));
        }else{
            echo json_encode(array('errmsg'=>'占用失败','errcode'=>77));
        }
    }

    public function open_index(){
        $this->cismarty->display('manager/line_location/index2.html');
    }

    public function test2(){
        //$this->system_model->wxpost_sys_business_bespeak('苏E5G52G',1,'44');
    }

    public function create_monthly_bill(){
        //die();
       // exit();
        $this->ajax_api_model->create_monthly_bill();
    }

    public function create_monthly_bill4test(){
        die();
        exit();
        $this->ajax_api_model->create_monthly_bill4test();
    }

    /**
     * 生成大屏显示的数据
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-05-10
     */
    public function show_data(){
        header("Access-Control-Allow-Origin: *");
        $rs = $this->ajax_api_model->show_data();
        echo json_encode($rs);
    }

    /**
     * 保存大屏显示的数据
     * @author yangyang <yang.yang@thmarket.cn>
     * @date 2018-05-10
     */
    public function save_history_atio(){
        $rs = $this->ajax_api_model->save_history_atio();
        echo json_encode($rs);
    }

    public function save_business_work(){
        echo $this->ajax_api_model->save_business_work();
    }

    public function wx_notify(){
        $this->load->config('wxpay_config');
        $wx_config = array();
        $wx_config['appid']=$this->config->item('appid');
        $wx_config['mch_id']=$this->config->item('mch_id');
        $wx_config['apikey']=$this->config->item('apikey');
        $wx_config['appsecret']=$this->config->item('appsecret');
        $wx_config['sslcertPath']=$this->config->item('sslcertPath');
        $wx_config['sslkeyPath']=$this->config->item('sslkeyPath');
        $this->load->library('wxpay/Wechatpay',$wx_config);
        $data_array = $this->wechatpay->get_back_data();
        if($data_array['result_code']=='SUCCESS' && $data_array['return_code']=='SUCCESS'){
            if($this->ajax_api_model->wx_change_order($data_array['out_trade_no']) != 1){
                return 'FAIL';
            }else{
                return 'SUCCESS';
            }
        }
    }

    public function create_work_order(){
        die();
        exit();
        $rs = $this->ajax_api_model->create_work_order();
        echo json_encode($rs);
    }

    public function create_info($flag){
        exit();
        die();
        //set_time_limit(0);
        $this->manager_model->create_info($flag);
    }

}
