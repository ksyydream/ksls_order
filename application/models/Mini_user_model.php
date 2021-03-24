<?php
/**
 * Created by PhpStorm.
 * User: yangyang
 * Date: 16/6/3
 * Time: 下午3:22
 */
class Mini_user_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    public function check_token($token, $user_id = 0){
        $user_info_ = $this->db->select()->from('users')->where(array('token' => $token))->get()->row_array();
        if(!$user_info_){
            return array('status' => -100, 'msg' => '未找到登录信息!', "result" => '');
        }
        if(time() - $user_info_['mini_last_login'] > 60 * 60 * 12){
            return array('status' => -101, 'msg' => '请登录!', "result" => '');
        }
        if($user_id != $user_info_['user_id']){
            return array('status' => -101, 'msg' => '异常!', "result" => '');
        }
        if($user_info_['status'] != 1){
            return array('status' => -101, 'msg' => '账号异常!', "result" => '');
        }
        //这里多效验一步 大客户品牌状态 先不做判断
        if($user_info_['brand_id']){
            $brand_info = $this->readByID("brand", 'id', $user_info_['brand_id']);
            if($brand_info && $brand_info['status'] != 1){
                     //return array('status' => -102, 'msg' => '大客户状态异常!', "result" => '');
			}
        }
       
