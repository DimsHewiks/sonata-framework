# Sonata Framework

Минималистичный PHP-фреймворк для быстрых API: роутинг на атрибутах, DI-контейнер, DTO-валидация и кеш роутинга.

## Установка
```bash
composer require sonata/framework
```

## Быстрый старт
Минимальная точка входа с роутингом и DI:
```php
<?php

use Sonata\Framework\Container\Container;
use Sonata\Framework\Router;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

require __DIR__ . '/vendor/autoload.php';

$_ENV['SONATA_BASE_PATH'] = __DIR__;
putenv('SONATA_BASE_PATH=' . $_ENV['SONATA_BASE_PATH']);

$container = new Container();

$container->set(ValidatorInterface::class, static function () {
    return Validation::createValidatorBuilder()
        ->enableAttributeMapping()
        ->getValidator();
});

$container->set(PDO::class, static function (): PDO {
    return new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s', $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['DB_NAME']),
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
});

$router = new Router($container, $_ENV['APP_ENV'] === 'dev', null, __DIR__);
$router->registerControllers();
$router->dispatch(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/',
    $_SERVER['REQUEST_METHOD']
);
```

## Атрибуты (теги)
Используются для маршрутизации и документации:
- `#[Controller(prefix: '/api')]` — объявляет контроллер и префикс.
- `#[Route(path: '/users', method: 'GET', summary: '...', description: '...')]` — маршрут метода.
- `#[From('json'|'query'|'formData')]` — источник данных для DTO.
- `#[Inject]` — внедрение зависимости в конструктор.
- `#[Response(ClassName::class, isArray: true)]` — тип ответа для OpenAPI.
- `#[Tag('Имя', 'Описание')]` — группа в документации.
- `#[NoAuth]` — пометка метода/контроллера, который не требует авторизации (используется middleware).

Пример:
```php
use Sonata\Framework\Attributes\Controller;
use Sonata\Framework\Attributes\Route;
use Sonata\Framework\Attributes\From;
use Sonata\Framework\Attributes\Tag;

#[Controller(prefix: '/api')]
#[Tag('Пользователи')]
final class UserController
{
    #[Route(path: '/users', method: 'POST', summary: 'Создать пользователя')]
    public function create(#[From('json')] UserCreateDto $dto): array
    {
        return ['ok' => true];
    }
}
```

## Команды
В приложении можно добавить команды, которые используют встроенный кеш:
- `cache:build` — построить кеш маршрутов (через `RoutesCache`).
- `cache:clear` — очистить кеш маршрутов.

## Middleware
Фреймворк поддерживает цепочку middleware для обработки запроса до контроллера.

Пример подключения:
```php
$router = new Router($container, $debug, null, __DIR__);
$router->addMiddleware(\App\Middleware\AuthMiddleware::class);
```

Middleware получает контекст:
- `controller` — класс контроллера
- `action` — метод контроллера
- `uri`, `method`
- `route`

## Пример NoAuth
```php
use Sonata\Framework\Attributes\NoAuth;

final class AuthController
{
    #[NoAuth]
    public function login(): array { /* ... */ }
}
```
## Логика
- **Роутинг**: `Router` сканирует контроллеры и строит маршруты по атрибутам. Поддерживаются параметры вида `/users/{id}`.
- **DI**: `Container` автосвязывает зависимости по типам, `#[Inject]` позволяет указывать явные идентификаторы.
- **Валидация**: DTO наследуется от `ParamsDTO` и валидируется через `symfony/validator`.
- **Кеш**: `RoutesCache` кеширует список маршрутов, `OpenApiCache` — OpenAPI-спеку.

## Переменные окружения
- `SONATA_BASE_PATH` — базовая директория приложения (для сканирования и кешей).
- `APP_ENV` — режим (`dev`/`prod`), влияет на кеши.
