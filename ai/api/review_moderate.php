<?php
require_once __DIR__ . '/../includes/review_service.php';

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

if (!ai_rate_limit('review_moderate', 8, 60)) {
    ai_json_response(429, [
        'success' => false,
        'message' => 'Too many moderation requests. Please try again shortly.',
    ]);
}

$input = ai_read_json_input();

if (empty($input)) {
    $input = $_POST;
}

$service = new AI_Review_Service();
$result = $service->moderateReview($pdo, [
    'product_id' => (int) ai_array_get($input, 'product_id', 0),
    'customer_id' => getUserId(),
    'rating' => (int) ai_array_get($input, 'rating', 0),
    'comment' => ai_clean_text((string) ai_array_get($input, 'comment', ''), (int) ai_get_config()['max_review_length']),
    'product_name' => ai_clean_text((string) ai_array_get($input, 'product_name', ''), 120),
]);

ai_json_response(200, [
    'success' => true,
    'decision' => $result['decision'],
    'reason' => $result['reason'],
    'source' => $result['source'],
]);
