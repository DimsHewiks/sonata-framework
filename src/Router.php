<?php
namespace Sonata\Framework;

use Sonata\Framework\Attributes\From;
use Sonata\Framework\Cache\RoutesCache;
use Sonata\Framework\Routing\ControllerDirectoryResolver;

class Router
{
    private array $routes = [];
    private RoutesCache $cache;
    private bool $debug;
    private array $controllerDirectories;
    private string $basePath;

    private \Sonata\Framework\Container\ContainerInterface $container;

    public function __construct(
        \Sonata\Framework\Container\ContainerInterface $container,
        bool $debug = false,
        ?array $controllerDirectories = null,
        ?string $basePath = null
    )
    {
        $this->container = $container;
        $this->debug = $debug;
        $this->basePath = $this->resolveBasePath($basePath);
        $this->cache = new RoutesCache($this->basePath);
        $this->controllerDirectories = $controllerDirectories
            ?? ControllerDirectoryResolver::resolve($this->basePath);
    }

    public function addRoute(string $pattern, string $method, string $controller, string $action): void
    {
        $this->routes[] = [
            'pattern' => $this->convertRoutePattern($pattern),
            'method' => $method,
            'controller' => $controller,
            'action' => $action
        ];
    }
    private function convertRoutePattern(string $pattern): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '([^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }



    public function dispatch(string $uri, string $method): void
    {
        $uri = strtok($uri, '?');

        foreach ($this->routes as $route) {
            $matches = $this->matchRoute($route, $uri, $method);
            if ($matches !== null) {
                try {
                    $controller = $this->container->get($route['controller']);
                    $methodName = $route['action'];

                    // Передаём URL-параметры в resolveParameters
                    $params = $this->resolveParameters($controller, $methodName, $matches);
                    $response = call_user_func_array([$controller, $methodName], $params);

                    $this->sendResponse($response);
                    return;

                } catch (\Throwable $e) {
                    $this->sendError(500, $e->getMessage(), $e);
                    return;
                }
            }
        }

        $this->sendNotFound($uri, $method);
    }

