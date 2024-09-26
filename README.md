# CounterGroup

CounterGroup - это PHP класс для авторизации и получения данных с сайта [https://my.cnord.net/](https://my.cnord.net/). Он предоставляет удобный интерфейс для входа в систему, получения информации о точках и событиях, связанных с ними.

## Возможности

- Авторизация на сайте.
- Получение информации о всех точках.
- Получение событий по конкретной точке.
- Парсинг и структурирование полученных данных.
- Конвертация дат в Unix timestamp с учетом часового пояса Саратова.
- Обработка ошибок через исключения.

## Требования

- PHP 7.0 или выше.
- Расширение cURL для PHP.

## Установка

1. Скопируйте файл `CounterGroup.php` в директорию вашего проекта.
2. Подключите файл в вашем скрипте:

```php
require_once 'path/to/CounterGroup.php';
```

## Использование

### Инициализация и авторизация

```php
$auth = new CounterGroup('your_email@example.com', 'your_password');
if ($auth->login()) {
    echo "Авторизация успешна";
} else {
    echo "Ошибка авторизации";
}
```

### Получение информации о всех точках

```php
$pointsInfo = $auth->getAllPoints();
print_r($pointsInfo);
```

### Получение событий по конкретной точке

```php
$objId = $pointsInfo['objId'];
$startDate = '2024-07-01T00:00:00';
$endDate = '2024-07-31T23:59:59';
$events = $auth->getPointEvents($objId, $startDate, $endDate);
print_r($events);
```

### Пример использования в тестовом скрипте
Для тестирования функциональности можно использовать файл test.php. Пример использования:

```php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('CounterGroup.php');

try {
    $CounterGroup = new CounterGroup('your_email@example.com', 'your_password');
    if (!$CounterGroup->login()) {
        throw new Exception("Ошибка авторизации");
    }

    $points = $CounterGroup->getAllPoints();
    print_r($points);

    $objId = $points[0]['objId'];
    $events = $CounterGroup->getPointEvents($objId, '2024-07-01', '2024-07-07');
    print_r($events);
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
```

## Структура данных

### Информация о точке

```php
[
    'objFullName' => 'Полное название объекта',
    'objId' => 'Идентификатор объекта',
    'objName' => 'Название объекта',
    'address' => 'Адрес объекта',
    'signaling' => 'Информация о сигнализации',
    'anyInf' => [
        'Дополнительный ключ' => 'Дополнительное значение'
    ]
]
```

### События

```php
[
    'Дата (в формате Unix timestamp или строка даты)' => [
        [
            'ruDate' => 'Дата на русском',
            'time' => 'Время события',
            'event' => 'Название события',
            'event_class' => 'Класс события (например, "alarm" или "info")',
        ]
    ]
]
```

## Обработка ошибок
Класс использует исключения для обработки ошибок. Рекомендуется оборачивать вызовы методов в блок try-catch:

```php
try {
    $events = $auth->getPointEvents($objId, $startDate, $endDate);
    // Обработка полученных данных
} catch (Exception $e) {
    echo "Произошла ошибка: " . $e->getMessage();
}
```

## Примечания

- Класс работает с часовым поясом Саратова (UTC+4) при конвертации дат.
- При изменении структуры HTML на сайте может потребоваться обновление методов парсинга.
- Метод parsePointEvents() сохраняет оригинальную дату на русском языке в поле 'ruDate'.
- Если конвертация в Unix timestamp не удалась, используется оригинальная строка даты в качестве ключа.

## Лицензия
[MIT License](https://opensource.org/licenses/MIT)


Этот `README.md` файл включает описание проекта, примеры использования, инструкции по установке и обработке ошибок, а также структуру данных. Вы можете сразу использовать его для вашего проекта.
