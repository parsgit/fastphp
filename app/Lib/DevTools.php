<?php
namespace App;

use App\Web\File;

class DevTools{

  public static function view($page,$params)
  {
    $components_view_path="components/dev-tools/views/";
    return view($components_view_path.'panel',['page'=>"$components_view_path$page",'pageParams'=>$params]);
  }

  public static function readIndex()
  {
    return File::getContent('index.php');
  }

  public static function readConfig()
  {
    return File::getContent(app_path('Config/core.php'));
  }

  public static function changeValueIndex($content,$newContent)
  {
    $index=DevTools::readIndex();
    $newText=str_replace($content,$newContent,$index);
    File::putContent('index.php',$newText);
    return $newText;
  }
  public static function changeStringIndex($param,$str)
  {
    $index=DevTools::readIndex();
    $start_pos=strpos($index,$param);
    $start_str=substr($index,$start_pos);
    $param_end_pos=strpos($start_str,';');
    $befor_str=substr($start_str,0,$param_end_pos);

    $newText=str_replace($befor_str,"$param=$str",$index);
    File::putContent('index.php',$newText);
  }

  public static function getValueIndex($param,$index=null,$removeQuotation=false)
  {
    if ($index==null) {
      $index=DevTools::readIndex();
    }
    $start_index=strpos($index,$param);
    $start_str=mb_substr($index,$start_index);
    $_param=strpos($start_str,'=')+1;

    $main_str=substr($start_str,$_param);
    $end_index=strpos($main_str,';');

    $val=substr($main_str,0,$end_index);
    if ($removeQuotation) {
      $val=str_replace("'",'',$val);
    }

    return $val;
  }
  public static function getValuesIndex($array,$removeQuotation=false)
  {
    $index=DevTools::readIndex();
    $res=[];
    if ( \is_string($array) ) {
      $array=[$array];
    }
    foreach ($array as $key => $value) {
      $name=$value;
      $name=str_replace('=','',$name);
      $res[$name]= DevTools::getValueIndex($value,$index,$removeQuotation);
    }
    return $res;
  }

  public static function settings($action,$param,$value)
  {
    if ($action=='check') {

      if ($value==='true') {
        $value='true';
        $after='false';
      }
      elseif ($value==='false') {
        $value='false';
        $after='true';
      }


      DevTools::changeValueIndex("$param=$after","$param=$value");
    }
    elseif($action=='string'){
       DevTools::changeStringIndex($param,$value);
    }
    return['ok'=>true];
  }

  public static function editUsername()
  {
    $cUsername=post('cUsername',null);
    $cPassword=post('cPassword',null);
    $newUsername=post('newUsername',null);

    $get_c_username=DevTools::getValueIndex('Developer_Username',null,true);
    $get_c_Password=DevTools::getValueIndex('Developer_Password',null,true);

    if ($cUsername==$get_c_username && $cPassword==$get_c_Password) {
      DevTools::changeStringIndex('Developer_Username',"'$newUsername'");
      return['ok'=>true];
    }
    else {
      return['ok'=>false,'msg'=>lang('msg.error_pass')];
    }
  }
  public static function editPassword()
  {
    $cUsername=post('cUsername',null);
    $cPassword=post('cPassword',null);
    $newPassword=post('newPassword',null);

    $get_c_username=DevTools::getValueIndex('Developer_Username',null,true);
    $get_c_Password=DevTools::getValueIndex('Developer_Password',null,true);

    if ($cUsername==$get_c_username && $cPassword==$get_c_Password) {
      DevTools::changeStringIndex('Developer_Password',"'$newPassword'");
      return['ok'=>true];
    }
    else {
      return['ok'=>false,'msg'=>lang('msg.error_pass')];
    }
  }

  public static function getDatabaseConfig()
  {
    $start_id = '//start=>database-config' ;
    $end_id   = '//end=>database-config'   ;

    $config=DevTools::readConfig();
    $MainCode=DevTools::findInsideCode($config,$start_id,$end_id,false,true);

    $arr=[];

    $configs_arr=explode('$con',$MainCode['code']);

    foreach ($configs_arr as $key => $str) {
      $str='$con'.$str;

      if (strpos($str,'config')===false) {
        continue;
      }


      $key=DevTools::findInsideCode($str,'[',']',false,true);
      $key=str_replace("'",'',$key['code']);
      $key=str_replace('"','',$key);

      $main_values= substr($str,strpos($str,'=')+1);
      $main_values= substr($main_values,strpos($main_values,'[')+1);
      $main_values= substr($main_values,0,strripos($main_values,']'));

      $array='{'.$main_values.'}';
      $array=str_replace('=>',':',$array);
      $array=str_replace("'",'"',$array);
      $params[$key]=json_decode($array);

    }
    return $params;
  }

