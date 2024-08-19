<?php

class CounterGroup {
    private $baseUrl = 'https://my.cnord.net';
    private $email;
    private $password;
    private $token;

    public function __construct($email, $password) {
        $this->email = $email;
        $this->password = $password;
    }

    public function login() {
        $url = $this->baseUrl . '/object_manager/login';
        $data = json_encode([
            'email' => $this->email,
            'password' => $this->password
        ]);

        $headers = [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Content-Type: application/json',
            'Origin: ' . $this->baseUrl,
            'Referer: ' . $this->baseUrl . '/object_manager/login',
            'X-Requested-With: XMLHttpRequest'
        ];

        $response = $this->sendRequest($url, 'POST', $headers, $data);

        if ($response['http_code'] == 200) {
            $this->extractToken($response['headers']);
            return true;
        }

        return false;
    }

    public function getObjects($page = 1) {
        if (!$this->token) {
            throw new Exception('Не авторизован. Сначала выполните вход.');
        }

        $url = $this->baseUrl . '/object_manager/objects/' . $page;
        $headers = [
            'Cookie: object_manager="' . $this->token . '"'
        ];

        return $this->sendRequest($url, 'GET', $headers);
    }

    public function getAllPoints() {
        if (!$this->token) {
            throw new Exception('Не авторизован. Сначала выполните вход.');
        }

        $url = $this->baseUrl . '/object_manager/objects/1/info';
        $headers = [
            'Accept: */*',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Cookie: object_manager="' . $this->token . '"',
            'Origin: ' . $this->baseUrl,
            'Referer: ' . $this->baseUrl . '/object_manager/objects/1',
            'X-Requested-With: XMLHttpRequest'
        ];

        $data = 'user_timezone_offset=240';

        $response = $this->sendRequest($url, 'POST', $headers, $data);
        return $this->parsePointInfo($response['body']);
    }

    public function getPointEvents($pointId, $startDate, $endDate, $timezoneOffset = 240) {
        if (!$this->token) {
            throw new Exception('Не авторизован. Сначала выполните вход.');
        }

        $url = $this->baseUrl . "/object_manager/objects/{$pointId}/arm_disarm_report/data";
        $headers = [
            'Accept: */*',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Cookie: object_manager="' . $this->token . '"',
            'Origin: ' . $this->baseUrl,
            'Referer: ' . $this->baseUrl . "/object_manager/objects/{$pointId}",
            'X-Requested-With: XMLHttpRequest'
        ];

        $data = http_build_query([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'user_timezone_offset' => $timezoneOffset
        ]);

        $response = $this->sendRequest($url, 'POST', $headers, $data);
        return $this->parsePointEvents($response['body']);
    }

    private function sendRequest($url, $method, $headers = [], $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'headers' => substr($response, 0, $header_size),
            'body' => substr($response, $header_size),
            'http_code' => $http_code
        ];
    }

    private function extractToken($headers) {
        if (preg_match('/object_manager="([^"]+)"/', $headers, $matches)) {
            $this->token = $matches[1];
        }
    }

    private function parsePointInfo($html) {
        $info = [];
        $pattern = '/<tr>\s*<th>(.*?)<\/th>\s*<td>(.*?)<\/td>\s*<\/tr>/s';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = trim(strip_tags($match[1]));
            $value = trim(strip_tags($match[2]));

            if($key == "Объект")
            {
                $info['objFullName'] = $value;
                preg_match_all('/№ (\d{1,}), (.*?)$/m', $value, $objInf);
                $info['objId'] = $objInf[1][0];
                $info['objName'] = $objInf[2][0];
            }
            else if($key == "Адрес")
            {
                $info['address'] = $value;
            }
            else if($key ==  "Сигнализация")
            {
                $info['signaling'] = $value;
            }
            else
            {
                $info['anyInf'][$key] = $value;
            }
        }

        return $info;
    }

    private function parsePointEvents($html) {
        $events = [];
        $pattern = '/<tr(?:\s+class="[^"]*")?\s*>.*?<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>.*?<p(?:\s+class="([^"]*)")?\s*>(.*?)<\/p>.*?<\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/s';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        $currentDate = null;
        $currentYear = date('Y'); // Текущий год, так как в данных он не указан

        foreach ($matches as $match) {
            $date = trim(strip_tags($match[1]));
            $time = trim(strip_tags($match[2]));
            $eventClass = isset($match[3]) ? $match[3] : '';
            $event = trim(strip_tags($match[4]));
            $details = str_replace("&nbsp;", "", trim(strip_tags($match[5])));

            if ($date !== '&nbsp;' && $date !== '' && $date !== '—') {
                $currentDate = $date;
            }

            if ($currentDate && $time !== '—' && $event !== '—' && $this->validateTime($time)) {
                // Преобразование даты и времени в Unix timestamp
                $dateTime = $this->convertToUnixTime($currentDate, $time, $currentYear);

                if(!$dateTime)
                    $dateTime = $currentDate;

                if ($dateTime !== false) {
                    if (!isset($events[$dateTime])) {
                        $events[$dateTime] = [];
                    }

                    $events[$dateTime][] = [
                        'ruDate' => $currentDate,
                        'time' => $time,
                        'event' => $event,
                        'event_class' => $eventClass,
                        'details' => $details
                    ];
                }
            }
        }

        // Сортировка событий по Unix timestamp (ключу массива)
        //ksort($events);

        return $events;
    }

    private function convertToUnixTime($date, $time, $year) {
        // Проверка на пустые значения
        if (empty($date) || $date == '—' || empty($time) || $time == '—' || !$this->validateTime($time)) {
            return false;
        }

        $pattern = '/(\d+)\s+(января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря),\s+(пн|вт|ср|чт|пт|сб|вс)/u';


        if (preg_match($pattern, $date, $matches)) {
            $day = $matches[1];
            $month = $matches[2];

            // Преобразование названия месяца в числовой формат
            $months = [
                'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4,
                'мая' => 5, 'июня' => 6, 'июля' => 7, 'августа' => 8,
                'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12
            ];
            $monthNum = $months[$month];

            // Формирование полной строки даты и времени
            $dateTimeString = sprintf('%d-%s-%s %s:00', $year, $monthNum, $day, $time);

            // Создание объекта DateTime с учетом часового пояса Саратова (UTC+4)
            try {
                $dateTime = new DateTime($dateTimeString, new DateTimeZone('Europe/Saratov'));
                return $dateTime->getTimestamp();
            } catch (Exception $e) {
                error_log("Ошибка при конвертации даты и времени: " . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    private function validateTime($time) {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }
}