<?php
/**
 * 返回AJAX提交表单后的JSON
 * $callbackType 默认的参数"closeCurrent"可以用于关闭当前窗体，'forward'跳转到$forwardUrl的网址。
 * 成功返回格式：{"statusCode":"200", "message":"操作成功", "navTabId":"navNewsLi", "forwardUrl":"", "callbackType":"closeCurrent"}
 * 失败返回格式:{"statusCode":"300", "message":"操作失败"}
 */
function form_submit_json($statusCode,$message,$navTabId="",$forwardUrl="",$callbackType="closeCurrent"){
    $returnType['statusCode'] =  $statusCode;
    $returnType['message'] = $message;
    $returnType['navTabId'] = $navTabId;
    $returnType['forwardUrl'] = $forwardUrl;
    $returnType['callbackType'] = $callbackType;
    echo (json_encode($returnType));
}

/**
 * 检查手机号码格式
 * @param $mobile 手机号码
 */
function check_mobile($mobile){
    if(preg_match('/1[0-9]\d{9}$/',$mobile))
        return true;
    return false;
}

//验证身份证
function is_idcard( $id )
{
    $id = strtoupper($id);
    $regx = "/(^\d{15}$)|(^\d{17}([0-9]|X)$)/";
    $arr_split = array();
    if(!preg_match($regx, $id))
    {
        return false;
    }
    if(15==strlen($id)) //检查15位
    {
        $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";

        @preg_match($regx, $id, $arr_split);
        //检查生日日期是否正确
        $dtm_birth = "19".$arr_split[2] . '/' . $arr_split[3]. '/' .$arr_split[4];
        if(!strtotime($dtm_birth))
        {
            return false;
        } else {
            return TRUE;
        }
    }
    else      //检查18位
    {
        $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
        @preg_match($regx, $id, $arr_split);
        $dtm_birth = $arr_split[2] . '/' . $arr_split[3]. '/' .$arr_split[4];
        if(!strtotime($dtm_birth)) //检查生日日期是否正确
        {
            return false;
        }
        else
        {
            //检验18位身份证的校验码是否正确。
            //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
            $arr_int = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
            $arr_ch = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
            $sign = 0;
            for ( $i = 0; $i < 17; $i++ )
            {
                $b = (int) $id{$i};
                $w = $arr_int[$i];
                $sign += $b * $w;
            }
            $n = $sign % 11;
            $val_num = $arr_ch[$n];
            if ($val_num != substr($id,17, 1))
            {
                return false;
            } //phpfensi.com
            else
            {
                return TRUE;
            }
        }
    }

}

function create_captcha($data = '', $font_path = '')

