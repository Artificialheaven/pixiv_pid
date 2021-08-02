<?php
$pid = $_GET['pid'];
//获取想要搜寻的pid，只允许存在全整数型pid，如果有 _p0 等，需要去掉，否则程序错误。
$om = $_GET['master'];
//传入是否使用master1200的缩略图，加快访问速度（降低带宽开销）。

if($pid == ""){
    echo "<h1>404 NOT FOUND</h>"
	//没有获取到pid，返回404。
    exit;
};
$pixiv = file_get_contents('https://www.pixiv.net/artworks/'.$pid);
//获取全部页面数据

if ($om == 1){
// regular
$url = getSubstr($pixiv,"regular\":\"","\",\"");
}   else   {
$url = getSubstr($pixiv,"original\":\"","\"},");
//original
};

    $ch = curl_init();//curl初始化
	curl_setopt($ch,CURLOPT_URL, $url);//置访问地址
	curl_setopt($ch,CURLOPT_REFERER,"http://www.pixiv.net/"); //置referer ， 破解防盗链
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
	$res = curl_exec($ch);//获取图片数据
	header('Content-Type:' .curl_getinfo($ch, CURLINFO_CONTENT_TYPE));//获取图片类型，添加header头文件告诉浏览器这是个什么文件
	echo($res);//输出图片
	curl_close($ch);//释放curl
	//exit; //退出，实际上无意义。

function getSubstr($str, $leftStr, $rightStr)
{
    $left = strpos($str, $leftStr);
    $right = strpos($str, $rightStr,$left);
    if($left < 0 or $right < $left) return '';
    return substr($str, $left + strlen($leftStr), $right-$left-strlen($leftStr));
};
?>