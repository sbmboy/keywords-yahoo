<?php
/**
 * 获取yahoo搜索框相关关键词
 * 时间：2018-8-31
 **/
echo "/**
 * 拓展Yahoo搜索框词
 * 版本：1.0
 * 说明：初次使用请输入核心关键词可以输入单个关键词，也可以是包含关键词的文档路径，关键词文档格式为每行一个关键词
 *
**/\n";
if(!file_exists('keywords.db3')){
  $inputopen = true;
}else{
  fwrite(STDOUT,"输入关键词或直接拓展（1.输入关键词或文档路径 2.直接运行拓展程序）：");
  if(trim(fgets(STDIN))=='1')
    $inputopen = true;
  else
    $inputopen = false;
}

 //1. 添加关键词
if($inputopen){
  fwrite(STDOUT,"输入关键词或文档路径：");
  $input = trim(fgets(STDIN));
  while($input==''){
    fwrite(STDOUT,"正确输入关键词或文档路径：");
    $input = trim(fgets(STDIN));
  }
  if(strstr($input,'.txt')) $keywords = file($input); else $keywords = $input;
  if(!file_exists("keywords.db3")) createDatabase();
  $db = new SQLite3('keywords.db3',SQLITE3_OPEN_READWRITE);
  $db->exec("begin exclusive transaction");
  if(is_array($keywords)){
    foreach($keywords as $v){
   		$v=trim($v);
   		if(!empty($v)){
   			$length = substr_count($v,' ')+1;
   			$sql="insert into allkeywords values ('".$db->escapeString($v)."',{$length},'',".time().",0,'pending')";
   			$db->exec($sql);
   		}
   	} //endforeach
     $count = count($keywords);
  }else{
    $length = substr_count($keywords,' ')+1;
    $sql="insert into allkeywords values ('".$db->escapeString($keywords)."',{$length},'',".time().",0,'pending')";
    $db->exec($sql);
    $count = 1;
  }
  $db->exec("end transaction");
  $db->close(); // close datebase
  echo "成功添加核心关键词{$count}个\n";
}
// 执行自动采集任务
echo "程序开始执行，只到所有数据全部跑完。\n如果想中途停止，请按ctrl+C\n";
$arrContextOptions=array(
  "ssl"=>array(
    "verify_peer"=>false,
    "verify_peer_name"=>false,
  ),
);
while(true){
  $db=new SQLite3('keywords.db3',SQLITE3_OPEN_READWRITE);
  $sql="select rowid,keywords,grade,length from allkeywords where status = 'pending' ORDER by length ASC limit 0,1"; // 获取最短的一条记录
  $post=$db->querySingle($sql,true); // 获取 rowid,keywords,who,grade
  if(isset($post['keywords'])){
    $url = "https://search.yahoo.com/sugg/gossip/gossip-us-ura/?command=".urlencode($post['keywords']); // 抓取Yahoo数据
    // $content = getSSLPage($url); // 抓取Yahoo数据
    $content = file_get_contents($url, false, stream_context_create($arrContextOptions));
    unset($url);
    preg_match_all("<s k=\"(.*)\" m=\"\d+\"\/>",$content,$keywords); // 正则匹配内容，获取关键词
    unset($content);
    if(count($keywords[1])>0){
      echo date("Y-m-d H:i:s").'|_'.$post['keywords']."\n";
    	$db->exec("begin exclusive transaction");
    	foreach($keywords[1] as $keyword){
    		$keyword = trim($keyword);
    		$length = substr_count($keyword,' ')+1;
    		$sql="insert into allkeywords values ('".$db->escapeString($keyword)."',{$length},'{$post['keywords']}',".time().",".($post['grade']+1).",'pending')";
    		@$db->exec($sql);
        echo date("Y-m-d H:i:s").' |_'.$post['keywords']."\n";
    	}
    	$db->exec("end transaction");
    	unset($keywords);
    	// 更新源关键词
    	$sql = "UPDATE allkeywords SET status = 'completed' WHERE rowid = {$post['rowid']}";
    	@$db->exec($sql);
    	unset($post);
    	unset($sql);

    }else{
      $sql = "UPDATE allkeywords SET length = ".($post['length']+1)." WHERE rowid = {$post['rowid']}";
		  @$db->exec($sql);
    }
  }else{
    die("全部完成");
  }
  $db->close();
  sleep(5); //休息5秒，设置太短会被封
}
/*
 * create database
*/
function createDatabase(){
    $db=new SQLite3('keywords.db3',SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
  	// ID|keywords|length|source|addtime|grade|status
  	$db->exec("CREATE TABLE IF NOT EXISTS allkeywords (
      keywords varchar(256),
      length INTEGER,
      source varchar(256),
      addtime INTEGER,
      grade INTEGER,
      status varchar(256)
    )"); // 创建数据库
  	$db->exec("create UNIQUE index if not exists index_keywords on allkeywords (keywords)");
  	$db->exec("create index if not exists index_length on allkeywords (length ASC)");
  	$db->exec("create index if not exists index_source on allkeywords (source)");
  	$db->exec("create index if not exists index_addtime on allkeywords (addtime ASC)");
  	$db->exec("create index if not exists index_grade on allkeywords (grade ASC)");
  	$db->exec("create index if not exists index_status on allkeywords (status)");
  	$db->close();
}
/*
 * getSSLPage
**/
function getSSLPage($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSLVERSION,3);
    curl_setopt($ch, CURLOPT_CAINFO, "./cacert.pem");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
?>
