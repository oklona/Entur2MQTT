
<?php

function getRESTData($url,$postdata)
{
	$ch = curl_init($url);                                                                      
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
				'Content-Type: application/graphql'
				)
			);
	curl_setopt($ch,CURLOPT_POSTFIELDS, $postdata);
	$result = curl_exec($ch);

	# Check for validity of data
	if (curl_getinfo($ch,CURLINFO_RESPONSE_CODE) == 302 ) {
		$returndata=curl_getinfo($ch,CURLINFO_REDIRECT_URL);
	}
	else {
		$returndata=json_decode($result,true);
	}
	
 return $returndata;
}

################################################################################################################################################
######		createconfig - Function to prompt for config data, and create config file.
################################################################################################################################################
function createconfig($refresh=false) {	
	$configcreated=false;
	global $folder;
	global $code;
	global $mosquitto_host;
	global $mosquitto_user;
	global $mosquitto_pass;
	global $topicbase;
	global $create;
	global $debug;
	
	$content="application/x-www-form-urlencoded";

	if($refresh == false) {
		$configdefault=array(
			'quay'=> 'NSR:Quay:7209',
			'interval'=>30,
			'nowText'=>'Now',
			'nrToReturn'=>2,
			'mosquitto_host'=> 'localhost',
			'mosquitto_user'=> '',
			'mosquitto_pass'=> '',
			'topicbase'=> '/entur/'
		);
		if(file_exists($folder . '/Entur2MQTT-config.php')){
			$config=array_replace($configdefault,include($folder . '/Entur2MQTT-config.php'));
		}
		else {
			$config=$configdefault;
		}
		$quay=readline("Quay to monitor (Find via https://stoppested.entur.org) [" . $config['quay'] . "]:");
		if($quay == "") {$quay=$config["quay"];}
		$interval=readline("What interval do you want to run the script at [" . $config["interval"] . "]: ");
		if($interval == "") {$interval=$config["interval"];}
		$nowText=readline('How do you want "Now" to be written (e.g. in your language) [' . $config["nowText"] . ']: ');
		if($nowText == "") {$nowText=$config["nowText"];}
		$nrToReturn=readline("How many departures do you want returned [" . $config["nrToReturn"] . "]: ");
		if($nrToReturn == "") {$nrToReturn=$config["nrToReturn"];}
		$mosquitto_host=readline("Type the name of your mosquitto host [" . $config["mosquitto_host"] . "]: ");
		if($mosquitto_host == "") {$mosquitto_host=$config["mosquitto_host"];}
		$mosquitto_user=readline("Type login-name for Mosquitto [" . $config["mosquitto_user"] . "]: ");
		if($mosquitto_user == "") {$mosquitto_user=$config["mosquitto_user"];}
		if (strlen($mosquitto_user) >> 0 ) {
			$mosquitto_pass=readline("Type the password for your mosquitto user (will be saved in PLAIN text) [" . $config["mosquitto_pass"] . "]: ");
			if($mosquitto_pass == "") {$mosquitto_pass=$config["mosquitto_pass"];}
		}
		else {
			$mosquitto_pass="";
		}
		$topicbase=readline('Type the base topic name to use for Mosquitto [' . $config["topicbase"] . ']: ');
		if($topicbase == "") {$topicbase=$config["topicbase"];}
		if (strlen($topicbase) == 0) {
			$topicbase="/entur/";
		}
		if (substr($topicbase,-1) <> "/") {
			$topicbase = $topicbase . "/";
		}
	
		$config="<?php" . PHP_EOL . "return array(" . PHP_EOL ;
		$config = $config . "	'quay'=> '" . $quay . "'," . PHP_EOL;
		$config = $config . "	'interval'=> '" . $interval . "'," . PHP_EOL;
		$config = $config . "	'nowText'=> '" . $nowText . "'," . PHP_EOL;
		$config = $config . "	'nrToReturn'=> '" . $nrToReturn . "'," . PHP_EOL;
		$config = $config . "	'mosquitto_host'=> '" . $mosquitto_host . "'," . PHP_EOL;
		$config = $config . "	'mosquitto_user'=> '" . $mosquitto_user . "'," . PHP_EOL;
		$config = $config . "	'mosquitto_pass'=> '" . $mosquitto_pass . "'," . PHP_EOL;
		$config = $config . "	'topicbase'=> '" . $topicbase . "'" . PHP_EOL;
		$config = $config . ");" . PHP_EOL . "?>" . PHP_EOL . PHP_EOL;

		if (file_put_contents($folder . "/Entur2MQTT-config.php", $config) <> false ) {
			if($debug){print "Configuration file created!" . PHP_EOL;}
			$configcreated=true;
		}
	}

	return $configcreated;
}


################################################################################################################################################
######
######		This is the main script block
######
################################################################################################################################################
require("phpMQTT.php");

$folder=dirname($_SERVER['PHP_SELF']);
$forcedquay="";

$shortopts="dDscq:j";
$longopts=array("dump","debug","single","create","quay:","json");
$options=getopt($shortopts,$longopts);

# Map options to variables, to simplify further script processing...
$debug=(array_key_exists("D",$options) || array_key_exists("debug",$options));
$dump=(array_key_exists("d",$options) || array_key_exists("dump",$options));
$single=(array_key_exists("s",$options) || array_key_exists("single",$options));
$create=(array_key_exists("c",$options) || array_key_exists("create",$options));
$returnjson=(array_key_exists("j",$options) || array_key_exists("json",$options));
if(array_key_exists("q",$options)) {
	$forcedquay=$options['q'];
}
if(array_key_exists("quay",$options)) {
	$forcedquay=$options['quay'];
}