  public static function addDatabaseConfigStr($str)
  {
    $end_id   = '//end=>database-config'   ;
    $config=DevTools::readConfig();

    $config=str_replace($end_id," $str \n \t\t\t$end_id",$config);
    File::putContent(app_path('Config/core.php'),$config);
    return  $config;
  }

  public static function removeDatabaseConfig($mainkey)
  {
    $start_id = '//start=>database-config' ;
    $end_id   = '//end=>database-config'   ;

    $core=DevTools::readConfig();

    $MainCode=DevTools::findInsideCode($core,$start_id,$end_id,false,true);


    $configs_ex=explode('$con',$MainCode['code']);
    $save=false;
    foreach ($configs_ex as $key => $config) {
      $config='$con'.$config;
      if (strpos($config,'$config')===false) {
        continue;
      }

      if (strpos($config,('$config['."'$mainkey']"))>-1) {
        $core=str_replace($config,"",$core);
        $save=true;
      }
    }

    if ($save) {
       File::putContent(app_path('Config/core.php'),$core);
      return ['ok'=>true];
    }
    else {
      return ['ok'=>false,'msg'=>'config not find in core file'];
    }
  }

  public static function addDatabaseConfig($main_key,$array){
    $temparr=DevTools::getPhpArrayText($array);
    return DevTools::addDatabaseConfigStr(DevTools::getPhpConfigStr('config',$main_key,$temparr));
  }

  public static function getPhpArrayText($array)
  {
    $temparr=[];
    foreach ($array as $key => $value) {
      if ($key=='db_host_port'|| $key=='result_stdClass') {
        $temparr[]="\n\t\t\t '$key'=>$value";
      }
      else {
        $temparr[]="\n\t\t\t '$key'=>'$value'";
      }

    }
    return implode(' , ',$temparr);
  }

  public static function getPhpConfigStr($name,$key,$str)
  {
    $str="".'$'."$name"."['".$key."']=[$str \n\t\t ];";
    return $str;
  }

  public static function editDatabaseConfig($main_key,$edit_key,$array){
    $configs=DevTools::getDatabaseConfig();
    $core=DevTools::readConfig();
    $start_id = '//start=>database-config' ;
    $end_id   = '//end=>database-config'   ;
    $MainCode=DevTools::findInsideCode($core,$start_id,$end_id,false,true);


    $configs_ex=explode('$con',$MainCode['code']);
    $save=false;

    foreach ($configs_ex as $key => $config) {
      $config='$con'.$config;
      if (strpos($config,'$config')===false) {
        continue;
      }

      if (strpos($config,('$config['."'$main_key']"))>-1) {

        if (! isset($array['password'])) {
          $array['password']=$configs[$main_key]->password;
        }
        $new=DevTools::getPhpConfigStr('config',$edit_key, DevTools::getPhpArrayText($array));
        $core=str_replace($config,"\t$new\n\t",$core);

        $save=true;
      }
    }

    if ($save) {
      File::putContent(app_path('Config/core.php'),$core);
      return ['ok'=>true];
    }
    else {
      return ['ok'=>false,'msg'=>'config not find in core file'];
    }
  }



  public static function findInsideCode($text,$start,$end,$trimAll=true,$trim=false)
  {
    return DevTools::findCode($text,$start,$end,$trimAll,false,true);
  }

  public static function findCode($text,$start,$end,$trimAll=true,$trim=false,$inside=false)
  {
    $index_func_start=strpos($text,$start);
    $index_func_end=strpos($text,$end);

    if ($inside==true) {
      $index_func_start+=strlen($start);
    }
    else {
      $index_func_end+=strlen($end);
    }

    $to=$index_func_end-$index_func_start;



    if ($to<0) {
      $text=substr($text,$index_func_start);
      $to=strpos($text,$end);

      if ($inside==false) {
        $to+=strlen($end);;
      }

      $code=substr($text,0,$to);
    }
    else {
      $code=substr($text,$index_func_start,$to);
    }

    if ($trimAll) {
      $code=preg_replace('/\s+/', '', $code);
    }
    if ($trim) {
      $code=trim($code);
    }
    return ['code'=>$code,'start'=>$index_func_start,'end'=>$index_func_end,'dif'=>$to];
  }

  public static function findCode2($text,$start,$end)
  {
    $index_func_start=strpos($text,$start);
    $index_func_end=strpos($text,$end);

    $code=substr($text,$index_func_start,$index_func_end);

    return ['code'=>$code,'start'=>$index_func_start,'end'=>$index_func_end,'dif'=>$to];
  }
}
