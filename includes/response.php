<?php
class Response
{
    public static function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($data = [], $message = 'ok')
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error($message, $code = 400)
    {
        self::json(['success' => false, 'message' => $message], $code);
    }

    public static function openaiError($message, $type = 'invalid_request_error', $code = null, $httpCode = 400)
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        $error = ['message' => $message, 'type' => $type];
        if ($code !== null) {
            $error['code'] = $code;
        }
        echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function notFound($message = 'Not Found')
    {
        self::error($message, 404);
    }

    public static function unauthorized($message = 'Unauthorized')
    {
        self::error($message, 401);
    }

    public static function forbidden($message = 'Forbidden')
    {
        self::error($message, 403);
    }

    public static function tooManyRequests($message = 'Too Many Requests', $retryAfter = 0)
    {
        if ($retryAfter > 0) {
            header('Retry-After: ' . $retryAfter);
        }
        self::error($message, 429);
    }

    public static function setHeader($name, $value)
    {
        header($name . ': ' . $value);
    }

    public static function setStatusCode($code)
    {
        http_response_code($code);
    }
}