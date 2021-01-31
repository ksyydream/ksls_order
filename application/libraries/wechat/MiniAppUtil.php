<?php
require_once(APPPATH ."libraries/wechat/WxCommon.php");
require_once(APPPATH ."libraries/wechat/WxBizDataCrypt.php");
/**
 * 小程序官方接口类
 */
class MiniAppUtil extends WxCommon
{
    private $config = []; //小程序配置

    public function __construct($config = null)
    {

        $this->config = $config;
    }
    

    public function getWxUserInfo($code , $iv , $encryptedData , &$data){
            try{
                $appid = $this->config['appid'];
                $session = $this->getSessionInfo($code);
		        if ($session === false) {
                    exit(json_encode(array('status' => -100,'msg' =>$this->getError(), 'result' => ''), JSON_UNESCAPED_UNICODE));

                }
                $sessionKey = $session['session_key'];
                $pc = new WxBizDataCrypt($appid, $sessionKey);
                $errCode = $pc->decryptData($encryptedData, $iv, $data);
                return $errCode;
            }catch(Exception $e)
            {
                exit(json_encode(array('status' => -100,'msg' =>'请求异常', 'result' => ''), JSON_UNESCAPED_UNICODE));
            }
    }

    /**
     * 获取小程序session信息
     * @param string $code 登录码
     */
    public function getSessionInfo($code)
    {
        $appId = $this->config['appid'];
        $appSecret = $this->config['appsecret'];
        if (!$appId || !$appSecret) {
            $this->setError('请检查后台是否配置appid和appsecret');
            return false;
        }
        
        $fields = [
            'appid' => $appId,
            'secret' => $appSecret,
            'js_code' => $code,
            'grant_type' => 'authorization_code'
        ];
        $url = 'https://api.weixin.qq.com/sns/jscode2session';
        $return = $this->requestAndCheck($url, 'GET', $fields);
        if ($return === false) {
            $this->setError('小程序登录失败, errcode : '.$return['errcode'].', errmsg : '.$return['errmsg']);
            return false;
        }
        return $return;
    }
    
}