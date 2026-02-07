<?php

namespace Sonata\Framework\MediaHelper\Services;

use Ramsey\Uuid\Uuid;

abstract class BaseMediaService
{
    protected const DEFAULT_MAX_SIZE = 10 * 1024 * 1024; // 10MB

    protected string $basePath;
    protected string $uploadDir;
    protected int $maxFileSize;
    protected array $errors = [];
    protected array $allowedExtensions;

    public function __construct(
        string $basePath,
        string $uploadDir,
        array $allowedExtensions,
        int $maxFileSize = self::DEFAULT_MAX_SIZE
    ) {
        $this->basePath = rtrim($basePath, '/');
        $this->uploadDir = $this->basePath . '/' . ltrim($uploadDir, '/');
        $this->maxFileSize = $maxFileSize;
        $this->allowedExtensions = $allowedExtensions;
        $this->ensureUploadDirectoryExists();
    }

    private function ensureUploadDirectoryExists(): void
    {
        if (!file_exists($this->uploadDir) && !mkdir($this->uploadDir, 0755, true)) {
            throw new \RuntimeException("Не удалось создать директорию для загрузки: {$this->uploadDir}");
        }
    }

    public function upload(array $fileFields): array
    {
        $result = [];

        foreach ($fileFields as $fieldName) {
            if (!isset($_FILES[$fieldName])) {
                $this->addError($fieldName, "Поле $fieldName не найдено в загруженных файлах");
                $result[$fieldName] = null;
                continue;
            }

            $result = $this->processFileField($_FILES[$fieldName]);
        }

        return $result;
    }

    private function processFileField(array $fileData): array
    {
        if (is_array($fileData['name'])) {
            return $this->processMultipleFiles($fileData);
        }

        return $this->processSingleFile($fileData);
    }

    private function processMultipleFiles(array $fileArray): array
    {
        $results = [];
        $files = $this->rearrangeFilesArray($fileArray);

        foreach ($files as $file) {
            $fileResult = $this->processSingleFile($file);
            $results[] = $fileResult;
        }

        return $results;
    }

    protected function generateTimestampFilename(string $originalName): string
    {
        $pathInfo = pathinfo($originalName);
        $name = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';

        if ($extension !== '') {
            return $name . '_' . date('Y-m-d_h-i-s') . '.' . $extension;
        } else {
            return $name . '_' . date('Y-m-d_h-i-s');
        }
    }

    protected function generateUuidFilename(string $extension): string
    {
        return Uuid::uuid7()->toString() . '.' . $extension;
    }

    private function processSingleFile(array $file): array
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $result = [
            'original_name' => $file['name'],
            'saved_name' => '',
            'full_path' => '',
            'relative_path' => '',
            'size' => $file['size'],
            'extension' => $extension,
            'uploaded' => false,
            'errors' => []
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = $this->getUploadError($file['error']);
            return $result;
        }

        $this->validateFile($result);

        if (empty($result['errors'])) {
            $this->saveUploadedFile($file, $result);
        }

        return $result;
    }

    private function validateFile(array &$result): void
    {
        if (!in_array($result['extension'], $this->allowedExtensions)) {
            $result['errors'][] = "Тип файла {$result['extension']} не разрешен. Разрешенные форматы: " .
                implode(', ', $this->allowedExtensions);
        }

        if ($result['size'] > $this->maxFileSize) {
            $result['errors'][] = "Файл слишком большой. Максимальный размер: " .
                $this->formatBytes($this->maxFileSize);
        }
    }

    abstract protected function saveUploadedFile(array $file, array &$result): void;

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function rearrangeFilesArray(array $fileArray): array
    {
        $rearranged = [];
        $fileCount = count($fileArray['name']);
        $fileKeys = array_keys($fileArray);

        for ($i = 0; $i < $fileCount; $i++) {
            foreach ($fileKeys as $key) {
                $rearranged[$i][$key] = $fileArray[$key][$i];
            }
        }

        return $rearranged;
    }

    private function getUploadError(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'Размер файла превышает значение upload_max_filesize в php.ini',
            UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает значение MAX_FILE_SIZE в HTML-форме',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'Загрузка файла остановлена расширением PHP',
            default => 'Неизвестная ошибка загрузки',
        };
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}