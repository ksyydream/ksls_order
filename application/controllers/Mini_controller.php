<?php
/**
 * Created by PhpStorm.
 * User: bin.shen
 * Date: 5/31/16
 * Time: 16:23
 */

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Mini_controller extends MY_Controller
{
    protected $wxconfig = array();
    public $token = '';
    public function __construct()
    {
        parent::__construct();
        ini_set('date.timezone','Asia/Shanghai');
        $this->load->model('sys_model');
        $this->load->helper('url');
        $this->wxconfig['appid']=$this->config->item('mini_appid');
        $this->wxconfig['appsecret']=$this->config->item('mini_appsecret');
        $this->checkToken(); // 检查token

    }

    /**
     * 校验token
     */
    public function checkToken()
    {
        $this->token = $this->input->get('token'); // token



    }

    //重载smarty方法assign
    public function assign($key,$val) {
        $this->cismarty->assign($key,$val);
    }

    //重载smarty方法display
    public function display($html) {
        $this->cismarty->display($html);
    }

    //public function get_mini_openid($code){
       //$this->load->library('wechat/MiniAppUtil',array('appid' => $this->config->item('mini_appid'), 'appsecret' => $this->config->item('mini_appsecret')));
        //$miniapp = $this->miniAppUtil->getSessionInfo($code);
        //$session = $miniapp->getSessionInfo($code);
    //}

    public function set_base_code($token){
        require_once (APPPATH . 'libraries/Base64.php');
        try{
            $token = base64_decode($token);
            $token = base64::decrypt($token, $this->config->item('token_key'));
            $token = explode('_', $token);
            if($token[0]!= 'FIN') return -1;
            $t = time() - $token[2];
            if($t >= 60 * 60) return -2;
        }catch(Exception $e){
            return -3;
        }
        return (int)$token[1];
    }

    public function get_header_token(){
        if (function_exists('getallheaders')){
            foreach (getallheaders() as $name => $value) {
                if($name == 'Token'){
                    return $value;
                }
            }
            return -1;
        }else{
            $hears_ = $this->getallheaders4nginx();
            foreach ($hears_ as $name => $value) {
                if($name == 'Token'){
                    return $value;
                }
            }
            return -1;
        }

    }

    public function getallheaders4nginx()
    {
        $headers = array ();
        foreach ($_SERVER as $name => $value)
        {
            if (substr($name, 0, 5) == 'HTTP_')
            {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    public function set_token_uid($uid,$role_name){
        require_once(APPPATH ."libraries/Base64.php");
        $uid = $role_name . '_' .$uid.'_'.time();
        $uid = Base64::encrypt($uid, $this->config->item('token_key'));
        return base64_encode($uid);
    }

    public function get_token_uid($token,$role_name){
        $token = base64_decode($token);
        require_once(APPPATH ."libraries/Base64.php");
        $token = Base64::decrypt($token, $this->config->item('token_key'));
        $token = explode('_', $token);
        if($token[0]!= $role_name) return 0;
        return (int)$token[1];
    }




    public function buildWxData(){
        $this->load->library('wxjssdk_th',array('appid' => $this->config->item('appid'), 'appsecret' => $this->config->item('appsecret')));
        $signPackage = $this->wxjssdk_th->wxgetSignPackage();
        //变量
        $this->cismarty->assign('wxappId',$signPackage["appId"]);
        $this->cismarty->assign('wxtimestamp',$signPackage["timestamp"]);
        $this->cismarty->assign('wxnonceStr',$signPackage["nonceStr"]);
        $this->cismarty->assign('wxsignature',$signPackage["signature"]);
    }

    public function getUserInfoById($uid, $lang = 'zh_CN') {
        $this->load->library('wxjssdk_th',array('appid' => $this->config->item('appid'), 'appsecret' => $this->config->item('appsecret')));
        $access_token = $this->wxjssdk_th->wxgetAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$uid&lang=$lang";
        $res = json_decode($this->request_post($url), true);
        $check_ = $this->checkIsSuc($res);

        if($check_){
            return $res;
        }else{
            return null;
        }
    }

    function request_post($url = '', $param = '')
    {
        $postUrl = $url;
        $curlPost = $param;
        $ch = curl_init(); //初始化curl
        curl_setopt($ch, CURLOPT_URL, $postUrl); //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0); //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1); //post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch); //运行curl
        curl_close($ch);
        return $data;
    }

    public function checkIsSuc($res) {
        $result = true;
        if (is_string($res)) {
            $res = json_decode($res, true);
        }
        if (isset($res['errcode']) && ( 0 !== (int) $res['errcode'])) {
            $result = false;
        }
        return $result;
    }

    public function get_or_create_ticket($access_token,$action_name = 'QR_STR_SCENE', $scene_str = '') {
        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $access_token;
        @$post_data->expire_seconds = 2592000;
        @$post_data->action_name = $action_name;
        $invite_code = $this->input->get('invite_code_temp');
        if(!isset($invite_code)){
            $invite_code = '';
        }
        if($scene_str != '')
            $invite_code = $scene_str;
        @$post_data->action_info->scene->scene_str = $invite_code;
        $ticket_data = json_decode($this->post($url, $post_data));
        @$ticket = $ticket_data->ticket;
        $img_url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".urlencode($ticket);
        return $img_url;
    }

    private function post($url, $post_data, $timeout = 300){
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/json;encoding=utf-8',
                'content' => urldecode(json_encode($post_data)),
                'timeout' => $timeout
            )
        );
        $context = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }

}