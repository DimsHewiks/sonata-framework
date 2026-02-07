<?php
namespace Sonata\Framework;

class ControllerFinder
{
    public function find(string $directory): array
    {


        $controllers = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getPathname();
            $className = $this->getFullyQualifiedClassName($filePath);

            if ($className && $this->isController($className)) {
                $controllers[] = $className;
                error_log("Found controller: " . $className);
            }
        }

        return $controllers;
    }

    private function isController(string $className): bool
    {
        if (!class_exists($className)) {
            error_log("Class does not exist: " . $className);
            return false;
        }

        try {
            $reflection = new \ReflectionClass($className);
            return !empty($reflection->getAttributes(\Sonata\Framework\Attributes\Controller::class));
        } catch (\Exception $e) {
            error_log("Reflection error: " . $e->getMessage());
            return false;
        }
    }

    private function getFullyQualifiedClassName(string $filePath): ?string
    {
        $fileContent = file_get_contents($filePath);
        if (!$fileContent) {
            error_log("Failed to read file: " . $filePath);
            return null;
        }

        // Быстрый поиск по регулярке (оптимизация)
        if (!preg_match('/\bnamespace\s+([^;]+);.*?class\s+(\w+)/s', $fileContent, $matches)) {
            error_log("No namespace/class found in: " . $filePath);
            return null;
        }

        $namespace = $matches[1];
        $className = $matches[2];

        return $namespace . '\\' . $className;
    }
    private function extractClassName(string $filePath): ?string
    {
        if (!is_readable($filePath)) {
            error_log("Cannot read file: {$filePath}");
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            error_log("Failed to read file: {$filePath}");
            return null;
        }

        $tokens = token_get_all($content);
        $namespace = '';
        $className = '';
        foreach ($tokens as $i => $token) {
            if (is_array($token)) {
                [$id, $text] = $token;
                echo $i.'-----';
                print_r($token);
                echo '</br>';
                if ($id === T_NAMESPACE) {
                    $namespace = $this->parseNamespace($tokens, $i);
                }

                if ($id === T_CLASS) {
                    $className = $this->parseClassName($tokens, $i);
                    break;
                }
            }
        }
        echo $namespace;
        return $namespace && $className ? $namespace . '\\' . $className : null;
    }

    private function parseNamespace(array $tokens, int $startIndex): string
    {
        $namespace = '';
        for ($i = $startIndex + 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($token === ';') break;

            if (is_array($token) && ($token[0] === T_STRING || $token[0] === T_NS_SEPARATOR)) {
                $namespace .= $token[1];
            }
        }
        return $namespace;
    }

    private function parseClassName(array $tokens, int $startIndex): string
    {
        for ($i = $startIndex + 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }
        return '';
    }
}