<?php

namespace Sonata\Framework\Http;

class Response
{
    /**
     * Отправить JSON-ответ
     */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Отправить HTML-ответ
     */
    public static function html(string $html, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Отправить ошибку в формате API
     */
    public static function error(string $message, int $code = 400, ?string $details = null): never
    {
        self::json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ]
        ], $code);
    }

    /**
     * Перенаправление
     */
    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header("Location: $url");
        exit;
    }
}