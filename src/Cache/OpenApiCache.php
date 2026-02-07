<?php

namespace Sonata\Framework\Cache;

class OpenApiCache
{
    private string $cacheFile;

    public function __construct(?string $cacheDir = null)
    {
        if ($cacheDir === null) {
            $basePath = $_ENV['SONATA_BASE_PATH']
                ?? $_SERVER['DOCUMENT_ROOT']
                ?? getcwd();
            $cacheDir = rtrim($basePath, '/') . '/var/cache';
        }
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $this->cacheFile = $cacheDir . '/openapi.cache.php';
    }

    public function get(): ?array
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }
        return include $this->cacheFile;
    }

    public function store(array $spec): void
    {
        file_put_contents($this->cacheFile, '<?php return ' . var_export($spec, true) . ';');
    }

    public function clear(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }
}
