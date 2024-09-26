<?php
class CounterGroup {
    private $baseUrl = 'https://my.cnord.net';
    private $email;
    private $password;
    private $cookies = [];

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
            $this->extractCookies($response['headers']);
            return [
                "isAuth" => true
            ];
        }

        return [
            "isAuth" => false,
            "err" => json_encode($response)
        ];
    }

    public function getAllPoints() {
        if (empty($this->cookies)) {
            throw new Exception('Не авторизован. Сначала выполните вход.');
        }

        $url = $this->baseUrl . '/object_manager';
        $headers = [
            'Accept: */*',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Cookie: ' . $this->getCookieHeader(),
            'Origin: ' . $this->baseUrl,
            'Referer: ' . $this->baseUrl . '/object_manager',
            'X-Requested-With: XMLHttpRequest'
        ];

        $data = 'user_timezone_offset=240';

        $response = $this->sendRequest($url, 'GET', $headers, $data);
        $objects = $this->parseObjectList($response['body']);
        $thisObject = $this->parseObjectInputData($response['body']);
        array_push($objects, $thisObject);

        return $objects;
    }

    public function getPointEvents($pointId, $startDate, $endDate, $timezoneOffset = 240) {
        if (empty($this->cookies)) {
            throw new Exception('Не авторизован. Сначала выполните вход.');
        }
        $url = $this->baseUrl . "/object_manager/objects/{$pointId}/arm_disarm_report/data";

        $headers = [
            'Accept: */*',
            'Accept-Language: ru,ru-RU;q=0.9,en-US;q=0.8,en;q=0.7',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Cookie: ' . $this->getCookieHeader(),
            'DNT: 1',
            'Origin: ' . $this->baseUrl,
            'Referer: ' . $this->baseUrl . "/object_manager/objects/{$pointId}",
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-origin',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'X-Requested-With: XMLHttpRequest',
            'sec-ch-ua: "Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"'
        ];

        $data = http_build_query([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'user_timezone_offset' => $timezoneOffset
        ]);

        $response = $this->sendRequest($url, 'POST', $headers, $data);

        if ($response['http_code'] == 400) {
            throw new Exception('Ошибка 400: Неверный запрос. Проверьте параметры. Тело ответа: ' . $response['body']);
        }

        return $this->parsePointEvents($response['body']);
    }

    private function sendRequest($url, $method, $headers = [], $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

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

    private function extractCookies($headers) {
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
        foreach($matches[1] as $item) {
            parse_str($item, $cookie);
            $this->cookies = array_merge($this->cookies, $cookie);
        }
    }

    private function getCookieHeader() {
        $cookieStrings = [];
        foreach ($this->cookies as $name => $value) {
            $cookieStrings[] = $name . '=' . $value;
        }
        return implode('; ', $cookieStrings);
    }

    private function parseObjectList($html) {
        $objects = [];

        $pattern = '/<li><a href="\/object_manager\/objects\/(\d+)">(.*?)<\/a><\/li>/';

        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $objects[] = [
                'objId' => intval($match[1]),
                'name' => html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            ];
        }

        return $objects;
    }

    private function parseObjectInputData($html) {
        $result = [
            'objId' => null,
            'name' => ''
        ];

        $pattern = '/<input[^>]*id="edit-object-name-input"[^>]*>/';
        if (preg_match($pattern, $html, $matches)) {
            $input_html = $matches[0];

            if (preg_match('/data-object-name="([^"]*)"/', $input_html, $name_matches)) {
                $result['name'] = html_entity_decode($name_matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if (preg_match('/data-save-url="\/object_manager\/objects\/(\d+)\/save_custom_object_name"/', $input_html, $id_matches)) {
                $result['objId'] = intval($id_matches[1]);
            }
        }

        return $result;
    }

    private function parsePointEvents($html) {
        $events = [];
        $pattern = '/<tr(?:\s+class="[^"]*")?\s*>.*?<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>.*?<p(?:\s+class="([^"]*)")?\s*>(.*?)<\/p>.*?<\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/s';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        $currentDate = null;
        $currentYear = date('Y');

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
                $dateTime = $this->convertToUnixTime($currentDate, $time, $currentYear);

                if (!$dateTime) {
                    $dateTime = $currentDate;
                }

                if ($dateTime !== false) {
                    if (!isset($events[$dateTime])) {
                        $events[$dateTime] = [];
                    }

                    $status = $eventClass;

                    switch($eventClass)
                    {
                        case "arm":
                            $status = "closed";
                            break;

                        case "disarming":
                            $status = "opened";
                            break;

                        case "alarm":
                            $status = "warning";
                            break;
                    }

                    $events[$dateTime][] = [
                        'ruDate' => $currentDate,
                        'time' => $time,
                        'event' => $event,
                        'event_class' => $eventClass,
                        'status' => $status,
                        'details' => $details
                    ];
                }
            }
        }

        krsort($events);
        return $events;
    }

    private function convertToUnixTime($date, $time, $year) {
        if (empty($date) || $date == '—' || empty($time) || $time == '—' || !$this->validateTime($time)) {
            return false;
        }

        $pattern = '/(\d+)\s+(января|февраля|марта|апреля|мая|июня|июля|августа|сентября|октября|ноября|декабря),\s+(пн|вт|ср|чт|пт|сб|вс)/u';

        if (preg_match($pattern, $date, $matches)) {
            $day = $matches[1];
            $month = $matches[2];

            $months = [
                'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4,
                'мая' => 5, 'июня' => 6, 'июля' => 7, 'августа' => 8,
                'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12
            ];
            $monthNum = $months[$month];

            $dateTimeString = sprintf('%d-%02d-%02d %s:00', $year, $monthNum, $day, $time);

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




