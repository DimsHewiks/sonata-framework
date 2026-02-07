<?php

namespace Sonata\Framework\MediaHelper\Enums;

enum UploadErrorEnum: string
{
    case UPLOAD_ERR_INI_SIZE="Файл слишком большой";
    case UPLOAD_ERR_FORM_SIZE="Превышен размер формы";
    case UPLOAD_ERR_PARTIAL = "Файл загружен частично";
    case UPLOAD_ERR_NO_FILE = "Файл не был загружен";
    case UPLOAD_ERR_NO_TMP_DIR = "Отсутствует временная папка";
    case UPLOAD_ERR_CANT_WRITE = "Ошибка записи на диск";
    case UPLOAD_ERR_EXTENSION = "Расширение PHP остановило загрузку";

}
