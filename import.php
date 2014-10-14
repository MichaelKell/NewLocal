<?php


set_time_limit(0);
//=================================================================
//                        SCHEDULED IMPORT
//=================================================================


// http://stackoverflow.com/questions/962915/how-do-i-make-an-asynchronous-get-request-in-php


function update_lock($next_line = "")
{
 $fl = fopen("upload/sync/sync_lock", "w");
 flock($fl, LOCK_EX);
 fwrite($fl, time());
 if ($next_line != "") {
  fwrite($fl, "\n" . $next_line);
 }
 fclose($fl);
 flock($fl, LOCK_UN);
}




function curl_post_async($url, $item)
{
    if($item=="")
        return;

    $str=implode(",", $item);
    $post_string="item=".$str; // ???/



    $parts=parse_url($url);

    $fp = fsockopen($parts['host'],
        isset($parts['port'])?$parts['port']:80,
        $errno, $errstr, 30);

    $out = "POST ".$parts['path']." HTTP/1.1\r\n";
    $out.= "Host: ".$parts['host']."\r\n";
    $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
    $out.= "Content-Length: ".strlen($post_string)."\r\n";
    $out.= "Connection: Close\r\n\r\n";
    if (isset($post_string)) $out.= $post_string;

    fwrite($fp, $out);
    fclose($fp);
}





require("admin/include/defs.php");
require("admin/include/dbconnect.php");
require("admin/include/templates.php");
require("admin/include/glib.php");
require("admin/include/functions/update.php");
require("engine/yandex.php");
$db = new CDatabase();
set_time_limit(0);
$dir_prefix = "./";



//$request_address='https://api.direct.yandex.ru/live/v4/json/';
$request_address='https://api-sandbox.direct.yandex.ru/v4/json/'; //sandbox


// если файла-замка нету - то создадим его.
if (!file_exists("upload/sync/sync_lock")) {
	$fl = fopen("upload/sync/sync_lock", "w");
	fclose($fl);
}

$fl = file("upload/sync/sync_lock");
$run = false;





//var_dump($fl);


if ($fl[1] == "done" || $fl[0]=="") {
	// check if it's time for a new scheduled run
	$dt = getDate();

echo "<br>----------------<br>Проверяем время<br>----------------<br>";

	if (time() - $fl[0] > 60) {
		//every 60sec
		echo "<br>----------------<br>Пора делать запланированную синхронизацию<br>----------------<br>";
		$run = true;
	}

} 
else
	if(time() - $fl[0] > 60){
	// no activity for the past 1 mins or more, and no "finished" status
    // mail here 
	echo "<br>----------------<br>Похоже, предыдущая синхронизация не дошла до конца. Прогоним ещё раз.<br>----------------<br>";
	$run = true;
}




//$run = true;
if ($_GET['force'] == "1" || $force == 1) {
	$run = true;
}




if ($run) {
    update_lock("started");
				echo "<br><br><br>----------------<br>Робот запущен.<br>----------------<br>";

		


$users=$db->getData("SELECT * FROM Users ORDER BY __id");

$db->query("UPDATE Logins SET UpdateStatus=1");


for($i=0;$i<count($users); $i++)
{
    $count=$db->getData("SELECT Login FROM Logins WHERE UserID=".$users[$i]['__id']);
    if(count($count)>0)
$arr[]=$users[$i]['__id'];
}



   


$request_arr=array_chunk($arr,count($users)/2+1); // 2 scripts




curl_post_async("http://server2.webisgroup.ru/api/update2.php",$request_arr[0]);
sleep(1);
curl_post_async("http://server2.webisgroup.ru/api/update3.php",$request_arr[1]);





while(true)     //now wait done status, both scripts
{
sleep(1);
$flupdate2=file("upload/sync/update2");             
$flupdate3=file("upload/sync/update3");

if($flupdate2[0]=="done" && $flupdate3[0]=="done")
    break;
}


//db->get data where updatestatus=0 and mail with logs

			//die();
			update_lock("done");
				echo "<br>----------------<br>Робот закончил работу.<br>";




}
 else {
    echo("Ещё не время для синхронизации (" . date("H:i:s") . ")<br>");
}










	?>
