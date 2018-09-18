<?php
/**
 * 简单的配置文件和功能函数
 * 使用显示图片等搜索结果，需要安装chrome浏览器，同时需要把chrome浏览器添加到环境变量
**/
// 配置 ssl 和 proxy
$arrContextOptions=array(
  "ssl"=>array(
    "verify_peer"=>false,
    "verify_peer_name"=>false,
  ),
  'http'=>array(
    'proxy' => 'tcp://127.0.0.1:1080', // 代理
    'request_fulluri' => true,
  )
);
$showImg = true; // 显示图片搜索结果
$showTranslate = false; // 显示翻译结果
$showSearch = false; // 显示web搜索结果

/*
 * 创建数据库
*/
function createDatabase($dbname="keywords.db3"){
    $db=new SQLite3($dbname,SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
  	// ID|keywords|length|source|addtime|grade|types|status,
    // ID|关键词|关键词长度|来源词|添加时间|采集深度|采集类型|状态
  	$db->exec("CREATE TABLE IF NOT EXISTS keywords (
      keywords varchar(256),
      length INTEGER,
      source varchar(256),
      addtime INTEGER,
      grade INTEGER,
      types varchar(256),
      status varchar(256)
    )"); // 创建数据库
  	$db->exec("create UNIQUE index if not exists index_keywords on keywords (keywords)");
  	$db->exec("create index if not exists index_length on keywords (length ASC)");
  	$db->exec("create index if not exists index_source on keywords (source)");
  	$db->exec("create index if not exists index_addtime on keywords (addtime ASC)");
    $db->exec("create index if not exists index_grade on keywords (grade ASC)");
  	$db->exec("create index if not exists index_types on keywords (types)");
  	$db->exec("create index if not exists index_status on keywords (status)");
  	$db->close();
}

// 导出数据库
function exportDatabase($dbname="keywords.db3",$filename="output.txt"){
  $db=new SQLite3('keywords2.db3',SQLITE3_OPEN_READONLY);
  $sql="select * from allkeywords";
  $result=$db->query($sql);
  $outputstr = "关键词\t长度\t搜索词\t添加时间\t深度\t采集类型\t状态\n";
  $jsq=0;
  while($row=$result->fetchArray(SQLITE3_ASSOC)){
    $outputstr.= $row['keywords']."\t".$row['length']."\t".$row['source']."\t".date("Y-m-d",$row['addtime'])."\t".$row['grade']."\t".$row['types']."\t".$row['status']."\n";
    $jsq++;
  }
  $db->close(); // close datebase
  file_put_contents($filename,$outputstr);
  echo "成功导出{$jsq}关键词到文件{$filename}\n";
}
// 插入数据库
function insertDatabase($keyword,$types,$img=false,$translate=false,$search=false,$source=NULL,$grade=0,$dbname="keywords.db3"){
	$keyword = trim($keyword);
  $check = checkKeyword($keyword,$dbname);
	if($check){
		echo '关键词: '.$keyword."已存在，跳过\n";
	}else{
    $db=new SQLite3($dbname,SQLITE3_OPEN_READWRITE);
    echo '关键词: '.$keyword."   ";
    fwrite(STDOUT,"辅助搜索(默认no，s:web|i:img|t:translate|a:all)：");
		$aide = strtolower(trim(fgets(STDIN)));
		if($aide =='i'||$aide =='a') exec('Chrome www.google.com/search?q='.urlencode($keyword).'"&"tbm=isch'); //Image
		if($aide =='s'||$aide =='a') exec('Chrome https://www.google.com/search?q='.urlencode($keyword)); //Web
		if($aide =='t'||$aide =='a') exec('Chrome https://translate.google.cn/#auto/zh-CN/'.rawurlencode($keyword)); //翻译
		fwrite(STDOUT,"是否保留{$keyword}[y/n](默认n)：");
		$switch = strtolower(trim(fgets(STDIN)));
		if($switch=='y'||$switch=='yes'){
			$length = substr_count($keyword,' ')+1;
			$sql="insert into keywords values ('".$db->escapeString($keyword)."',{$length},'".$source."',".time().",".($grade+1).",'{$types}','pending')";
			@$db->exec($sql);
		}else{
			$length = substr_count($keyword,' ')+1;
			$sql="insert into keywords values ('".$db->escapeString($keyword)."',{$length},'".$source."',".time().",".($grade+1).",'{$types}','trash')";
			@$db->exec($sql);
		}
    $db->close();
	}
}

// 检测关键词是否存在
function checkKeyword($keyword,$dbname="keywords.db3"){
  $db=new SQLite3($dbname,SQLITE3_OPEN_READONLY);
  $sql="select count(rowid) from keywords where keywords = '{$keyword}'";
  $result=intval($db->querySingle($sql,false));
  $db->close();
  if($result>0) return true; else return false;
}

// 根据关键词获取Yahoo组合框搜索, 返回数组
function getCombobox($keyword){
  global $arrContextOptions;
  $url = "https://search.yahoo.com/sugg/gossip/gossip-us-ura/?command=".urlencode($keyword); // 抓取Yahoo数据
  $content = file_get_contents($url, 0, stream_context_create($arrContextOptions));
  preg_match_all("<s k=\"(.*)\" m=\"\d+\"\/>",$content,$keywords); // 正则匹配内容，获取关键词
  return array_filter($keywords[1]);
}

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

// 根据关键词获取Yahoo搜索结果中每一条的标题, 返回数组
function getSearchTitle($keyword,$count=10){
  global $arrContextOptions;
	$result = array();
  $url = "https://search.yahoo.com/search?pz={$count}&p=".urlencode($keyword); // 抓取Yahoo数据
  $content = file_get_contents($url, 0, stream_context_create($arrContextOptions));
  $titles = getInfo('<div id="web">','</ol>',$content);
  $titles = explode("</li>",$titles);
	foreach($titles as $v)$result[]=getInfo('<h3 class="title">','</h3>',$v,true);
  return array_filter($result);
}

// 获取2个字符串之间的内容
function getInfo($startstr,$endstr,$content,$fun=false){
	$strpos1=strpos($content,$startstr);
	$strpos2=strpos($content,$endstr);
	if($strpos1&&$strpos2){
		$strlens=$strpos2-$strpos1;
		$result=substr($content,$strpos1,$strlens);
		$result=trim($result);
		if($fun) $result=strip_tags($result);
	}else $result='';
	return $result;
}

// 暂停程序
function stop($num=5){
  echo "程序暂停 {$num} 秒\n";
  for($i=$num;$i>0;$i--){
    sleep(1);
    echo "{$i}...";
  }
  echo "休息结束\n";
}
?>
