<?php

$r18 = $_GET["r18"];
$tag = $_GET["tag"];
$master = $_GET["master"];
$isPid = $_GET["ispid"];
//赋值入参

if($r18 == null){
	$r18 = "0";
};
if($tag == null){
	$notTag = 1;
};
if($master == null){
	$master == "0";
};
//初始化变量表

if($notTag == 0){
	$text = file_get_contents("https://api.lolicon.app/setu/v2?r18=".$r18."&master=".$master."&tag=".urlencode($tag));

} else {
	$text = file_get_contents("https://api.lolicon.app/setu/v2?r18=".$r18."&master=".$master);
};

$pid = getSubstr($text,"{\"error\":\"\",\"data\":[{\"pid\":",",\"p\":");
if($isPid == "1"){
		echo $pid;
} else {
		header("HTTP/1.1 303 See Other");
		header("Location: https://pixiv.moeiris.com/pid.php?pid=".$pid."&master=".$master);
};


function getSubstr($str, $leftStr, $rightStr)
{
    $left = strpos($str, $leftStr);
    $right = strpos($str, $rightStr,$left);
    if($left < 0 or $right < $left){
         return "";
    };
    return substr($str, $left + strlen($leftStr), $right-$left-strlen($leftStr));
};


?>
