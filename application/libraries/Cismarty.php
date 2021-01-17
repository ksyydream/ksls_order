<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/13
 * Time: 16:58
 */

require_once( APPPATH . 'libraries/Smarty/libs/Smarty.class.php' );

class Cismarty extends Smarty {
    protected $ci;
    public function  __construct(){
        $this->ci = & get_instance();
        $this->ci->load->config('smarty');
        $this->template_dir   = $this->ci->config->item('template_dir');
        $this->compile_dir    = $this->ci->config->item('compile_dir');
        $this->cache_dir      = $this->ci->config->item('cache_dir');
        $this->config_dir     = $this->ci->config->item('config_dir');
        $this->template_ext   = $this->ci->config->item('template_ext');
        $this->caching        = $this->ci->config->item('caching');
        $this->cache_lifetime = $this->ci->config->item('lefttime');
        $this->left_delimiter = $this->ci->config->item('left_delimiter');
        $this->right_delimiter = $this->ci->config->item('right_delimiter');
    }
}