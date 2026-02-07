<?php

namespace Sonata\Framework\MediaHelper;

use Imagick;

class ImageCompressor
{
    /**
     * @param string $srcPath путь к исходному файлу
     * @param string $dstPath путь для сохранения webp
     * @param int $maxBytes максимальный размер файла (в байтах)
     * @throws \ImagickException
     */
    public function compressToWebp(
        string $srcPath,
        string $dstPath,
        int $maxBytes
    ): void {
        $img = new Imagick($srcPath);

        // Убираем EXIF и метаданные
        $img->stripImage();

        if ($img->getImageAlphaChannel()) {
            $img->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
        }

        // Ограничиваем размер изображения
        $maxSide = 1280;
        if ($img->getImageWidth() > $maxSide || $img->getImageHeight() > $maxSide) {
            $img->resizeImage(
                $maxSide,
                $maxSide,
                Imagick::FILTER_LANCZOS,
                1,
                true
            );
        }

        $img->setImageFormat('webp');
        $img->setOption('webp:method', '6');


        // Подбираем качество под нужный размер
        for ($quality = 85; $quality >= 45; $quality -= 5) {
            $img->setImageCompressionQuality($quality);
            $img->writeImage($dstPath);

            if (filesize($dstPath) <= $maxBytes) {
                $img->clear();
                return;
            }
        }

        // fallback — минимальное качество
        $img->setImageCompressionQuality(45);
        $img->writeImage($dstPath);

        $img->clear();
    }
}
