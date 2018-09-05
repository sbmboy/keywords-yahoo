<?php
/**
 * 获取yahoo搜索框相关关键词
 * 时间：2018-8-31
 **/
echo "/**
 * 拓展Yahoo搜索框词
 * 版本：1.0
 * 说明：初次使用请输入核心关键词, 可以输入单个关键词, 也可以是包含关键词的文档路径, 关键词文档格式为每行一个关键词
 *
**/\n";
if(!file_exists('keywords.db3')){
  $inputopen = true;
}else{
  echo "1.输入关键词或文档路径\n2.直接运行拓展程序\n3.导出关键词到TXT\n";
  fwrite(STDOUT,"请选择：");
  $check=trim(fgets(STDIN));
  if($check=='1'){
    $inputopen = true;
	  $outputopen = false;
  }elseif($check=='2'){
    $inputopen = false;
    $outputopen = false;
  }elseif($check=='3'){
    $inputopen = false;
    $outputopen = true;
  }else{
    die("输入错误！");
  }
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
  echo "创建数据库keywords.db3成功\n添加核心关键词{$count}个\n";
  echo "拓展程序将再5秒后执行，如需终止，请按ctrl+c\n";
  for($i=5;$i>0;$i--){
    sleep(1);
    echo "{$i}\n";
  }
}
// 3.导出关键词
if($outputopen){
  $db=new SQLite3('keywords.db3',SQLITE3_OPEN_READONLY);
  $sql="select keywords,grade,length,source from allkeywords"; // 获取最短的一条记录
  $result=$db->query($sql);
  $outputstr = "关键词\t长度\t搜索词\t深度\n";
  $jsq=0;
  while($row=$result->fetchArray(SQLITE3_ASSOC)){
    $outputstr.= $row['keywords']."\t".$row['length']."\t".$row['source']."\t".$row['grade']."\n";
    $jsq++;
  }
  $db->close(); // close datebase
  file_put_contents("output.txt",$outputstr);
  echo "成功导出{$jsq}关键词到文件output.txt\n";
  echo "拓展程序将再10秒后执行，如需终止，请按ctrl+c\n";
  for($i=10;$i>0;$i--){
    sleep(1);
    echo "{$i}\n";
  }
}

// 执行自动采集任务
echo "拓展程序开始执行，直到所有数据全部跑完。\n如果想中途停止，请按ctrl+C\n";
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
$words = array(""," a"," b"," c"," d"," e"," f"," g"," h"," i"," j"," k"," l"," m"," n"," o"," p"," q"," r"," s"," t"," u"," v"," w"," x"," y"," z");
while(true){
  $db=new SQLite3('keywords.db3',SQLITE3_OPEN_READONLY);
  $sql="select rowid,keywords,grade,length from allkeywords where status = 'pending' ORDER by length ASC limit 0,1"; // 获取最短的一条记录
  $post=$db->querySingle($sql,true); // 获取 rowid,keywords,grade
  $db->close();
  if(isset($post['keywords'])){
	$db=new SQLite3('keywords.db3',SQLITE3_OPEN_READWRITE);
	$db->exec("begin exclusive transaction");
    foreach($words as $word){
	  echo date("H:i:s").'|_'.$keyword.$word."\n";
      $url = "https://search.yahoo.com/sugg/gossip/gossip-us-ura/?command=".urlencode($post['keywords'].$word); // 抓取Yahoo数据
      $content = file_get_contents($url, 0, stream_context_create($arrContextOptions));
      preg_match_all("<s k=\"(.*)\" m=\"\d+\"\/>",$content,$keywords); // 正则匹配内容，获取关键词
      if(count($keywords[1])>0){
      	foreach($keywords[1] as $keyword){
      		$keyword = trim($keyword);
      		$length = substr_count($keyword,' ')+1;
      		$sql="insert into allkeywords values ('".$db->escapeString($keyword)."',{$length},'{$post['keywords']}',".time().",".($post['grade']+1).",'pending')";
      		@$db->exec($sql);
			echo date("H:i:s").' |_'.$keyword."\n";
      	}
      }
  	  echo "暂停5秒...\n";
      for($i=5;$i>0;$i--){
        sleep(1);
        echo "{$i}\n";
      }
    }
    // 更新源关键词
    $sql = "UPDATE allkeywords SET status = 'completed' WHERE rowid = {$post['rowid']}";
    @$db->exec($sql);
  	$db->exec("end transaction");
  	$db->close();
  	echo date("H:i:s").'|_'.$post['keywords']."完成..\n";
  	echo "暂停5秒...\n";
    for($i=5;$i>0;$i--){
      sleep(1);
      echo "{$i}\n";
    }
  }else{
    die("数据库中无待扩展关键词，请检查！");
  }
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
?>
