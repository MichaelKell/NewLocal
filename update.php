<?





function logstr($string)
{
global $fll;
fwrite($fll, strip_tags($string));
echo nl2br("<i>".$string."</i>");

}





function compare($a,$arr, $compare)
{
//echo "___start____ $a ".count($arr)."<br>";
//var_dump($a);
  for($i=0;$i<count($arr);$i++){

    if(intval($arr[$i][$compare.""])==intval($a))
      return true;
}
    return false;
//echo "end<br>";
}



function unique($arr){
	$total=count($arr);
	for($i=0;$i<$total;$i++)
	{
		for($j=$i+1;$j<$total;$j++)
			if($arr[$i]==$arr[$j])
				unset($arr[$j]);
	}
	return $arr;
}


 
function syncDB($users) // выгрузка в локальную базу
{
	global $db;
	logstr("\nUser = ".$users."\n");
	syncCampaigns($users);	//uncomment
	syncBanners($users);		//uncomment
	updatePrices($users);
	checkStatus($users);
	cleanUp($users);
}


function checkStatus($userid)
{
global $db;
$res=$db->getData("SELECT * FROM Logins WHERE UserID=".intval($userid)." AND UpdateStatus=1");
$Log="Обновление данных прошло успешно";
for($i=0;$i<count($res);$i++)
{
	$__ctime=timeStr();
$db->query("INSERT INTO UpdateLog (__ctime,UserID,Login,Log) VALUES('".$__ctime."',".intval($userid).",'".$res[$i]['Login']."','".$Log."')");
}

}



