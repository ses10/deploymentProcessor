#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');
require_once('bundleDBHelper.inc');

function logMessage($request)
{
	echo 'a log has been recieved';
	echo $request['message'];

	$logFile = fopen("log.txt", "a");

	fwrite($logFile, $request['message'] ."\n");

	return true;
}

//returns the next bundle version # for a machine
function nextBundleVer($request)
{
	$dbHelper = new DatabaseHelper();
	$dbHelper->connect();
	
	
	$ver = $dbHelper->getNextVersion($request['bundle']);
	echo "the next version is " . $ver;	

	return $ver;
}

//return true if successful, false otherwise
function updateBundleVer($request)
{
        $dbHelper = new DatabaseHelper();
        $dbHelper->connect();
	
	return $dbHelper->updateVersion($request['bundle']);
		
}

function deployBundle($request)
{
	$json = file_get_contents('./hosts.json');
	$arr = json_decode($json, true);
	
	//get ip of target 
	$target = ($arr[$request['bundle']][$request['machine']] );
	$ip = $target['ip'];
	
	echo "\n" . "Doing scp" . "\n";
	
        $dbHelper = new DatabaseHelper();
        $dbHelper->connect();
        $ver = $dbHelper->getNextVersion($request['bundle']);
	$ver -= 1;	
	$bundle = $request['bundle'] . $ver; 

	//scp 
	$command= 'scp /home/ses/bundles/' . $bundle . ".tgz " . $target['user'] . "@" . $ip . ":/home/" . $target['user']. "/temp";
	shell_exec($command);
	
	//change ini according to targetmachine
	shell_exec('python3 /home/ses/deploymentProcessor/changeIni.py ' . $ip);
	
        //message target rabbitmq about recent deployment
        $client = new rabbitMQClient("targetRabbitMQ.ini","testServer");
	$request = array();
	$request['type'] = 'deployAlert';
	$request['version'] = $ver;
	$request['message'] = 'hey gaybo';
	$client->publish($request);
		

	echo 'recived deploybundle';
	return $ip;
}

function deployAlert($request)
{
echo "\n Installing " . $request['version']. " \n";
}

function requestProcessor($request)
{
  echo "received request".PHP_EOL;
  //var_dump($request);
  if(!isset($request['type']))
  {
    return "ERROR: unsupported message type";
  }
  switch ($request['type'])
  {
    case "bundleRequest":
	return nextBundleVer($request);
    case "updateBundleVer":
	return updateBundleVer($request);
    case "deployBundle":
	return deployBundle($request);
    case "deployAlert":
	deployAlert($request);	
  }    
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("rabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>

