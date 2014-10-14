<?

ignore_user_abort(true);
set_time_limit(0);
	$fll = fopen("log_update2.txt", "a");
	
header("Content-type: text/html; charset=UTF-8");

require("admin/include/defs.php");
require("admin/include/dbconnect.php");
require("admin/include/templates.php");
require("admin/include/glib.php");
require("admin/include/functions/update.php");
require("engine/yandex.php");
require("engine/get_mail.php");
//require("engine/class.phpmailer.php");
require("engine/class.smtp.php");
//$request_address='https://api.direct.yandex.ru/live/v4/json/';
$request_address='https://api-sandbox.direct.yandex.ru/v4/json/'; //sandbox
$db = new CDatabase();
set_time_limit(0);
$dir_prefix = "./";
global $params;

 $flupdate = fopen("upload/sync/update2", "w");
  flock($flupdate, LOCK_EX);
$arr=array_map('intval',explode(',', mysql_real_escape_string($item)));
logstr("\n START ".basename(__FILE__)." users=".$item." ".$dt."\n");

for($i=0;$i<count($arr);$i++)
syncDB($arr[$i]);


$dt = date("F j, Y, H:i:s");   
logstr("END ".basename(__FILE__)." ".$item." ".$dt."\n==============================================================================================================\n==============================================================================================================\n\n");
fclose($fll);

 fwrite($flupdate, "done");
 fclose($flupdate);
  flock($flupdate, LOCK_UN);


?>