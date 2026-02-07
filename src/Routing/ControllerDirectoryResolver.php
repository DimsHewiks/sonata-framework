<?php

namespace Sonata\Framework\Routing;

class ControllerDirectoryResolver
{
    /**
     * @param string[] $appDirectories
     * @return string[]
     */
    public static function resolve(string $basePath, array $appDirectories = ['api', 'view', 'commands']): array
    {
        $basePath = rtrim($basePath, '/');
        $directories = [];

        foreach ($appDirectories as $dir) {
            $path = $basePath . '/' . $dir;
            if (is_dir($path)) {
                $directories[] = $path;
            }
        }

        $vendorSonata = $basePath . '/vendor/sonata';
        if (is_dir($vendorSonata)) {
            foreach (new \DirectoryIterator($vendorSonata) as $entry) {
                if ($entry->isDot() || !$entry->isDir()) {
                    continue;
                }

                $controllerDir = $entry->getPathname() . '/src/Controllers';
                if (is_dir($controllerDir)) {
                    $directories[] = $controllerDir;
                }
            }
        }

        return array_values(array_unique($directories));
    }
}
