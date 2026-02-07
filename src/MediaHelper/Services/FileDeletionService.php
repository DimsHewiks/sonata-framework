<?php

namespace Sonata\Framework\MediaHelper\Services;

class FileDeletionService
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * Удаление одного файла по относительному пути
     *
     * @param string $relativePath Относительный путь к файлу
     * @return bool TRUE если файл успешно удален, FALSE если файл не найден или ошибка
     */
    public function deleteFile(string $relativePath, bool $absolute = false): bool
    {
        $fullPath = $this->getFullPath($relativePath);

        if ($absolute) {
            $fullPath = $relativePath;
        }

        if (!file_exists($fullPath)) {
            return false;
        }

        if (!is_writable($fullPath)) {
            throw new \RuntimeException("Файл недоступен для записи: $fullPath");
        }

        return unlink($fullPath);
    }

    /**
     * Удаление директории и всего её содержимого
     *
     * @param string $relativePath Относительный путь к директории
     * @param bool $recursive Удалять вложенные директории рекурсивно
     * @return bool TRUE если директория успешно удалена
     */
    public function deleteDirectory(string $relativePath, bool $recursive = true): bool
    {
        $fullPath = $this->getFullPath($relativePath);

        if (!file_exists($fullPath)) {
            return false;
        }

        if (!is_dir($fullPath)) {
            throw new \RuntimeException("Путь $relativePath не является директорией");
        }

        if (!is_writable($fullPath)) {
            throw new \RuntimeException("Директория недоступна для записи: $fullPath");
        }

        if ($recursive) {
            return $this->deleteDirectoryRecursive($fullPath);
        } else {
            // Удаление только пустой директории
            return rmdir($fullPath);
        }
    }

    /**
     * Рекурсивное удаление директории и всего её содержимого
     *
     * @param string $dirPath Полный путь к директории
     * @return bool
     */
    private function deleteDirectoryRecursive(string $dirPath): bool
    {
        $files = array_diff(scandir($dirPath), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $dirPath . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                // Рекурсивно удаляем поддиректорию
                if (!$this->deleteDirectoryRecursive($filePath)) {
                    return false;
                }
            } else {
                // Удаляем файл
                if (!unlink($filePath)) {
                    throw new \RuntimeException("Не удалось удалить файл: $filePath");
                }
            }
        }

        // Удаляем пустую директорию
        return rmdir($dirPath);
    }

    /**
     * Удаление нескольких файлов и/или директорий
     *
     * @param array $relativePaths Массив относительных путей к файлам/директориям
     * @param bool $recursive Для директорий - удалять рекурсивно
     * @return array Ассоциативный массив с результатами удаления для каждого элемента
     */
    public function deleteItems(array $relativePaths, bool $recursive = true): array
    {
        $results = [];

        foreach ($relativePaths as $path) {
            try {
                $fullPath = $this->getFullPath($path);

                if (is_dir($fullPath)) {
                    $results[$path] = $this->deleteDirectory($path, $recursive);
                } else {
                    $results[$path] = $this->deleteFile($path);
                }
            } catch (\Exception $e) {
                $results[$path] = false;
                error_log("Ошибка удаления $path: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Удаление файла по UUID (если файлы хранятся с UUID именами)
     *
     * @param string $uuid UUID файла
     * @param string $extension Расширение файла
     * @param string $directory Директория, где хранится файл
     * @return bool
     */
    public function deleteFileByUuid(string $uuid, string $extension, string $directory = ''): bool
    {
        $directory = trim($directory, '/');
        $filename = $uuid . '.' . ltrim($extension, '.');

        if (!empty($directory)) {
            $relativePath = $directory . '/' . $filename;
        } else {
            $relativePath = $filename;
        }

        return $this->deleteFile($relativePath);
    }

    /**
     * Безопасное удаление файла/директории с проверкой, что элемент находится в разрешенной директории
     *
     * @param string $relativePath Относительный путь к файлу/директории
     * @param array $allowedDirectories Разрешенные директории для удаления
     * @param bool $recursive Для директорий - удалять рекурсивно
     * @return bool
     */
    public function deleteSafely(string $relativePath, array $allowedDirectories = [], bool $recursive = true): bool
    {
        $fullPath = $this->getFullPath($relativePath);

        // Проверяем, находится ли элемент в разрешенной директории
        if (!empty($allowedDirectories)) {
            $isAllowed = false;
            foreach ($allowedDirectories as $allowedDir) {
                $allowedFullPath = $this->basePath . '/' . ltrim($allowedDir, '/');
                if (strpos($fullPath, $allowedFullPath) === 0) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                throw new \RuntimeException("Удаление запрещено: $relativePath не находится в разрешенных директориях");
            }
        }

        if (is_dir($fullPath)) {
            return $this->deleteDirectory($relativePath, $recursive);
        } else {
            return $this->deleteFile($relativePath);
        }
    }

    /**
     * Получение полного пути к файлу/директории
     *
     * @param string $relativePath Относительный путь
     * @return string Полный путь
     */
    private function getFullPath(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        return $this->basePath . '/' . $relativePath;
    }

    /**
     * Проверка существования файла/директории
     *
     * @param string $relativePath Относительный путь
     * @return bool
     */
    public function exists(string $relativePath): bool
    {
        $fullPath = $this->getFullPath($relativePath);
        return file_exists($fullPath);
    }

    /**
     * Проверка, является ли путь директорией
     *
     * @param string $relativePath Относительный путь
     * @return bool
     */
    public function isDirectory(string $relativePath): bool
    {
        $fullPath = $this->getFullPath($relativePath);
        return is_dir($fullPath);
    }

    /**
     * Получение информации о файле/директории
     *
     * @param string $relativePath Относительный путь
     * @return array|null
     */
    public function getInfo(string $relativePath): ?array
    {
        $fullPath = $this->getFullPath($relativePath);

        if (!file_exists($fullPath)) {
            return null;
        }

        $info = [
            'size' => is_file($fullPath) ? filesize($fullPath) : $this->getDirectorySize($fullPath),
            'modified' => filemtime($fullPath),
            'permissions' => fileperms($fullPath),
            'is_readable' => is_readable($fullPath),
            'is_writable' => is_writable($fullPath),
            'is_directory' => is_dir($fullPath),
        ];

        if (is_dir($fullPath)) {
            $info['item_count'] = count(array_diff(scandir($fullPath), ['.', '..']));
        }

        return $info;
    }

    /**
     * Получение размера директории
     *
     * @param string $dirPath Путь к директории
     * @return int Размер в байтах
     */
    private function getDirectorySize(string $dirPath): int
    {
        $size = 0;
        $files = array_diff(scandir($dirPath), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $dirPath . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                $size += $this->getDirectorySize($filePath);
            } else {
                $size += filesize($filePath);
            }
        }

        return $size;
    }


}