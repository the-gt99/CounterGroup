<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('CounterGroup.php');

$auth = new CounterGroup('login', 'password');

if ($auth->login())
{
    $points = $auth->getAllPoints();

    $objId = $points['objId'];

    $events = $auth->getPointEvents($objId, '2024-07-01T00:00:00', '2024-07-31T23:59:59');
    var_dump($events);
}
else
{
    echo "Ошибка авторизации";
}
