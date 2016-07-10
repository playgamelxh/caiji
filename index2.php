<?php
include 'Curl.php';
$curl = new Curl();

//$user = 'lvxiaohu';
//$header = array(
//    'Cookie:_GCWGuid=F59B28E1-FD08-31CB-F0EA-88DF38321500; m_xizhi_user=Xo1zVJDJbW0msvjssWju2W%2Bjj6gS%2BPqEKrlKv%2B1kvgmUbXRAjh8cyHjA0%2BmaBlkNVlL4x%2FuNgBJPWr7OTsQ46%2FfKNHnNp2xlDcwItVkohhHjbERRgswiV9ZavxgD6ZIY',
//    'User-Agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36',
//);

$user = 'yuanyaohua';
$header = array(
    'Cookie: _GCWGuid=A7591B04-DDA1-F192-9AA3-A61893C60428; m_xizhi_user=wHcOK%2FkraNiLKBU5Q%2BfLZMjms7yAtqxaCi3FCZxC45TjC%2FHTtHwQqDbuI%2BAAb6vVdFV2dJmszXEwXPUsLOMkfOZVyehjNkk%2FEUo9rQZ0EuCQB8HcXV2CEjoLtxnyGxP0',
    'User-Agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36',
);


$curl->setHttpHeader($header);
$page = 25;
while(true) {
    $curl->setUrl("http://manage.xizhi.com/gs/index?page={$page}");
    $html = $curl->run();
    preg_match_all("/gs\/detail\/\?cid=(\d+)/", $html, $match);
    if (empty($match[1])) {
        die("over");
    }

    foreach ($match[1] as $k => $cid) {
        $url = "http://manage.xizhi.com/gs/detail/?cid={$cid}";
        $curl->setUrl($url);
        $html = $curl->run();
        $html = preg_replace('/\s/', '', $html);
        preg_match_all('/<tdwidth="200">企业名称<\/td><td>(.*?)<\/td>/', $html, $name);
        $comname = strip_tags($name[1][0]);
//        echo $comname,"\r\n";
        //搜企查查
        qichacha($cid, $comname, $header);
//        if ($k >= 5) break;
    }
    $page++;
    echo "page:",$page,"\r\n";
    //die();
}

//起查查搜索
function qichacha($cid, $comname, $header)
{
    //公司有后缀的人工审核
//    $comname = '广州市正耀金属制品有限公司';
    preg_match('/.*?公司(.*?)$/', $comname, $temp);
    if (!empty($temp[1])) {
        return;
    }

    $curl = new Curl();
    $tempIp = rand(1,255).'.'.rand(1,255).'.'.rand(1,255).'.'.rand(1,255);
    $headerQichacha = array(
        "CLIENT-IP:{$tempIp}",
        "X-FORWARDED-FOR:{$tempIp}",
    );
    $curl->setHttpHeader($headerQichacha);
    $url = "http://www.qichacha.com/search?key={$comname}";
    $curl->setUrl($url);
    $html = $curl->run();
    //判断是否需要验证码
    if (strpos($html, '家符合条件的企业, 用时') === false) {
        echo "check code\r\n";
        sleep(5);
        qichacha($comname);
    }
    //搜索结果查询
    $html = preg_replace('/\s/', '', $html);
    //匹配结果
    $p = '/<ahref="\/firm_(.*?)\.shtml".*?class="text-priamry">(.*?)<\/a>/';
    preg_match_all($p, $html, $match);
//    print_r($match);
    $log = "";
    if (empty($match[2])) {
        nameCheck($cid, $header, $type = array(1));
        writeLog("{$cid}\t无结果拒审\r\n");
    } else {
        //有结果
        foreach ($match[2] as $key => $value) {
//            echo strip_tags($value),"\r\n";
            if ($comname == strip_tags($value)) {
                //判断工商状态
                preg_match_all('/<spanclass="labellist-detaillist-successm-l-xs">(.*?)<\/span>/', $html, $temp);
//                print_r($temp);die();
                if (isset($temp[1][$key]) && $temp[1][$key] == '在业' || $temp[1][$key] == '存续') {
                    //审核通过
                    check($cid, $header, 2);
                    writeLog("{$cid}\t完全匹配审核通过\r\n");
                    break;
                } else {
                    //拒审
                    check($cid, $header, 3, array(1, 6));
                    writeLog("{$cid}\t完全匹配拒审\r\n");
                    break;
                }
            }
        }
        //有结果 名称无法完全匹配
//        sleep(1);
        $url = "http://www.qichacha.com/firm_{$match[1][0]}.shtml";
        $curl->setUrl($url);
        $html = $curl->run();
        $html = preg_replace('/\s/', '', $html);
        $p = '/<listyle="width:100%"><label>曾用名：<\/label>((<span>.*?<\/span>)+)<\/li>/';
        preg_match($p, $html, $temp);
        print_r($temp);echo $cid;
        if (!empty($temp[1])) {
            $str = strip_tags($temp[1]);
            $str = str_replace('&nbsp;&nbsp;', '||', $str);
            $arr = explode('||', $str);
            foreach ($arr as $v) {
                if ($v == $comname || strpos($v, $comname) !== false) {
                    preg_match('/<spanclass="text-bigfont-bold"style="color:#555;">(.*?)<\/span>/', $html, $temp);
//                    print_r($temp);die();
                    check($cid, $header, 1, array(), $temp[1]);
                    writeLog("{$cid}\t改名\t{$temp[1]}\r\n");
                    break;
                }
            }
        }
    }
//    die();
}

//写日志
function writeLog($str)
{
    global $user;
    file_put_contents("{$user}SQ.txt", $str, FILE_APPEND);
}
//审核函数
function check($cid, $header, $state, $type = array(), $name = '')
{
    $curl = new Curl();
    $curl->setHttpHeader($header);
    $curl->setUrl("http://manage.xizhi.com/gs/dodetail?isajax=1");
    $data = array(
        'state' => $state,
        'cid' => $cid,
    );
    if ($state == 1) {
        $data['recomname'] = $name;
    }
    $reason = array(
        1 => '悉知、企查查、公信系统等确无此企业信息',
        2 => '名称有明显问题（如英文、无行政区、字数少无法识别、其他等）',
        3 => '非工业品原材料行业（如服务业、消费品及其它行业）',
        4 => '特殊行业（如化工、食品、保健品、药品、医疗器械行业等）  ',
        5 => '主营产品与企业名称不符  ',
        6 => '工商状态不正常（如：吊销、注销等） ',
    );
    if (is_array($type) && !empty($type)) {
        foreach ($type as $key) {
            $data['checkdesc[]'] = $reason[$key];
        }
    }
    $curl->setPost($data);
    $html = $curl->run();
}
//公司名为人名
function nameCheck($cid, $header, $type = array(1, 2))
{
    $curl = new Curl();
    $curl->setHttpHeader($header);
    $curl->setUrl("http://manage.xizhi.com/gs/dodetail?isajax=1");
    $data = array(
        'state' => 3,
        'cid' => $cid,
    );
    $reason = array(
        1 => '悉知、企查查、公信系统等确无此企业信息',
        2 => '名称有明显问题（如英文、无行政区、字数少无法识别、其他等）',
        3 => '非工业品原材料行业（如服务业、消费品及其它行业）',
        4 => '特殊行业（如化工、食品、保健品、药品、医疗器械行业等）  ',
        5 => '主营产品与企业名称不符  ',
        6 => '工商状态不正常（如：吊销、注销等） ',
    );
    foreach ($type as $key) {
        $data['checkdesc[]'] = $reason[$key];
    }
    $curl->setPost($data);
    $html = $curl->run();
}