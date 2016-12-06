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
	
	
	$ver = $dbHelper->getNextVersion($request['bundleName']);
	echo "Next BundleVersion Request " . $request['bundleName'];	

	return $ver;
}

//return true if successful, false otherwise
function updateBundleVer($request)
{
        $dbHelper = new DatabaseHelper();
        $dbHelper->connect();
	
	echo "Update BundleVerions Request";
	
	return $dbHelper->updateVersion($request['bundleName']);
		
}

function deployBundle($request)
{
	$json = file_get_contents('./hosts.json');
	$arr = json_decode($json, true);
	
	//get ip of target 
	$target = ($arr[$request['bundle']][$request['branch']] );
	$ip = $target['ip'];

	echo "\n" . "Doing scp" . "\n";
	
	//get most recent bundle ver
        $dbHelper = new DatabaseHelper();
        $dbHelper->connect();
        $ver = $dbHelper->getNextVersion($request['bundleName']);
	$ver -= 1;	
	$bundle = $request['bundleName'] . $ver; 

	//scp to target 
        $command= 'scp /home/ses/bundles/' . $bundle . '.tgz ' . $target['user'] . '@' . $ip . ':/home/' . $target['user']. '/temp';
	shell_exec($command);
	
	//change ini according to targetmachine
	shell_exec('python3 /home/ses/deploymentProcessor/changeIni.py ' . $ip);
	
        //message target rabbitmq about recent deployment
        $client = new rabbitMQClient("targetRabbitMQ.ini","testServer");
	$req = array();
	$req['type'] = 'deployAlert';
	$req['version'] = $ver;
	$req['bundleName'] = $request['bundleName'];
	$req['bundleTar'] = $request['bundleName'] . $ver . ".tgz";
	$req['bundleFolder'] = substr($request['bundleName'], 3, -2);	

	$client->publish($req);		

	echo 'recived deploybundle';
	return $ip;
}

function rollbackBundle($request)
{

        $json = file_get_contents('./hosts.json');
        $arr = json_decode($json, true);

        //get ip of target 
        $target = ($arr[$request['bundle']][$request['branch']] );
        $ip = $target['ip'];

        echo "\n" . "Doing scp" . "\n";

        //get most recent bundle ver
        $dbHelper = new DatabaseHelper();
        $dbHelper->connect();
        $ver = $dbHelper->getNextVersion($request['bundleName']);
        $ver  = $ver - 2;
        $bundle = $request['bundleName'] . $ver;

        //scp to target 
        $command= 'scp /home/ses/bundles/' . $bundle . ".tgz " . $target['user'] . "@" . $ip . ":/home/" . $target['user']. "/temp";
	shell_exec($command);

        //change ini according to targetmachine
        shell_exec('python3 /home/ses/deploymentProcessor/changeIni.py ' . $ip);

        //message target rabbitmq about recent deployment
        $client = new rabbitMQClient("targetRabbitMQ.ini","testServer");
        $req = array();
        $req['type'] = 'deployAlert';
        $req['version'] = $ver;
        $req['bundleName'] = $request['bundleName'];
        $req['bundleTar'] = $request['bundleName'] . $ver . ".tgz";
        $client->publish($req);

	echo 'recived rollbakc';

	return $ip;
}


function deployAlert($request)
{
  $deployPath = '/home/ses/temp/';
  
  echo "\n Installing " . $request['bundleName']. $request['version']. " \n";

  //create tmp folder
  shell_exec('mkdir ' . $deployPath . 'tmp/');

  //decompress
  shell_exec('tar -xvf '. $deployPath .$request['bundleTar'] . ' -C ' . $deployPath . "tmp");
  
  $ini = (parse_ini_file($deployPath . 'tmp/' . 'bundle.ini'));
  $dstPath =  $ini[$request['bundleFolder']];
  
  //delete tmp folder
  shell_exec('rm -rf ' . $deployPath . 'tmp/' );

  //copy tar to correct path - 1 directory
  $rtPath = str_replace(PHP_EOL, '',  shell_exec('dirname '. $dstPath));
  shell_exec('cp ' . $deployPath . $request['bundleTar'] . ' ' . $rtPath);

  shell_exec( 'tar -xvf '. $rtPath . '/' . $request['bundleTar']. ' -C ' . $rtPath ); 

  shell_exec('rm ' . $rtPath . '/'. $request['bundleTar'] );

  //create tmp folder
  //copy tar into it
  //extract and read .ini for correct path
  //delete tmp folder 
  //copy tar to correct path - 1 directory
  //extract and delete .ini

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
	break;
    case "rollbackBundle":
	return rollbackBundle($request);	
  }    
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("rabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>