        return $this->fun_success('登录成功',$user_info_);
    }

    public function get_user_info($user_id){
        $row = $this->db->select(" us.rel_name, us.brand_id, b.brand_name, us.other_brand, us.shop_name, us.store_id, s.store_name")
            ->from('users us')
            ->join('brand b','us.brand_id = b.id','left')
            ->join('brand_stores s','us.store_id = s.store_id','left')
            ->where(array('user_id' => $user_id))->get()->row_array();
        return $this->fun_success('获取成功',$row);
    }

    public function update_user_tt($user_id,$token = ''){
        $update_data = array('mini_last_login' => time());
        if($token){
            $update_data['token'] = $token;
        }
        $this->db->where('user_id', $user_id)->update('users', $update_data);
    }

    //门店账号列表 因为大客户账号 和 管理员账号均可能需要调用,所以写成公用的
    public function user_list($where){
        $res = array();
        $data['limit'] = $this->mini_limit;//每页显示多少调数据
        $data['keyword'] = $this->input->post('keyword')?trim($this->input->post('keyword')):null;
        $data['brand_id'] = $this->input->post('brand_id')?trim($this->input->post('brand_id')):null;
        $data['store_id'] = $this->input->post('store_id')?trim($this->input->post('store_id')):null;
        $data['status'] = $this->input->post('status')?trim($this->input->post('status')):null;
        $page = $this->input->post('page')?trim($this->input->post('page')):1;
        $this->db->select('count(a.user_id) num');
        $this->db->from('users a');
        $this->db->join('brand b', 'a.brand_id = b.id', 'left');
        $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('a.rel_name', $data['keyword']);
            $this->db->or_like('a.mobile', $data['keyword']);
            $this->db->or_like('a.shop_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['status']){
            $this->db->where('a.status', $data['status']);
        }
        if($data['brand_id']){
            $this->db->where('a.brand_id', $data['brand_id']);
        }
        if($data['store_id']){
            $this->db->where('a.store_id', $data['store_id']);
        }
        $num = $this->db->get()->row();
        $res['total_rows'] = $num->num;
        $res['total_page'] = ceil($res['total_rows'] / $data['limit']);

        $this->db->select('a.user_id,a.rel_name,a.mobile,a.other_brand,a.shop_name,b.brand_name,a.status user_status,b.status brand_status,a.store_id,s.store_name');
        $this->db->from('users a');
        $this->db->join('brand b', 'a.brand_id = b.id', 'left');
        $this->db->join('brand_stores s', 'a.store_id = s.store_id', 'left');
        $this->db->where($where);
        if($data['keyword']){
            $this->db->group_start();
            $this->db->like('a.rel_name', $data['keyword']);
            $this->db->or_like('a.mobile', $data['keyword']);
            $this->db->or_like('a.shop_name', $data['keyword']);
            $this->db->group_end();
        }
        if($data['status']){
            $this->db->where('a.status', $data['status']);
        }
        if($data['brand_id']){
            $this->db->where('a.brand_id', $data['brand_id']);
        }
        if($data['store_id']){
            $this->db->where('a.store_id', $data['store_id']);
        }
        $this->db->order_by('a.reg_time', 'desc');
        $this->db->limit($data['limit'], $offset = ($page - 1) * $data['limit']);
        $res['res_list'] = $this->db->get()->result_array();
        return $this->fun_success('获取成功', $res);
    }

    //修改个人信息
    public function save_user_info($user_id){
        $user_data = array(
            'rel_name' => trim($this->input->post('rel_name')),
            'brand_id' => trim($this->input->post('brand_id')) ? trim($this->input->post('brand_id')) : -1,
            'store_id' => trim($this->input->post('store_id')) ? trim($this->input->post('store_id')) : -1,
            'invite' => trim($this->input->post('invite')) ? trim($this->input->post('invite')) : null,
            'shop_name' => trim($this->input->post('shop_name')),
            'other_brand' => trim($this->input->post('other_brand')) ? trim($this->input->post('other_brand')) : '',
        );
        if(!$user_data['rel_name']){
            return $this->fun_fail('姓名不能为空!');
        }
        if(!$user_data['shop_name']){
            return $this->fun_fail('门店地址不能为空!');
        }
        if($user_data['brand_id'] == -1){
            if(!$user_data['other_brand']){
                return $this->fun_fail('品牌不能为空!');
            }
            if(!$user_data['shop_name'])
                return $this->fun_fail('门店地址不能为空!');
        }else{
            $brand_info_ = $this->db->select()->from('brand')->where(array('id' => $user_data['brand_id'], 'status' => 1))->get()->row_array();
            if(!$brand_info_)
                return $this->fun_fail('所选品牌无效!');
            $user_data['other_brand'] = '';
            if($user_data['store_id'] == -1){
                if(!$user_data['shop_name'])
                    return $this->fun_fail('门店地址不能为空!');
            }else{
                $store_info_ = $this->db->select()->from('brand_stores')->where(array('store_id' => $user_data['store_id'], 'status' => 1))->get()->row_array();
                if(!$store_info_)
                    return $this->fun_fail('所选门店二级无效!');
            }
        }
        $user_info_ = $this->db->select()->from('users')->where('user_id', $user_id)->get()->row_array();
        if($user_info_['invite']){
            unset($user_data['invite']);
        }else{
            if($user_data['invite']){
                $check_invite_ = $this->db->select()->from('admin')->where(array('admin_id' => $user_data['invite'], 'status' => 1))->get()->row_array();
                if(!$check_invite_)
                    return $this->fun_fail('此邀请码不可使用!');
            }
        }
        if($user_info_ && $user_info_['brand_id'] != $user_data['brand_id']){
            $check_ = $this->db->select('loan_id')->from('loan_master')->where(array('flag' => 1))->get()->row_array();
            if($check_){
                return $this->fun_fail('存在未处理的申请单,不可修改大客户品牌!');
            }
        }else{
            //以防万一 还是去除
            unset($user_data['brand_id']);
        }
        $this->db->where('user_id', $user_id)->update('users',$user_data);
        return $this->fun_success('操作成功');
    }

    public function change_password($user_id){
        $new_password = $this->input->post('new_password') ? trim($this->input->post('new_password')) : '';
        if(!$new_password)
            return $this->fun_fail('新密码不能为空!');
        if(strlen($new_password) < 6)
            return $this->fun_fail('新密码长度不能小于6位!');
        if(!ctype_alnum($new_password))
            return $this->fun_fail('新密码只能为字母和数字!');
        $this->db->where(array('user_id' => $user_id))->update('users', array('password' => sha1($new_password)));
        return $this->fun_success('修改成功');
    }


}