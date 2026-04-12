<?php
// ai/includes/helpers.php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

function ai_get_config() {
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }

    return $config;
}

function ai_get_header($name) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$key]) ? trim((string) $_SERVER[$key]) : '';
}

function ai_get_csrf_token() {
    return generateCSRFToken();
}

function ai_validate_csrf_request($token = null) {
    $requestToken = $token;

    if ($requestToken === null || $requestToken === '') {
        $requestToken = ai_get_header('X-CSRF-Token');
    }

    if (($requestToken === null || $requestToken === '') && isset($_POST['csrf_token'])) {
        $requestToken = $_POST['csrf_token'];
    }

    return $requestToken && validateCSRFToken($requestToken);
}

function ai_json_response($statusCode, array $payload) {
    if (!headers_sent()) {
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit();
}

function ai_read_json_input() {
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function ai_clean_text($value, $maxLength = 1000) {
    $value = is_string($value) ? $value : '';
    $value = strip_tags($value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim($value);

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function ai_array_get(array $data, $key, $default = null) {
    return array_key_exists($key, $data) ? $data[$key] : $default;
}

function ai_rate_limit($bucket, $maxRequests, $windowSeconds) {
    if (!isset($_SESSION['ai_rate_limits'])) {
        $_SESSION['ai_rate_limits'] = [];
    }

    if (!isset($_SESSION['ai_rate_limits'][$bucket])) {
        $_SESSION['ai_rate_limits'][$bucket] = [];
    }

    $now = time();
    $cutoff = $now - (int) $windowSeconds;

    $_SESSION['ai_rate_limits'][$bucket] = array_values(array_filter(
        $_SESSION['ai_rate_limits'][$bucket],
        function ($timestamp) use ($cutoff) {
            return $timestamp >= $cutoff;
        }
    ));

    if (count($_SESSION['ai_rate_limits'][$bucket]) >= $maxRequests) {
        return false;
    }

    $_SESSION['ai_rate_limits'][$bucket][] = $now;
    return true;
}

function ai_write_log($channel, array $payload) {
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $file = $logDir . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $channel) . '.log';
    $entry = [
        'timestamp' => date('c'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => function_exists('getUserId') ? getUserId() : null,
        'payload' => $payload,
    ];

    @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function ai_escape_attr_json(array $payload) {
    return htmlspecialchars(
        json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
        ENT_QUOTES,
        'UTF-8'
    );
}

function ai_relative_url($basePath, $path) {
    return rtrim($basePath, '/') . '/' . ltrim($path, '/');
}

function ai_is_openai_configured() {
    $config = ai_get_config();
    return !empty($config['openai_api_key']);
}
