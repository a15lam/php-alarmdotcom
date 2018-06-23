<?php
require __DIR__ . '/../vendor/autoload.php';

$alarm = new \a15lam\AlarmDotCom\AlarmDotCom();
$sensors = $alarm->sensors(\a15lam\Workspace\Utility\ArrayFunc::get($argv, 1));

print_r($sensors);