function cleanup($userid)					//max 30 logs, max 15 reports
{
global $db, $dir_prefix;
$db->query("DELETE FROM `UpdateLog`
WHERE __id NOT IN (
  SELECT __id
  FROM (
    SELECT __id
    FROM `UpdateLog`
    WHERE UserID=".intval($userid)."
    ORDER BY __ctime DESC
    LIMIT 30
  ) UpdateLog
);");

$res=$db->getData("SELECT __id,Link FROM Reports WHERE UserID=".intval($userid)." ORDER BY DateCreate DESC LIMIT 18446744073709551615 OFFSET 15;");
for($i=0;$i<count($res);$i++)
{
	if(unlink($res[$i]['Link']))
	$db->query("DELETE FROM Reports WHERE __id=".$res[$i]['__id']);
}

}









function syncCampaigns($userid)
{
global $db, $CampaignID, $UserID, $Login, $Name, $Clicks, $Shows, $IsActive, $Rest,$Consum, $fll;
$res=$db->getData("SELECT * FROM Logins WHERE UserID='".intval($userid)."' AND UpdateStatus=1");
if(count($res)==0)
logstr("No Logins connected... Finished\n");
for($i=0;$i<count($res);$i++)
{


$dbres=$db->getData("SELECT * FROM Campaigns WHERE Login='".$res[$i]['Login']."' AND UserID='".intval($userid)."' ORDER BY CampaignID");
$yares=getCampaigns($res[$i]['Login'],$res[$i]['Token']);
if($yares["error_str"]!="")
{	
	logstr("Sync Campaigns for Login = ".$res[$i]['Login']." FAILED Error= ".$yares["error_code"]." ".$yares["error_str"]."\n\n");
	$db->query("UPDATE Logins SET UpdateStatus=0 WHERE __id=".$res[$i]['__id']);								//stop sync for login
	$Log="Ошибка обновления компаний ".$yares["error_code"]." ".$yares["error_str"];
	$__ctime=timeStr();
	$db->query("INSERT INTO UpdateLog(__ctime,UserID,Login,Log) VALUES('".$__ctime."',".intval($userid).",'".$res[$i]['Login']."','".$Log."')");
	continue;	
}
else
 logstr("Sync Campaigns for Login = ".$res[$i]['Login']." Total in loc db: ".count($dbres)." Total from Yandex: ".count($yares['data'])."\n");





	for($k=0;$k<count($dbres);$k++){	
if(compare($dbres[$k]['CampaignID'],$yares['data'], "CampaignID")==true)
continue;
else
{
$db->query("DELETE FROM Campaigns WHERE UserID='".intval($userid)."' AND CampaignID='".$dbres[$k]['CampaignID']."'");
logstr($dbres[$k]['CampaignID']." not in Yandex... Deleted\n");
}
}



unset($ress);
unset($stat);
for($b=0;$b<count($yares['data']);$b++)									//building request arr
	$ress[]=$yares['data'][$b]['CampaignID'];
if($ress!="")
$stat=GetSummaryStat($ress,$res[$i]['Login'],$res[$i]['Token']);       //max 100 в сутки
if($stat['error_str']!="")
{	
		logstr("Sync Campaigns for Login = ".$res[$i]['Login']." GetSummaryStat FAILED Error= ".$stat["error_code"]." ".$stat["error_str"]."\n\n");
		$db->query("UPDATE Logins SET UpdateStatus=0 WHERE __id=".$res[$i]['__id']);	//stop sync for login
		$Log="Ошибка обновления компаний ".$stat["error_code"]." ".$stat["error_str"];
		$__ctime=timeStr();
		$db->query("INSERT INTO UpdateLog(__ctime,UserID,Login,Log) VALUES('".$__ctime."',".intval($userid).",'".$res[$i]['Login']."','".$Log."')");
		continue;	
}


////
for($j=0;$j<count($yares['data']);$j++)
{
$Name=$Login=$UserID=$CampaignID=$Clicks=$Shows=$IsActive=$Rest=$Consum=null;
$isstopped="";

for($t=0;$t<count($stat['data']);$t++)
if($yares['data'][$j]['CampaignID']==$stat['data'][$t]['CampaignID'])
$Consum=$stat['data'][$t]['SumSearch'];

$Name=$yares['data'][$j]['Name'];

/*
	echo $Name." = ";			
echo $yares['data'][$j]['Sum']."<br>";*/

$Clicks=$yares['data'][$j]['Clicks'];
$Shows=$yares['data'][$j]['Shows'];

echo "<b>".$yares['data'][$j]['IsActive']." </b>";


if($yares['data'][$j]['IsActive']=="Yes")
	$IsActive="1";
else
	$IsActive="0";

$isstopped=checkDayBudget($Consum,$yares['data'][$j]['CampaignID'],$userid,$res[$i]['Login'],$res[$i]['Token']);    //check daybudget for campaign.
if($isstopped=="Yes")
	$IsActive="1";
if($isstopped=="No")	//no=stopped
	$IsActive="0";
if(floatval($Consum)==0)
	$Consum="0";

echo "<b>".$IsActive."</b>";

$Rest=$yares['data'][$j]['Rest'];

	$campaign=$db->getData("SELECT * FROM Campaigns WHERE CampaignID='".$yares['data'][$j]['CampaignID']."' AND UserID='".intval($userid)."'");
	if($campaign[0]['CampaignID']!="")
	{
			$db->update("Campaigns","__id='".$campaign[0]['__id']."'");
			logstr($campaign[0]['CampaignID']." found in loc db... Updated\n");
	}
		else{ 
			$UserID=$userid;
			$Login=$res[$i]['Login'];
			$CampaignID=$yares['data'][$j]['CampaignID'];
			$db->insert("Campaigns");
			logstr($CampaignID." Not found in loc db... Inserted\n");
		}
}



}

logstr("____________________________________\n");
}






function checkDayBudget($consum,$campid, $userid, $login, $token) 
{
	global $db,$ent, $dir_prefix;
	$res=$db->getData("SELECT DayBudget, Stopped FROM Campaigns WHERE UserID=".intval($userid)." AND CampaignID='".$campid."' AND Login='".$login."'");

if($res[0]['Stopped']!="")			//already stopped
{
$stop=date("d.m.Y",strtotime($res[0]['Stopped']));
$now=date("d.m.Y");

if(strcmp($now,$stop)!==0 || $consum<$res[0]['DayBudget'] || floatval($res[0]['DayBudget'])==0) 
{
$status=ResumeCampaign($campid,$login,$token);
if($status['error_str']!="")
{
	logstr("<span style='color: red;'>Error ResumeCampaign ".$status['error_code']." ".$status['error_str']."</span>");
}

	logstr("<span style='color: #3f4c04;'>Day Budget. Campaign Resumed</span> ");
$db->query("UPDATE Campaigns SET Stopped=NULL WHERE UserID=".intval($userid)." AND CampaignID='".$campid."' AND Login='".$login."'");
return "Yes";
}
else
return;
}
else{	
	if(floatval($res[0]['DayBudget'])==0){													//daybudget not setted
		return;
}
		if($consum>=$res[0]['DayBudget']){
		$status=StopCampaign($campid,$login,$token);
		if($status['error_str']!="")
{
	logstr("<span style='color: red;'>Error StopCampaign ".$status['error_code']." ".$status['error_str']."</span>");
}

logstr("<span style='color: #980b0b;'>Day Budget. Campaign Stopped</span> ");


/*
		$mail=$db->getData("SELECT Email FROM Users WHERE __id=".$userid);
		$mailSubject="Дневной бюджет кампании превышен";
		$Email=$ent['site_name'];
		$Name=$ent['site_name'];
 doMail($mail[0]['Email'], $mailSubject, "<html><body>".nl2br(parseTemplate(tplFromFile(dir_prefix."admin/templates/email_camp_stopped.htm"), $ent))."</body></html>", $Email, $Name);
 */

		$Stop_time=timeStr();
		$db->query("UPDATE Campaigns SET Stopped='".$Stop_time."' WHERE UserID=".intval($userid)." AND CampaignID=".$campid." AND Login='".$login."'"); //add isactive
		return "No";
	}
	else 
		return;

}

}




function syncBanners($userid)
{
global $db, $fll;

//var_dump($userid);
$res=$db->getData("SELECT * FROM Logins WHERE UserID='".intval($userid)."' AND UpdateStatus=1");
for($i=0;$i<count($res);$i++)
{

$campaigns=$db->getData("SELECT CampaignID FROM Campaigns WHERE Login='".$res[$i]['Login']."' AND UserID='".intval($userid)."'");


unset($ress);
unset($banners);
for($a=0;$a<count($campaigns);$a++)
{
$ress[]= $campaigns[$a]['CampaignID']; //макс - 10
}

if($ress!="")
$banners=getBanners($ress,$res[$i]['Login'], $res[$i]['Token']);

if($banners['error_str']!="")   // неактуальный токен
{
	var_dump($ress);
	$db->query("UPDATE Logins SET UpdateStatus=0 WHERE __id=".$res[$i]['__id']);								//stop sync for login
	$Log="Ошибка обновления объявлений ".$banners['error_code']." ".$banners["error_str"];
	$__ctime=timeStr();
	$db->query("INSERT INTO UpdateLog(__ctime,UserID,Login,Log) VALUES('".$__ctime."',".intval($userid).",'".$res[$i]['Login']."','".$Log."')");
		logstr("Sync Banners for Login = ".$res[$i]['Login']." FAILED Error= ".$banners['error_code']." ".$banners["error_str"]."\n");
		continue;
}
else
{
	logstr("Sync Banners for Login = ".$res[$i]['Login']."\n");
}

//var_dump($banners);

$totalupdated=0;
$totalfromyandex=0;

for($j=0;$j<count($campaigns); $j++)
{
unset($arr);


for($b=0;$b<count($banners['data']);$b++)									//building arr for requset
{
	//echo $banners['data'][$b]['CampaignID']."\n";
	if($banners['data'][$b]['CampaignID']==$campaigns[$j]['CampaignID'])
	{
		$arr[]=$banners['data'][$b];
	}
}




dosyncBanners($arr, $userid, $campaigns[$j]['CampaignID']);


for($k=0;$k<count($arr);$k++)
{
	$total=0;
$total=dosyncPhrases($arr[$k]['Phrases'], $userid);
$totalupdated+=$total[0];
$totalfromyandex+=$total[1];
}


}

//totalcnt = total phrases for login

}
}








function dosyncBanners($data, $userid, $campid)
{
	//echo "campid=".$campid;
global $db, $BannerID, $CampaignID, $UserID, $Title, $Text, $Href, $Domain, $Geo, $fll, $IsActive;



$dbres=$db->getData("SELECT * FROM Banners WHERE CampaignID='".$campid."' AND UserID='".intval($userid)."' ORDER BY BannerID");
logstr("******Campaign=".$campid." Total in loc db: ".count($dbres)." Total from Yandex: ".count($data)."\n");

//echo "<br>";
//echo count($dbres)." ".count($data)."<br>";




	for($k=0;$k<count($dbres);$k++){
if(compare($dbres[$k]['BannerID'],$data, "BannerID")==true)
continue;
else
{
$db->query("DELETE FROM Banners WHERE UserID='".intval($userid)."' AND BannerID='".$dbres[$k]['BannerID']."'");
logstr($dbres[$k]['BannerID']." not in Yandex... Deleted\n");
}
}



for($i=0; $i<count($data); $i++)
{
	
$BannerID=$CampaignID=$UserID=$Title=$Text=$Domain=$Geo=$IsActive=null;
$BannerID=$data[$i]['BannerID'];
$CampaignID=$campid;
$UserID=$userid;
$Title=$data[$i]['Title'];
$Text=$data[$i]['Text'];
$Href=$data[$i]['Href'];
$Domain=$data[$i]['Domain'];
$Geo=$data[$i]['Geo'];
if($data[$i]['StatusShow']=="No")
	$IsActive="0";
else
	$IsActive="1";


$res=$db->getData("SELECT * FROM Banners WHERE BannerID='".$data[$i]['BannerID']."' AND UserID='".intval($userid)."'");
if(count($res)>0)
{
	$db->update("Banners","__id='".$res[0]['__id']."'");
	logstr($res[0]['BannerID']." found in loc db... Updated\n");
}
else
{
$db->insert("Banners");
	logstr($BannerID." Not found in loc db... Inserted\n");
}
}

}




function dosyncPhrases($data, $userid)
{
global $db, $PhraseID, $BannerID, $CampaignID, $UserID, $Name, $Clicks, $Shows, $Price, $CurrentOnSearch, $PremiumMax, $PremiumMin, $Max, $Min, $Spec2, $Garant2, $Garant3;
//sorting

$total=0;

usort($data, "cmp"); 
$dbres=$db->getData("SELECT * FROM Phrases WHERE BannerID='".$data[0]['BannerID']."' AND UserID='".intval($userid)."' ORDER BY PhraseID");


//echo "<br>";
//echo count($dbres)." ".count($data)."<br>";



for($k=0;$k<count($dbres);$k++){
if(compare($dbres[$k]['PhraseID'],$data,"PhraseID")==true)
continue;
else
$db->query("DELETE FROM Phrases WHERE UserID='".intval($userid)."' AND PhraseID='".$dbres[$k]['PhraseID']."'");
}



//var_dump($data);

for($i=0; $i<count($data);$i++)
{
$PhraseID=$BannerID=$CampaignID=$UserID=$Name=$Shows=$Clicks=$Price=$CurrentOnSearch=$PremiumMax=$PremiumMin=$Min=$Max=$Spec2=$Garant2=$Garant3=null;
////
/*echo $data[$i]['Phrase']."=";
for($j=0;$j<count($data[$i]['Prices']);$j++)
	echo $data[$i]['Prices'][$j]." ";*/



$Spec2=findparams($data[$i]['Prices'],$data[$i]['PremiumMin'],$data[$i]['PremiumMax'], $data[$i]['Max']);
if($Spec2=="")
	$Spec2=$data[$i]['PremiumMin'];


$Garant2=findparams($data[$i]['Prices'],$data[$i]['Min'],$data[$i]['Max']);
if($Garant2=="")
$Garant2=$data[$i]['Min'];



$Garant3=findparams($data[$i]['Prices'],$data[$i]['Min'],$Garant2);
if($Garant3=="")
$Garant3=$data[$i]['Min'];





	///
$PhraseID=$data[$i]['PhraseID'];
$BannerID=$data[$i]['BannerID'];
$CampaignID=$data[$i]['CampaignID'];
$UserID=$userid;
$Name=$data[$i]['Phrase'];
$Clicks=$data[$i]['Clicks'];
$sumclicks+=$Clicks;
$Shows=$data[$i]['Shows'];
$Price=$data[$i]['Price'];


$CurrentOnSearch=$data[$i]['CurrentOnSearch'];
$PremiumMin=$data[$i]['PremiumMin'];
$PremiumMax=$data[$i]['PremiumMax'];
$Min=$data[$i]['Min'];
$Max=$data[$i]['Max'];
$res=$db->getData("SELECT * FROM Phrases WHERE PhraseID='".$data[$i]['PhraseID']."' AND UserID='".intval($userid)."'");
if(count($res)>0){
	$db->update("Phrases", "__id='".$res[0]['__id']."'");
	$total++;
}
else{
	$db->insert("Phrases");
	$total++;
}
}

return array($total,count($data));
}



function cmp($a, $b)  
{ 
return strnatcmp($a["Phrase"], $b["Phrase"]); 
} 








function findparams($arr,$from,$to, $exc=null)
{
for($i=0;$i<count($arr);$i++){
//	echo $arr[$i]." ";
	if($arr[$i]>$from && $arr[$i]<$to)
		if($exc!=""){
			if($exc!=$arr[$i])
		return $arr[$i];
	else
		continue;
	}else{
		return $arr[$i];
	}
}

}





//////////////////////
/////////////////////

	function updatePrices($userid){
		global $db;

		logstr("\n<span style='color: green;'>Prices Update</span>\n");

		$res=$db->getData("SELECT * FROM Logins WHERE UserID='".intval($userid)."' AND UpdateStatus=1");
		for($i=0;$i<count($res);$i++){
			unset($query_arr);		//phrases to update
			unset($d_keywords);		//keywords to stop
			unset($d_banners);		//banners to stop
			unset($d_campaigns);	//campaigns to stop
			unset($r_banners);		//banners to run
			unset($r_keywords);		//keywords to run
			$campaigns=$db->getData("SELECT * FROM Campaigns WHERE Login='".$res[$i]['Login']."' ORDER BY CampaignID");

			logstr("Prices updating for Login ".$res[$i]['Login']."\n");

			for($j=0;$j<count($campaigns);$j++){
				
				

					$phrases=$db->getData("SELECT * FROM Phrases WHERE CampaignID='".$campaigns[$j]['CampaignID']."' AND Strategy <> 0");
					logstr("***Campaign=".$campaigns[$j]['CampaignID']." Total phrases to update=".count($phrases)."\n");

					if($campaigns[$j]['Stopped']!="")			//campaign stopped cos daybudget
						//if($campaigns[$i]['IsActive']!=1 && $campaigns[$i]['IsDisabled']==0) //если кампания неактивна и неактивна не из-за макс цены
							{
								logstr("Campaign Stopped. Day Budget has expired\n");
								//logstr("Campaign is not active\n");
								continue;
							}
																							//если кампания неактивна из-за макс цены
							for($k=0;$k<count($phrases);$k++)
							{
								unset($arr);
								unset($price);
								unset($continue);
								$iseval=eval(getPrice($phrases[$k]));										
								if($iseval===false || $price=="")
								{
									if($iseval===false)
									logstr("<span style='color: red;'>FAILED TO EVAL</span>\n");
									if($price=="")
										logstr("<span style='color: red;'>Zero price</span>\n");
									continue;
								}
										
										logstr(" result= <span style='color: #b1880a'>".$price."</span>");
										logstr(" maxprice: ");

										
										if(floatval($phrases[$k]['MaxPrice'])!=0 && floatval($phrases[$k]['MaxPrice'])<=$price){			//if maxprice setted
										logstr("<span style='color: #ae5018;'>not ok</span> ");
										if(intval($phrases[$k]['Strategy2'])!=0)								//found alt strategy
										{
											logstr("found alt strategy ");
												$iseval=eval(getPrice($phrases[$k],2));
												if($iseval===false || $price=="")
													{
														if($iseval===false)
														logstr("<span style='color: red;'>FAILED TO EVAL</span>\n");
														if($price=="")
														logstr("<span style='color: red;'>Zero price</span>\n");
														continue;
													}
												logstr(" result= <span style='color: #b1880a'>".$price."</span>");
												logstr(" maxprice2: ");
												if(floatval($phrases[$k]['MaxPrice2'])!=0 && floatval($phrases[$k]['MaxPrice2'])<=$price)
												{
													logstr("<span style='color: #ae5018;'>not ok</span> ");
													if(intval($phrases[$k]['Disable'])!=0) 			//disable by strategy 2
													{
														logstr("try disable ");
														if($phrases[$k]['Disable']==1){
														logstr("campaign -");
														/*$status=StopCampaign($campaigns[$j]['CampaignID'],$res[$i]['Login'],$res[$i]['Token']);
														if($status['error_str']!="")
														logstr("<span style='color: red;'>Error StopCampaign ".$status['error_code']." ".$status['error_str']."</span>\n");*/
													//	$db->query("UPDATE Campaigns SET IsDisabled=1 WHERE CampaignID='".$campaigns[$j]['CampaignID']."' AND UserID='".$userid."'");
														logstr("<span style='color: #721818;'>Campaign ".$campaigns[$j]['CampaignID']." disabled</span>\n");
														$d_campaigns[]=$campaigns[$j]['CampaignID'];
														continue;
														//$continue=1;
														}

														if($phrases[$k]['Disable']==2)
														{
														logstr("banner - ".$phrases[$k]['BannerID']."\n");
														$d_banners[]=array("BannerID" => $phrases[$k]['BannerID'],"CampaignID" => $campaigns[$j]['CampaignID']);
														continue;
														//$continue=1;
														}	
														if($phrases[$k]['Disable']==3)
														{
														$price=0.01;
														$d_keywords[]=array("PhraseID" => $phrases[$k]['PhraseID'],"BannerID" => $phrases[$k]['BannerID'], "CampaignID"=>$phrases[$k]['CampaignID']);
														}
												
													}
													else
													{
														$price=$phrases[$k]['MaxPrice2'];
													}
												}
												else{
													logstr("<span style='color: #11ac73'>ok</span> ");
													//run if disabled
												}
										}
										else
										{
											if(intval($phrases[$k]['Disable'])!=0)									//found disable
											{
												logstr("try disable ");
												if($phrases[$k]['Disable']==1){
													logstr("campaign -");
													/*$status=StopCampaign($campaigns[$j]['CampaignID'],$res[$i]['Login'],$res[$i]['Token']);
													if($status['error_str']!="")
													logstr("<span style='color: red;'>Error StopCampaign ".$status['error_code']." ".$status['error_str']."</span>\n");*/
													//$db->query("UPDATE Campaigns IsDisabled=1 WHERE CampaignID='".$campaigns[$j]['CampaignID']."' AND UserID='".$userid."'");
													logstr("<span style='color: #721818;'>Campaign ".$campaigns[$j]['CampaignID']." disabled\n");
												$d_campaigns[]=$campaigns[$j]['CampaignID'];
												continue;
												//$continue=1;
												}

												if($phrases[$k]['Disable']==2)
												{
													logstr("banner -".$phrases[$k]['BannerID']."\n");
													$d_banners[]=array("BannerID" => $phrases[$k]['BannerID'],"CampaignID" => $campaigns[$j]['CampaignID']);
													continue;
													//$continue=1;
												}
												if($phrases[$k]['Disable']==3)
												{
													$price=0.01;
													$d_keywords[]=array("PhraseID" => $phrases[$k]['PhraseID'],"BannerID" => $phrases[$k]['BannerID'], "CampaignID"=>$phrases[$k]['CampaignID']);
												}
												
											}
											else
											{
												$price=$phrases[$k]['MaxPrice'];
											}
										}


										}
										else{
													logstr("<span style='color: #11ac73'>ok</span> ");
												}


										/*run if disabled*/
										/*
										if($phrases[$k]['Disable']!=0)
										{
										
											if($phrases[$k]['Disable']==1)
											{
												if($campaigns[$j]['IsDisabled']==1){

													$status=ResumeCampaign($campaigns[$j]['CampaignID'],$res[$i]['Login'],$res[$i]['Token']);
														if($status['error_str']!="")
													logstr("<span style='color: red;'>Error ResumeCampaign ".$status['error_code']." ".$status['error_str']."</span>\n");
												logstr("<span style='color: #3e56c0;'>Campaign ".$campaigns[$j]['CampaignID']." resumed</span>\n");

												$db->query("UPDATE Campaigns SET IsActive=1, IsDisabled=0 WHERE CampaignID='".$campaigns[$j]['CampaignID']."' AND UserID='".$userid."'");
												}
											}
											if($phrases[$k]['Disable']==2)
												{
													$is=$db->getData("SELECT IsDisabled FROM Banners WHERE BannerID='".$phrases[$k]['BannerID']."' AND UserID='".$userid."'");
													if($is[0]['IsDisabled']==1)
													{
														$r_banners[]=array("BannerID" => $phrases[$k]['BannerID'],"CampaignID" => $campaigns[$j]['CampaignID']);
													}
												}
											if($phrases[$k]['Disable']==3)
											{
												if($phrases[$k]['IsDisabled']==1)
													$db->query("UPDATE Phrases SET IsDisabled=0 WHERE BannerID='".$phrases[$k]['BannerID']."' AND UserID='".$userid."'");
											}
										}
										/**/
										//if($continue==1)
										//	continue;

										logstr("final price: <span style='color: #b1880a'>$price</span>");
										if($price==0.01)
										{
											$db->query("UPDATE Phrases SET IsDisabled=1 WHERE BannerID='".$phrases[$k]['BannerID']."' AND UserID='".$userid."'");
											logstr(" phrase stopped");
										}
										logstr("\n");
										$arr['AutoBroker']="Yes";
										$arr['CampaignID']=$campaigns[$j]['CampaignID'];
										$arr['PhraseID']=$phrases[$k]['PhraseID'];	
										$arr['Price']=$price;	
										$query_arr[]=$arr;										// max 1000 фраз
										$db->query("UPDATE Phrases SET Price=$price WHERE __id=".$phrases[$k]['__id']);
									
							}
				}

if($query_arr!="")
$status=doUpdatePrices($query_arr,$res[$i]['Login'],$res[$i]['Token']);
if($status["error_str"]!="")
{	
	logstr("Updating Prices for Login = ".$res[$i]['Login']." FAILED Error= ".$status["error_code"]." ".$status["error_str"]."\n\n");
	$Log="Ошибка обновления цен ".$status["error_code"]." ".$status["error_str"];
	$__ctime=timeStr();
	$db->query("INSERT INTO UpdateLog(__ctime,UserID,Login,Log) VALUES('".$__ctime."','".intval($userid)."','".$res[$i]['Login']."','".$Log."')");
	$db->query("UPDATE Logins SET UpdateStatus=0 WHERE __id=".$res[$i]['__id']);
}



/*

///////do resume
if($r_banners!="")
foreach ($r_banners as $banner) {
	$campaignid=$banner['CampaignID'];
	$bannerid=$banner['BannerID'];
	$banners_res[$campaignid][]=$bannerid;
}


	foreach ($banners_res as $key => $value) {
		unset($query_banners);
		$campid=$key;
		for($m=0;$m<count($value);$m++)
			$query_banners[]=$value[$m];
		$status=ResumeBanners($campid,$query_banners,$res[$i]['Login'],$res[$i]['Token']);
		if($status['error_str']!="")
		{	
			//var_dump($query_banners);
			logstr("<span style='color: red;'>Resume Banners for campaign $campid FAILED ".$status['error_str']."</span>\n");
		}
		else
			for($p=0;$p<count($query_banners);$p++){
				$db->query("UPDATE Banners SET IsDisabled=0, IsActive=1 WHERE BannerID='".$query_banners[$p]."' AND UserID='".$userid."'");
				$db->query("UPDATE Phrases SET IsDisabled=0 WHERE BannerID='".$query_banners[$p]."' AND UserID='".$userid."'");
				logstr("<span style='color: #3e56c0;'>Banner ".$query_banners[$p]." resumed\n");
				}
	}
*/



//do stop
$disable=getDisablePhrases($d_banners, $d_campaigns,$userid);			//d_keywords, d_banners, d_campaigns - все фразы, объявления, кaмпании, которые надо остановить

//var_dump($disable);
unset($disable_arr);
for($n=0;$n<count($disable);$n++)
{
	unset($m);
$m['CampaignID']=$disable[$n]['CampaignID'];
$m['AutoBroker']="Yes";
$m['PhraseID']=$disable[$n]['PhraseID'];
echo $m['PhraseID'];
echo "<br>";
$m['Price']=0.01;
$disable_arr[]=$m;
}

if($disable_arr!="")
$status=doUpdatePrices($disable_arr,$res[$i]['Login'],$res[$i]['Token']);
var_dump($status);
if($status["error_str"]!="")
{	
	logstr("Updating Prices for Login = ".$res[$i]['Login']." FAILED Error= ".$status["error_code"]." ".$status["error_str"]."\n\n");
	$Log="Ошибка обновления цен ".$status["error_code"]." ".$status["error_str"];
	$__ctime=timeStr();
	$db->query("INSERT INTO UpdateLog(__ctime,UserID,Login,Log) VALUES('".$__ctime."','".intval($userid)."','".$res[$i]['Login']."','".$Log."')");
	//$db->query("UPDATE Logins SET UpdateStatus=0 WHERE __id=".$res[$i]['__id']);
}


/*	foreach ($disable[1] as $key => $value) {
		unset($query_banners);
		$campid=$key;
		for($m=0;$m<count($value);$m++)
			$query_banners[]=$value[$m];
		$status=StopBanners($campid,$query_banners,$res[$i]['Login'],$res[$i]['Token']);
		if($status['error_str']!="")
		{
			logstr("<span style='color: red;'>Disable Banners for campaign $campid FAILED ".$status['error_str']."</span>\n");
		}
		else
			for($p=0;$p<count($query_banners);$p++){
				$db->query("UPDATE Banners SET IsDisabled=1, IsActive=0 WHERE BannerID='".$query_banners[$p]."' AND UserID='".$userid."'");
				$db->query("UPDATE Phrases SET IsDisabled=1 WHERE BannerID='".$query_banners[$p]."' AND UserID='".$userid."'");
				logstr("<span style='color: #721818;'>Banner ".$query_banners[$p]." stopped\n");
				}
	}*/




}

	}






function getDisablePhrases($banners,$campaigns,$userid)
{
	global $db;
$campaigns=unique($campaigns);
$banners=unique($banners);

$cntc=count($campaigns);
$cntb=count($banners);


for($i=0;$i<$cntc;$i++)								//если нужно остановаить всю компанию, не нужно останавливать её баннеры
{
	for($j=0;$j<$cntb;$j++)
	{
	
		if($campaigns[$i]==$banners[$j]['CampaignID'])
			unset($banners[$j]);
	}
			
}
$banners=array_values($banners);


$banners_res=array();
$campaigns_res=array();
	for($i=0;$i<count($banners);$i++)
	{
		$res=$db->getData("SELECT PhraseID, CampaignID FROM Phrases WHERE BannerID='".$banners[$i]['BannerID']."' AND UserID='".$userid."'");
		for($j=0;$j<count($res);$j++)
			$banners_res[]=array("PhraseID" => $res[$j]['PhraseID'], "CampaignID"=> $res[$j]['CampaignID']);
	}
	

	for($i=0;$i<count($campaigns);$i++)
	{
			$res=$db->getData("SELECT PhraseID FROM Phrases WHERE CampaignID='".$campaigns[$i]."' AND UserID='".$userid."'");
			for($j=0;$j<count($res);$j++)
			$campaigns_res[]=array("PhraseID" => $res[$j]['PhraseID'], "CampaignID"=>$campaigns[$i]);
	}


//var_dump($banners_res+$campaigns_res);
$result=array_merge($campaigns_res,$banners_res);
	return $result;
}





function getDisableArrs($keywords, $banners, $campaigns, $userid){
global $db;


$campaigns=unique($campaigns);
$banners=unique($banners);

$cntc=count($campaigns);
$cntb=count($banners);
$cntk=count($keywords);

for($i=0;$i<$cntc;$i++)								//если нужно остановаить всю компанию, не нужно останавливать её баннеры и слова
{
	for($j=0;$j<$cntb;$j++)
	{
	
		if($campaigns[$i]==$banners[$j]['CampaignID'])
			unset($banners[$j]);
	}
			
	for($k=0;$k<$cntk;$k++)
	{
		if($campaigns[$i]==$keywords[$k]['CampaignID'])
			unset($keywords[$k]);
	}
}
$banners=array_values($banners);
$keywords=array_values($keywords);

for($i=0;$i<$cntb;$i++)									// так же с объявлениями
{
	for($j=0;$j<$cntk;$j++)
	{
		if($banners[$i]['BannerID']==$keywords[$j]['BannerID'])
			unset($keywords[$j]);
	}
}
$keywords=array_values($keywords);

for($i=0;$i<$cntk;$i++)
{
	$arr[]=$keywords[$i]['BannerID'];
}

$arrcnt=array_count_values($arr);
/*
echo "<br>";
var_dump($campaigns);
echo "<br>";
var_dump($banners);
echo "<br>";
var_dump($keywords);
echo "<br>";
var_dump($arrcnt);
*/
/*
for($i=0;$i<count($banners);$i++)
$banners_res[]=$banners[$i]['BannerID'];
*/

/*
foreach ($arrcnt as $key => $value) {				//нельзя выключить все активные фразы объявления, поэтому выключаем все объявление
$count=$db->getData("SELECT CampaignID FROM Phrases WHERE BannerID='".$key."' AND UserID='".$userid."'");
if(count($count)<=$value)
{
//disable banner and delete all keywords
$total=count($keywords);	
	for($i=0;$i<$total;$i++)
		if($keywords[$i]['BannerID']==$key)
			unset($keywords[$i]);
$banners[]=array("BannerID" => $key, "CampaignID" => $count[0]['CampaignID']);
}
}
*/
foreach ($banners as $banner) {
	$campaignid=$banner['CampaignID'];
	$bannerid=$banner['BannerID'];
	$banners_res[$campaignid][]=$bannerid;
}



$keywords=array_values($keywords);
for($i=0;$i<count($keywords);$i++)
$keywords_res[]=$keywords[$i]['PhraseID'];

return array($campaigns,$banners_res,$keywords_res);





}










function getPrice($phrase, $strategy=1){
global $db;



if($strategy==1)
{
	$strid=$phrase['Strategy'];
	logstr("Phrase= '".$phrase['Name']."'");
}
else
	$strid=$phrase['Strategy2'];

$strategy=$db->getData("SELECT Formula FROM Strategies WHERE __id='".$strid."'");
$price=processStrategy($strategy[0]['Formula'],$phrase);

logstr(" Formula: <span style='color: #b1880a'>".htmlspecialchars($strategy[0]['Formula'])."</span> translated to: <span style='color: #b1880a'>".$price."</span>");

return $price;
}






function processStrategy($data,$phrase){
//echo $data;


$data=preg_replace("/([\s\)0-9])И([\s\(0-9])/", '$1 && $2', $data);
$data=preg_replace("/([\s\)0-9])ИЛИ([\s\(0-9])/", '$1 || $2', $data);


$data=preg_replace("/\n+/", " ", $data);
$data=preg_replace("/\t+/", " ", $data);
$data=preg_replace("/\s\s+/", " ", $data);

//$data=preg_replace("/([\s0-9\)]ИЛИ[\s0-9\()])/", "||", $data);
$data=str_replace("ВХОД_СПЕЦ", $phrase['PremiumMin'], $data);
$data=str_replace("1С", $phrase['PremiumMax'], $data);
$data=str_replace("2С", $phrase['Spec2'], $data);
$data=str_replace("ВХОД_ГАРАНТ", $phrase['Min'], $data);
$data=str_replace("1Г", $phrase['Max'], $data);
$data=str_replace("2Г", $phrase['Garant2'], $data);
$data=str_replace("3Г", $phrase['Garant3'], $data);
$data=str_replace("МАКС_ЦЕНА", $phrase['MaxPrice'], $data);
$data=str_replace("КЛИКИ", $phrase['Clicks'], $data);
$data=str_replace("ПОКАЗЫ", $phrase['Shows'], $data);
$data=str_replace("CTR", number_format($phrase['Clicks']/$phrase['Shows']*100, 2), $data);
//$data=str_replace("СТАВКА", '$res', $data);
$data=preg_replace("/(СТАВКА)\s*=(\s*[0-9А-Я_CTR*\+\.\-\*\/]+)/", '$price=$2;', $data);
$data=preg_replace("/\s*ЦИКЛ\s*\(\s*([0123])\s*<\s*z\s*<\s*([2-5])\)/", 'for($ind=$1;$ind<$2;$ind++)', $data);
$data=str_replace("ВЫХОД", 'break;', $data);

//$data=preg_replace("/(ЕСЛИ)\s*\(([А-Я0-9\s_\.\(\)&\|=><]*)\)\s*\{/", 'if($2){ $res=',$data);
//$data=preg_replace("/(\})(\s*\}*)/",";$1 $2",$data);


$garant=array($phrase['Max'],$phrase['Garant2'],$phrase['Garant3'], $phrase['Min']);
$spec=array($phrase['PremiumMax'],$phrase['Spec2'],$phrase['PremiumMin']);

$data=preg_replace("/(ЕСЛИ)/","if",$data);
$data=preg_replace("/(ИНАЧЕ)/","else",$data);
$data=str_replace("zГ",'$garant[$ind]',$data);
$data=str_replace("zC",'$spec[$ind]',$data);
$data=str_replace("zС",'$spec[$ind]',$data);



return $data;

}

 



?>