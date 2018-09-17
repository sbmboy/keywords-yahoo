<?php
$keywords = "roadheader";
$arrContextOptions=array(
  "ssl"=>array(
    "verify_peer"=>false,
    "verify_peer_name"=>false,
  ),
  'http'=>array(
    'proxy' => 'tcp://127.0.0.1:1080',
    'request_fulluri' => true,
  )
);
$result = getAlsoTry($keywords);
var_dump($result);

// 根据关键词获取Yahoo相关搜索, 返回数组
function getAlsoTry($keyword){
  global $arrContextOptions;
  $url = "https://search.yahoo.com/search?p=".urlencode($keyword); // 抓取Yahoo数据
  $content = file_get_contents($url, 0, stream_context_create($arrContextOptions));
  $alsotry = getInfo('<table class="compTable','</table>',$content);
  $alsotry = str_replace('</a>','</a>[|||]',$alsotry);
  $alsotry = strip_tags($alsotry);
  $alsotry = explode("[|||]",$alsotry);
  return array_filter($alsotry);
}

// 获取2个字符串之间的内容
function getInfo($startstr,$endstr,$content,$fun=false){
	$strpos1=strpos($content,$startstr);
	$strpos2=strpos($content,$endstr);
	if($strpos1&&$strpos2){
		$strlens=$strpos2-$strpos1;
		$result=substr($content,$strpos1,$strlens);
		$result=trim($result);
		if($fun) $result=$startstr.strip_tags($result).$endstr;
	}else $result='';
	return $result;
}
 ?>
