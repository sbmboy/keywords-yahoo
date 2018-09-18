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
$dbname = 'continuous-miner.db3'; // 词库名
require_once 'config.php';
if(!file_exists($dbname)){
  $inputopen = true;
  $outputopen = false;
}else{
  echo "1.源关键词输入\n2.关键词库导出\n3.运行拓展程序\n";
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

//1. 源关键词输入
if($inputopen){
  fwrite(STDOUT,"输入关键词(包含关键词的文档路径)：");
  $input = trim(fgets(STDIN));
  while($input==''){
    fwrite(STDOUT,"请输入正确的关键词或文档路径：");
    $input = trim(fgets(STDIN));
  }
  if(strstr($input,'.txt')) $keywords = file($input); else $keywords = $input;
  if(!file_exists($dbname)) createDatabase($dbname); // 创建数据库
  // 关键词入库
  if(is_array($keywords)){
    foreach($keywords as $v){
      $v=trim($v);
      if(!empty($v)){
        insertDatabase($v,'add',true,false,false,NULL,0,$dbname);
   		}
   	} //endforeach
     $count = count($keywords);
  }else{
    $v=trim($v);
    insertDatabase($v,'add',true,false,false,NULL,0,$dbname);
    $count = 1;
  }
  echo "创建数据库{$dbname}成功\n添加关键词{$count}个\n";
  echo "5秒后执行拓展程序，如需终止，请按ctrl+c\n";
  stop(5);
}
// 2.关键词库导出
if($outputopen){
  exportDatabase($dbname,'output-new.txt');
  echo "拓展程序将再10秒后执行，如需终止，请按ctrl+c\n";
  stop(10);
}

// 执行自动采集任务
echo "拓展程序开始执行，直到所有数据全部跑完。\n如果想中途停止，请按ctrl+C\n";
$words = array(""," a"," b"," c"," d"," e"," f"," g"," h"," i"," j"," k"," l"," m"," n"," o"," p"," q"," r"," s"," t"," u"," v"," w"," x"," y"," z");
while(true){
  $db=new SQLite3($dbname,SQLITE3_OPEN_READONLY);
  $sql="select rowid,keywords,grade,length from allkeywords where status = 'pending' ORDER by length ASC limit 0,1"; // 获取最短的一条记录
  $post=$db->querySingle($sql,true); // 获取 rowid,keywords,grade
  $db->close();
  if(isset($post['keywords'])){
	$db=new SQLite3($dbname,SQLITE3_OPEN_READWRITE);
	$db->exec("begin exclusive transaction");
    foreach($words as $word){
	    echo '正在处理: '.$post['keywords'].$word."\n";
      $keywords = getCombobox($post['keywords'].$word);
      if(count($keywords)>0){
      	foreach($keywords as $keyword){
      		$keyword = trim($keyword);
          insertDatabase($keyword,'Combobox',true,false,false,$post['keywords'].$word,$post['grade'],$dbname);
      	}
      }else{
        echo "无相关拓展，暂停3秒...";
        stop(3);
      }
    }
    // 更新源关键词
    $db=new SQLite3($dbname,SQLITE3_OPEN_READWRITE);
    $sql = "UPDATE keywords SET status = 'completed' WHERE rowid = {$post['rowid']}";
    @$db->exec($sql);
  	$db->close();
  	echo $post['keywords']."完成..\n";
  }else{
    die("数据库中无待扩展关键词，请检查！");
  }
}
?>
