#!/usr/bin/php
<?php
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("listener.ini", "testServer");

$request = array();
$request['type'] = 'deployAlert';

print_r($client->send_request($request));

?>
