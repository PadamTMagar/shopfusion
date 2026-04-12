<?php
require_once __DIR__ . '/../includes/chat_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ai_json_response(405, [
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
}

if (!ai_validate_csrf_request()) {
    ai_json_response(403, [
        'success' => false,
        'message' => 'Security token validation failed.',
    ]);
}

if (!ai_rate_limit('chat', 12, 60)) {
    ai_json_response(429, [
        'success' => false,
        'message' => 'Too many chat requests. Please wait a moment and try again.',
    ]);
}

$input = ai_read_json_input();
$message = ai_clean_text((string) ai_array_get($input, 'message', ''), (int) ai_get_config()['max_chat_length']);

$service = new AI_Chat_Service();
$result = $service->respond($message, [
    'page_type' => ai_clean_text((string) ai_array_get($input, 'page_type', 'storefront'), 80),
    'page_title' => ai_clean_text((string) ai_array_get($input, 'page_title', 'ShopFusion'), 120),
    'product_id' => (int) ai_array_get($input, 'product_id', 0),
]);

ai_json_response($result['success'] ? 200 : 422, $result);
