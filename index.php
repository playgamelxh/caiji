<?php
include 'Curl.php';
$curl = new Curl();

//$user = 'lvxiaohu';
//$header = array(
//    'Cookie:_GCWGuid=F59B28E1-FD08-31CB-F0EA-88DF38321500; m_xizhi_user=Xo1zVJDJbW0msvjssWju2W%2Bjj6gS%2BPqEKrlKv%2B1kvgmUbXRAjh8cyHjA0%2BmaBlkNVlL4x%2FuNgBJPWr7OTsQ46%2FfKNHnNp2xlDcwItVkohhHjbERRgswiV9ZavxgD6ZIY',
//    'User-Agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36',
//);
//耀华
$user = 'mengchao';
$header = array(
    'Cookie: _GCWGuid=A7591B04-DDA1-F192-9AA3-A61893C60428; m_xizhi_user=GVraldV%2BIAYiUwQUAyZvK4njeoPZjdvPAzH6%2FF7DXILpkMdshz2EAtBR54oNVeu5Lm5ccw6r9HXpm8TvwUzM1spmiCXu3b7IQaNcxJFSU0DwLBHc4lh428qgeMdevD3J',
);

$curl->setHttpHeader($header);
$page = 1;
while(true) {
    $curl->setUrl("http://manage.xizhi.com/gs/index?page={$page}");
    $html = $curl->run();
    preg_match_all("/gs\/detail\/\?cid=(\d+)/", $html, $match);
    if (empty($match[1])) {
        die("over");
    }

    foreach ($match[1] as $cid) {
        $url = "http://manage.xizhi.com/gs/detail/?cid={$cid}";
        $curl->setUrl($url);
        $html = $curl->run();
        $html = preg_replace('/\s/', '', $html);
        preg_match_all('/<tdwidth="200">企业名称<\/td><td>(.*?)<\/td>/', $html, $name);

        //企业名称小于4个字
        if (mb_strlen($name[1][0], "utf8") <= 4) {
            echo $cid,":",$name[1][0];
            $str = nameCheck($cid, $header);
            file_put_contents("{$user}.txt", $cid."\r\n", FILE_APPEND);
            continue;
//            die();
        }
        //企业名称末尾不为 厂 公司 集团
        //行 部 店 个体经营 批发部
        preg_match('/.*?(厂|公司|集团).*?/', $name[1][0], $temp);
        if (empty($temp[1])) {
            file_put_contents("{$user}.txt", $cid."\r\n", FILE_APPEND);
            nameCheck($cid, $header);
            echo $cid,"\r\n";
            continue;
        }

        //包含物流  代理
        preg_match('/.*?(物流|代理).*?/', $name[1][0], $temp);
        if (!empty($temp)) {
            file_put_contents("{$user}.txt", $cid."\r\n", FILE_APPEND);
            nameCheck($cid, $header, array(1, 3));
            echo $cid,"\r\n";
            continue;
        }

        //包含 化工、食品、保健品、药品、医疗器械行业
        preg_match('/.*?(化工|食品|保健品|药品|医疗).*?/', $name[1][0], $temp);
        if (!empty($temp)) {
            file_put_contents("{$user}.txt", $cid."\r\n", FILE_APPEND);
            nameCheck($cid, $header, array(1, 4));
            echo $cid,"\r\n";
            continue;
        }

        //过滤营销部  办事处结尾的公司
//        preg_match('/.*?(部|处)$/', $name[1][0], $temp);
//        if (!empty($temp)) {
//            file_put_contents("{$user}.txt", $cid."\r\n", FILE_APPEND);
//            nameCheck($cid, $header, array(1, 2));
//            echo $cid,"\r\n";
//            continue;
//        }
    }
    $page++;
    echo "page:",$page,"\r\n";
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