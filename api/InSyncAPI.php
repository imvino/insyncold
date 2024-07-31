<?php
ini_set("soap.wsdl_cache_enabled", "0");
require_once(dirname(__FILE__) . '/../helpers/constants.php');

//Create a new soap server
$server = new SoapServer(SITE_BASE_URL . "api/InSyncAPI.wsdl", array('encoding' => 'UTF-8'));

require_once 'rest/Core.php';

$server->setClass("Core");

$server->handle();