{

    $defaults = array('word' => '', 'word_length' => 4, 'font_path'	=> '../../system/fonts/texb.ttf', 'img_width' => '150', 'img_height' => '30', 'expiration' => 7200);



    foreach ($defaults as $key => $val)

    {

        if ( ! is_array($data))

        {

            if ( ! isset($$key) OR $$key == '')

            {

                $$key = $val;

            }

        }

        else

        {

            $$key = ( ! isset($data[$key])) ? $val : $data[$key];

        }

    }

    // Do we have a "word" yet?

    // -----------------------------------
    $now = microtime(TRUE);


    if ($word == '')

    {

        $pool = '0123456789';



        $str = '';

        for ($i = 0; $i < $word_length; $i++)

        {

            $str .= substr($pool, mt_rand(0, strlen($pool) -1), 1);

        }



        $word = $str;

    }



    // -----------------------------------

    // Determine angle and position

    // -----------------------------------



    $length        = strlen($word);

    $angle        = ($length >= 6) ? rand(-($length-6), ($length-6)) : 0;

    $x_axis        = rand(6, (360/$length)-16);

    $y_axis = ($angle >= 0 ) ? rand($img_height, $img_width) : rand(6, $img_height);



    // -----------------------------------

    // Create image

    // -----------------------------------



    // PHP.net recommends imagecreatetruecolor(), but it isn't always available

    if (function_exists('imagecreatetruecolor'))

    {

        $im = imagecreatetruecolor($img_width, $img_height);

    }

    else

    {

        $im = imagecreate($img_width, $img_height);

    }



    // -----------------------------------

    //  Assign colors

    // -----------------------------------



    $bg_color                = imagecolorallocate ($im, 255, 255, 255);

    $border_color        = imagecolorallocate ($im, 153, 102, 102);

    //$text_color                = imagecolorallocate ($im, 204, 153, 153);
    $text_color                = imagecolorallocate ($im, 0, 0, 0);
    $grid_color                = imagecolorallocate($im, 255, 182, 182);

    $shadow_color        = imagecolorallocate($im, 255, 240, 240);



    // -----------------------------------

    //  Create the rectangle

    // -----------------------------------



    ImageFilledRectangle($im, 0, 0, $img_width, $img_height, $bg_color);



    // -----------------------------------

    //  Create the spiral pattern

    // -----------------------------------



    $theta                = 1;

    $thetac                = 7;

    $radius                = 16;

    $circles        = 20;

    $points                = 32;



    for ($i = 0; $i < ($circles * $points) - 1; $i++)

    {

        $theta = $theta + $thetac;

        $rad = $radius * ($i / $points );

        $x = ($rad * cos($theta)) + $x_axis;

        $y = ($rad * sin($theta)) + $y_axis;

        $theta = $theta + $thetac;

        $rad1 = $radius * (($i + 1) / $points);

        $x1 = ($rad1 * cos($theta)) + $x_axis;

        $y1 = ($rad1 * sin($theta )) + $y_axis;

        imageline($im, $x, $y, $x1, $y1, $grid_color);

        $theta = $theta - $thetac;

    }



    // -----------------------------------

    //  Write the text

    // -----------------------------------



    $use_font = ($font_path != '' AND file_exists($font_path) AND function_exists('imagettftext')) ? TRUE : FALSE;




    if ($use_font == FALSE)                {

        $font_size = 5;

        //$x = rand(0, $img_width/($length/3));

        $x=rand(0,20);//修改

        $y = 0;

    }

    else

    {

        $font_size        = 16;

        //$x = rand(0, $img_width/($length/1.5));

        $x=rand(0,20);//修改

        $y = $font_size+2;

    }

    for ($i = 0; $i < strlen($word); $i++)

    {

        if ($use_font == FALSE)

        {

            $y = rand(0 , $img_height/2);

            imagestring($im, $font_size, $x, $y, substr($word, $i, 1), $text_color);

            $x += ($font_size*2);

        }

        else

        {

            $y = rand($img_height/2, $img_height-3);

            imagettftext($im, $font_size, $angle, $x, $y, $text_color, $font_path, substr($word, $i, 1));

            $x += $font_size;

        }

    }





    // -----------------------------------

    //  Create the border

    // -----------------------------------



    imagerectangle($im, 0, 0, $img_width-1, $img_height-1, $border_color);



    // -----------------------------------

    //  Generate the image

    // -----------------------------------



    // $img_name = $now.'.jpg';



    // ImageJPEG($im, $img_path.$img_name);



    // $img = "<img src=\"$img_url$img_name\" width=\"$img_width\" height=\"$img_height\" style=\"border:0;\" alt=\" \" />";

    #直接输出

    header("Content-Type:image/jpeg");

    imagejpeg($im);



    ImageDestroy($im);

    #返回生成的验证码字符串

    return array('word' => $word, 'time' => $now);

    // return array('word' => $word, 'time' => $now, 'image' => $img);

}

/**
 * 函数：加密
 * @param string            密码
 * @return string           加密后的密码
 */
function password($password = '')
{
    /*
    *后续整强有力的加密函数
    */
    return md5('Q' . $password . 'W');

}