<?php


require_once("code/funcs.php");
require_once('vendor/autoload.php');

$app = new \Slim\App;

require_once('code/member.php');
require_once('code/food.php');
$app->run();