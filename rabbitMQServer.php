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
	return "test";
  }    
  return array("returnCode" => '0', 'message'=>"Server received request and processed");
}

$server = new rabbitMQServer("rabbitMQ.ini","testServer");

$server->process_requests('requestProcessor');
exit();
?>