if($dump){$single=true;}

if ((file_exists($folder . '/Entur2MQTT-config.php') == false ) || $create) {
	$configcreated=createconfig();
	if($configcreated == false) {
		exit("Failed to create config! " . PHP_EOL);
	}
}

$config = include($folder.'/Entur2MQTT-config.php');
if ($forcedquay != "" ) {
	$config['quay']=$forcedquay;
}

$url="https://api.entur.io/journey-planner/v2/graphql";
$second=99;

$mosquitto_host=$config['mosquitto_host'];
$mosquitto_user=$config['mosquitto_user'];
$mosquitto_pass=$config['mosquitto_pass'];
$topicbase=$config['topicbase'];

$client_id = "Entur".$config['quay']; // make sure this is unique for connecting to sever - you could use uniqid()

$mqtt = new Bluerhinos\phpMQTT($mosquitto_host, "1883", $client_id);

if(!$mqtt->connect(true, NULL, $mosquitto_user, $mosquitto_pass)) {
	exit(1);
}

if($create) {
	# Option create should exit after creating new config
	exit(0);
}

if($single) {
	retrieveandpublish($url,$mqtt);
	exit(0);
}


$count=$config['interval'];
while($mqtt->proc()){
	if ( $count==$config['interval']) {
		retrieveandpublish($url,$mqtt);
		$count=0;
	}
	sleep(1);
	$count = $count + 1;
}

$mqtt->close();

// Retrieveing information
function retrieveandpublish($url,$mqtt) {
	global $mosquitto_host;
	global $mosquitto_user;
	global $mosquitto_pass;
	global $topicbase;
	global $dump;
	global $json;
	global $debug;
	global $config;
	global $returnjson;

	$topic=$topicbase . $config['quay'] . '/';

	if(strstr($config['quay'],"StopPlace")) {
		$stopPlace=true;
		$postdata='{stopPlace(id:"'.$config['quay'].'"){id name estimatedCalls(timeRange: 3600, numberOfDepartures: '.$config['nrToReturn'].'){expectedDepartureTime,serviceJourney{line{publicCode}},destinationDisplay{frontText},quay{id,name}}}}';
	}
	else {
		$stopPlace=false;
		$postdata='{quay(id:"'.$config['quay'].'"){id name estimatedCalls(timeRange: 3600, numberOfDepartures: '.$config['nrToReturn'].'){expectedDepartureTime,serviceJourney{line{publicCode}},destinationDisplay{frontText}}}}';
	}

	$currentdata=array(getRESTData($url,$postdata));


	if (count($currentdata) >> 0) {

		if($dump) {var_dump($currentdata);}
		
		foreach ($currentdata as $node) {
			if(is_array($node) == 0 ) {
				echo "Unreachable" . PHP_EOL;
				exit();
			}
			if(strstr($config['quay'],"StopPlace")) {
				$level4=$node['data']['stopPlace']['estimatedCalls'];
			}
			else {
				$level4=$node['data']['quay']['estimatedCalls'];
			}
			$count=count($level4);
			if($debug) {print "Count of returned elements: " . $count . PHP_EOL;}

			$found=false;
			if($count>0) {
				$tz=getenv("TZ");
                if ( ! $tz ) { $tz = "Erope/Oslo"; }
                $dtz=new DateTimeZone($tz);

				$naa=new DateTime("now");
				$mqtt->publish($topic . "time", $naa->format('H:i:s'));
				$mqtt->publish($topic . "date", $naa->format('Y-m-d'));
				for($i = 0; $i < $count; $i++) {
					$ankomst=new DateTime($level4[$i]["expectedDepartureTime"]);
					$interval = $ankomst->diff($naa);
					$first=$interval->format('%i');
					if ($first == 0 ) {
						$first=$config['nowText'];
					}
					$firstpublicCode=$level4[$i]['serviceJourney']['line']['publicCode'];
					$firstDestination=$level4[$i]['destinationDisplay']['frontText'];
					if($stopPlace) {
						$firstquay=$level4[$i]['quay']['id'];
						$firstquayname=$level4[$i]['quay']['name'];
					}
					if($returnjson){
						$jsondata=array(
							'departsIn'=>$first,
							'publicCode'=>$firstpublicCode,
							'Destination'=>$firstDestination
						);

						if($debug) {var_dump($jsondata);}

						if ($stopPlace) {
							$jsondata['quay'] = $firstquay;
							$jsondata['quayname'] = $firstquayname;
							#'$jsondata += ['quay'=>$firstquay,'quayname'=>$firstquayname];
						}
						if($debug) {var_dump($jsondata);}

						$publishData=json_encode($jsondata);
						$mqtt->publish($topic . "departure$i", $publishData);
					}
					else {
						$mqtt->publish($topic . "departsIn$i", $first);
						$mqtt->publish($topic . "publicCode$i", $firstpublicCode);
						$mqtt->publish($topic . "Destination$i", $firstDestination);
						if ($stopPlace) {
							$mqtt->publish($topic . "quay$i", $firstquay);
							$mqtt->publish($topic . "quayname$i", $firstquayname);
						}
					}
				}
			}

			if($count < $config['nrToReturn']) {
				for($i = $count - 1; $i << $config['nrToReturn']; $i++ ) {
					$mqtt->publish($topic . "time$i", "--");	
				}
			}
		}
	}
}			
?>

