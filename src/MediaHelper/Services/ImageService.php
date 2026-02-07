<?php

namespace Sonata\Framework\MediaHelper\Services;

class ImageService extends BaseMediaService
{
    protected const DEFAULT_MAX_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct(
        string $basePath,
        string $uploadDir,
        array $allowedExtensions,
        int $maxFileSize= self::DEFAULT_MAX_SIZE
    ) {
        parent::__construct($basePath, $uploadDir, $allowedExtensions, $maxFileSize);
    }

    protected function  saveUploadedFile(array $file, array &$result): void
    {
        // Генерируем имя файла с таймстампомвместо UUID
        $result['saved_name'] = $this->generateTimestampFilename($result['original_name']);
        $result['full_path'] = $this->uploadDir . '/' . $result['saved_name'];

        // Сохраняем относительный путь (без basePath)
        $result['relative_path'] = str_replace($this->basePath, '', $result['full_path']);
        $result['relative_path'] = ltrim($result['relative_path'], '/');

        if (move_uploaded_file($file['tmp_name'], $result['full_path'])) {
            $result['uploaded'] = true;
} else {
            $result['errors'][] = "Ошибка при перемещении файла на сервер.";
        }
    }
}