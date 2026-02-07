<?php

namespace Sonata\Framework\MediaHelper;

use Sonata\Framework\MediaHelper\Services\FileDeletionService;
use Sonata\Framework\MediaHelper\Services\FileService;
use Sonata\Framework\MediaHelper\Services\ImageService;
use Sonata\Framework\MediaHelper\Services\VideoService;

class MediaHelper
{
    private string $basePath;
    private string $baseFolder;
    private string $uploadDir;
    private VideoService $videoService;
    private ImageService $imageService;
    private FileService $fileService;
    private FileDeletionService $fileDeletionService;
    private array $names = [];

    // Поддерживаемые расширения файлов
    private const array IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const array VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'hevc'];

    private const array DOCUMENT_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf'];

    public function __construct(
        string $uploadDir = '',
        ?VideoService $videoService = null,
        ?ImageService $imageService = null
    ) {
        $this->basePath = $_ENV['SONATA_BASE_PATH'] ?? getcwd();
        $this->baseFolder = $_ENV['MEDIA_UPLOAD_BASE'] ?? '/upload-next';
        $this->uploadDir = $uploadDir;
        $this->videoService = $videoService ?? new VideoService(
            $this->basePath,
            $this->baseFolder . $this->uploadDir,
            self::VIDEO_EXTENSIONS
        );
        $this->imageService = $imageService ?? new ImageService(
            $this->basePath,
            $this->baseFolder . $this->uploadDir,
            self::IMAGE_EXTENSIONS
        );
        $this->fileService = new FileService(
            $this->basePath,
            $this->baseFolder . $this->uploadDir,
            self::DOCUMENT_EXTENSIONS
        );
        $this->fileDeletionService = new FileDeletionService(
            $this->basePath . $this->baseFolder
        );
    }


    /**
     * Загрузка файлов на сервер
     * @throws \Exception
     */
    public function import(): array
    {
        if (empty($this->names)) {
            throw new \RuntimeException('не указаны имена полей файлов');
        }

        $results = [];

        foreach ($this->names as $name) {
            // Проверяем, существует ли поле в $_FILES
            if (!isset($_FILES[$name])) {
                $results[$name] = null;
                continue;
            }

            // Проверяем, является ли это множественной загрузкой
            if (is_array($_FILES[$name]['name'])) {
                // Для множественных файлов обрабатываем каждый файл отдельно
                $results[$name] = $this->processMultipleFilesOfDifferentTypes($name);
            } else {
                // Обработка одиночного файла
                $filename = $_FILES[$name]['name'] ?? '';

                if (empty($filename)) {
                    $results[$name] = null;
                    continue;
                }

                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (empty($extension)) {
                    throw new \RuntimeException("Не удалось определить тип файла: $filename");
                }

                $results[$name] = match (true) {
                    in_array($extension, self::IMAGE_EXTENSIONS) => $this->imageService->upload([$name]),
                    in_array($extension, self::VIDEO_EXTENSIONS) => $this->videoService->upload([$name]),
                    in_array($extension, self::DOCUMENT_EXTENSIONS) => $this->fileService->upload([$name]),
                    default => throw new \RuntimeException(sprintf("Неизвестный тип файла: %s", $extension)),
                };
            }
        }

        return $results;
    }

    /**
     * @throws \Exception
     */
    private function processMultipleFilesOfDifferentTypes(string $fieldName): array
    {
        $results = [];
        $files = $_FILES[$fieldName];
        $rearrangedFiles = $this->rearrangeFilesArray($files);
        $originalFilesData = $_FILES[$fieldName] ?? null;

        foreach ($rearrangedFiles as $index => $file) {
            $filename = $file['name'] ?? '';

            if (empty($filename)) {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (empty($extension)) {
                throw new \RuntimeException("Не удалось определить тип файла: $filename");
            }
            $_FILES[$fieldName] = $file;

            try {

                $serviceResult = null;

                if (in_array($extension, self::IMAGE_EXTENSIONS)) {
                    $serviceResult = $this->imageService->upload([$fieldName]);
                } elseif (in_array($extension, self::VIDEO_EXTENSIONS)) {
                    $serviceResult = $this->videoService->upload([$fieldName]);
                } elseif (in_array($extension, self::DOCUMENT_EXTENSIONS)) {
                    $serviceResult = $this->fileService->upload([$fieldName]);
                } else {
                    throw new \RuntimeException(sprintf("Неизвестный тип файла: %s", $extension));
                }
                if (isset($serviceResult[$fieldName])) {
                    $fileData = $serviceResult[$fieldName];
                    if (is_array($fileData)) {
                        $results[] = $fileData;
                    }
                } else {
                    if (is_array($serviceResult)) {
                        if (isset($serviceResult['original_name'])) {
                            $results[] = $serviceResult;
                        } else {
                            foreach ($serviceResult as $item) {
                                if (is_array($item) && isset($item['original_name'])) {
                                    $results[] = $item;
                                }
                            }
                        }
                    }
                }

            } catch (\Exception $e) {
            } finally {
                if ($originalFilesData !== null) {
                    $_FILES[$fieldName] = $originalFilesData;
                } else {
                    unset($_FILES[$fieldName]);
                }
            }
        }

        // Восстанавливаем оригинальные данные в конце
        if ($originalFilesData !== null) {
            $_FILES[$fieldName] = $originalFilesData;
        } else {
            unset($_FILES[$fieldName]);
        }
        return $results;
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

    /**
     * @throws \Exception
     */
    public function importVideo(array $names): array
    {
        $this->setNames($names);
        return $this->import();
    }

    /**
     * Удаление одного файла
     * @param string $filePath путь до файла
     * @param bool $absolute рекурсивное удаление
     * @return bool
     */
    public function deleteFile(string $filePath, bool $absolute = false): bool
    {
        return $this->fileDeletionService->deleteFile($filePath,$absolute);
    }

    /**
     * Удаление директории и всего её содержимого
     * @param $directory
     * @return bool
     */
    public function deleteDirectory(string $directory): bool
    {
        if(!$directory) {
            return false;
        }
        return $this->fileDeletionService->deleteDirectory(
            $directory, true
        );
    }

    /**
     * Удаление нескольких файлов
     * @param array $filePaths
     * @return array
     */
    public function deleteFiles(array $filePaths): array
    {
        //фикс рекурсии
        return $this->fileDeletionService->deleteItems($filePaths);
    }

    /**
     * Устанавливает директорию, куда сохранять файлы
     * @param string $uploadDir
     * @return void
     */
    public function setUploadDir(string $uploadDir): void
    {
        $this->uploadDir = $uploadDir;
    }

    /**
     * Устанавливает имена полей FormData, где хранятся файлы
     * @param array $names имена, отправляемые клиентом
     * @return $this
     */
    public function setNames(array $names): self
    {
        $this->names = $names;
        return $this;
    }
    
    public function existFile(string $param_name): bool
    {
        return isset($_FILES[$param_name]);
    }
}
