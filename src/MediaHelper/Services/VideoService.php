<?php

namespace Sonata\Framework\MediaHelper\Services;

class VideoService extends BaseMediaService
{
    protected const DEFAULT_MAX_SIZE = 50 * 1024 * 1024; // 50MB

    public function __construct(
        string $basePath,
        string $uploadDir,
        array $allowedExtensions,
        int $maxFileSize= self::DEFAULT_MAX_SIZE
    ) {
        parent::__construct($basePath, $uploadDir, $allowedExtensions, $maxFileSize);
    }

    protected function saveUploadedFile(array $file, array &$result): void
    {
        // Генерируем UUID имя файла
        $result['saved_name'] = $this->generateTimestampFilename($result['original_name']);
        $result['full_path'] = $this->uploadDir . '/' . $result['saved_name'];

        // Сохраняем относительный путь (без basePath)
        $result['relative_path'] = str_replace($this->basePath, '', $result['full_path']);
        $result['relative_path'] = ltrim($result['relative_path'], '/');

        if (move_uploaded_file($file['tmp_name'], $result['full_path'])) {
            $result['uploaded'] = true;
        } else {
            $result['errors'][]= "Ошибка при перемещении файла на сервер.";
        }
    }
private function getRelativePath(array $fileResult)
    {
        if (!empty($fileResult['errors'])) {
            return $fileResult['errors'];
        }

        return $fileResult['relative_path'] ?? null;
    }
}