    public function registerRoutesFromAnnotations(string $controllerClass): void
    {
        try {
            $reflection = new \ReflectionClass($controllerClass);

            foreach ($reflection->getAttributes(\Sonata\Framework\Attributes\Route::class) as $classAttr) {
                $classRoute = $classAttr->newInstance();

                foreach ($reflection->getMethods() as $method) {
                    foreach ($method->getAttributes(\Sonata\Framework\Attributes\Route::class) as $methodAttr) {
                        $methodRoute = $methodAttr->newInstance();

                        $fullPath = rtrim($classRoute->path, '/') . '/' . ltrim($methodRoute->path, '/');

                        $this->addRoute(
                            $fullPath,
                            $methodRoute->method,
                            $controllerClass,
                            $method->getName()
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Route registration error: " . $e->getMessage());
        }
    }

    public function registerControllers(): void
    {
        if (!$this->debug && $cachedRoutes = $this->cache->get()) {
            $this->routes = $cachedRoutes;
            return;
        }

        $finder = new ControllerFinder();
        foreach ($this->controllerDirectories as $directory) {
            foreach ($finder->find($directory) as $controller) {
                $this->registerController($controller);
            }
        }


        if (!$this->debug) {
            $this->cache->store($this->routes);
        }
    }
    private function registerController(string $className): void
    {
        try {
            $reflection = new \ReflectionClass($className);

            $controllerAttr = $reflection->getAttributes(\Sonata\Framework\Attributes\Controller::class);
            if (empty($controllerAttr)) return;

            $prefix = $controllerAttr[0]->newInstance()->prefix;

            foreach ($reflection->getMethods() as $method) {
                $routeAttrs = $method->getAttributes(\Sonata\Framework\Attributes\Route::class);
                if (empty($routeAttrs)) continue;

                $route = $routeAttrs[0]->newInstance();
                $fullPath = rtrim($prefix, '/') . '/' . ltrim($route->path, '/');

                $this->addRoute(
                    $fullPath,
                    $route->method,
                    $className,
                    $method->getName()
                );
            }
        } catch (\Exception $e) {
            error_log("Controller registration error: " . $e->getMessage());
        }
    }

    private function sendResponse(mixed $data): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');

        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                $data = $data->toArray();
            } else {
                $data = get_object_vars($data);
            }
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sendNotFound(string $uri, string $method): void
    {
        $this->sendError(404, 'Route not found');
    }

    private function sendError(int $code, string $message, ?\Throwable $exception = null): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => null
            ]
        ];

        // Добавляем трассировку в режиме отладки
        if ($this->debug && $exception) {
            $response['error']['details'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_map(function ($frame) {
                    return [
                        'file' => $frame['file'] ?? '[internal]',
                        'line' => $frame['line'] ?? 0,
                        'function' => $frame['function'] ?? '',
                        'class' => $frame['class'] ?? '',
                        'type' => $frame['type'] ?? ''
                    ];
                }, $exception->getTrace())
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }


    private function getRequestData(string $source): array
    {
        return match($source) {
            'query' => $_GET,
            'jsonBody' => $this->getJsonBody(),
            'formData' => $this->getFormData(),
            'request' => $_REQUEST,
            default => []
        };
    }
    private function getJsonBody(): array
    {
        $content = file_get_contents('php://input');
        if (empty($content)) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function getFormData(): array
    {
        // Объединяем POST данные и файлы
        return array_merge($_POST, $_FILES);
    }
    /**
     * @throws \ReflectionException
     */
    /**
     * @throws \ReflectionException
     */
    private function resolveParameters(object $controller, string $method, array $urlMatches = []): array
    {
        $reflection = new \ReflectionMethod($controller, $method);
        $parameters = [];
        $urlIndex = 0;

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();

            if ($type && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $fromAttr = null;
                foreach ($param->getAttributes(\Sonata\Framework\Attributes\From::class) as $attr) {
                    $fromAttr = $attr->newInstance();
                    break;
                }

                if (!$fromAttr) {
                    throw new \LogicException(
                        "Parameter \${$param->getName()} in {$reflection->getDeclaringClass()->getName()}::{$method}() " .
                        "must be annotated with #[From('source')]"
                    );
                }

                $data = match ($fromAttr->source) {
                    'query' => $_GET,
                    'json' => $this->getJsonBody(),
                    'formData' => $this->getFormData(),
                    default => throw new \InvalidArgumentException("Unsupported source: {$fromAttr->source}")
                };

                $dtoClass = $type->getName();
                $dto = new $dtoClass($data);

                if (!interface_exists(\Symfony\Component\Validator\Validator\ValidatorInterface::class)) {
                    $this->sendError(500, "ValidatorInterface class not found");
                    exit;
                }

                if (!$this->container->has(\Symfony\Component\Validator\Validator\ValidatorInterface::class)) {
                    $this->sendError(500, "Validator service not registered");
                    exit;
                }

                $validator = $this->container->get(\Symfony\Component\Validator\Validator\ValidatorInterface::class);
                $errors = $validator->validate($dto);

                if (count($errors) > 0) {
                    $messages = [];
                    foreach ($errors as $error) {
                        $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
                    }
                    $this->sendError(400, implode(' | ', $messages));
                    exit;
                }

                $parameters[] = $dto;
            }
            else {
                if (isset($urlMatches[$urlIndex])) {
                    $rawValue = $urlMatches[$urlIndex];

                    if ($param->getType()?->getName() === 'array') {
                        $values = array_filter(array_map('trim', explode(',', $rawValue)));
                        $values = array_map(function ($v) {
                            if (is_numeric($v)) {
                                return str_contains($v, '.') ? (float)$v : (int)$v;
                            }
                            return $v;
                        }, $values);
                        $parameters[] = array_values($values);
                    } else {
                        $value = $rawValue;
                        if (is_numeric($value)) {
                            $value = str_contains($value, '.') ? (float)$value : (int)$value;
                        }
                        $parameters[] = $value;
                    }
                    $urlIndex++;
                } elseif ($param->isDefaultValueAvailable()) {
                    $parameters[] = $param->getDefaultValue();
                } else {
                    throw new \LogicException("Missing URL parameter for \${$param->getName()}");
                }
            }
        }

        return $parameters;
    }

    private function matchRoute(array $route, string $uri, string $method): ?array
    {
        if (strtoupper($route['method']) !== strtoupper($method)) {
            return null;
        }

        $matches = [];
        if (preg_match($route['pattern'], $uri, $matches)) {
            array_shift($matches);
            return $matches;
        }

        return null;
    }

    public function loadCachedRoutes(): void
    {
        $cacheFile = rtrim($this->basePath, '/') . '/var/cache/routes.php';

        if (file_exists($cacheFile)) {
            $this->routes = require $cacheFile;
        }
    }

    private function resolveBasePath(?string $basePath): string
    {
        return $basePath
            ?? $_ENV['SONATA_BASE_PATH']
            ?? $_SERVER['DOCUMENT_ROOT']
            ?? getcwd();
    }
}
