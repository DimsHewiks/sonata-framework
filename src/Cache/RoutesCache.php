<?php
namespace Sonata\Framework\Cache;

class RoutesCache
{
    private string $cachePath;

    public function __construct(?string $basePath = null)
    {
        $basePath = $basePath
            ?? $_ENV['SONATA_BASE_PATH']
            ?? $_SERVER['DOCUMENT_ROOT']
            ?? getcwd();
        $this->cachePath = rtrim($basePath, '/') . '/var/cache/routes.cache';
        $this->ensureCacheDirExists();
    }

    public function get(): ?array
    {
        if (!file_exists($this->cachePath)) {
            return null;
        }

        $data = unserialize(file_get_contents($this->cachePath));
        return $data['expires'] > time() ? $data['routes'] : null;
    }

    public function store(array $routes, int $ttl = 3600): void
    {
        $data = [
            'routes' => $routes,
            'expires' => time() + $ttl,
            'created' => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->cachePath, serialize($data), LOCK_EX);
    }

    public function clear(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    private function ensureCacheDirExists(): void
    {
        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
