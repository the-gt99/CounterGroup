<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('CounterGroup.php');

function getFullInfoFor7days() {
    try
    {
        $CounterGroup = new CounterGroup('login', 'password');

        $loginRes = $CounterGroup->login();

        if(!$loginRes['isAuth'])
            return [
                "err" => "Ошибка авторизации: {$loginRes['err']}"
            ];

        $points = $CounterGroup->getAllPoints();

        $nowTime = time();
        $backTime = time() - 7 * 24 * 60 * 60;

        $nowDate = date("Y-m-d 23:59:59", $nowTime);
        $backDate = date("Y-m-d 00:00:00", $backTime);

        foreach($points as &$point)
        {
            $events = $CounterGroup->getPointEvents($point['objId'], $backDate, $nowDate);

            $point['events'] = $events;
            $point['status'] = "error";

            if(count($events) > 0)
            {
                $point['status'] = $events[array_key_first($events)][0]['status'];
            }
        }

        return $points;
    }
    catch (Exception $e)
    {
        return [
            "err" => "Произошла ошибка: " . $e->getMessage()
        ];
    }
}

function getEventsByObjectId($id) {
    try
    {
        $CounterGroup = new CounterGroup('login', 'password');

        $loginRes = $CounterGroup->login();

        if(!$loginRes['isAuth'])
            return [
                "err" => "Ошибка авторизации: {$loginRes['err']}"
            ];

        $nowTime = time();
        $backTime = time() - 7 * 24 * 60 * 60;

        $nowDate = date("Y-m-d 23:59:59", $nowTime);
        $backDate = date("Y-m-d 00:00:00", $backTime);

        $events = $CounterGroup->getPointEvents($id, $backDate, $nowDate);

        return $events;
    }
    catch (Exception $e)
    {
        return [
            "err" => "Произошла ошибка: " . $e->getMessage()
        ];
    }
}


$points = getFullInfoFor7days();

if(isset($points['err']))
    echo($points['err']);

$events = getEventsByObjectId("id");

if(isset($events['err']))
    echo($events['err']);


var_dump($points, $events);




