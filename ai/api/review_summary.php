<?php
require_once __DIR__ . '/../includes/review_service.php';

if (!ai_rate_limit('review_summary', 30, 60)) {
    ai_json_response(429, [
        'success' => false,
        'message' => 'Too many analysis requests. Please refresh in a moment.',
    ]);
}

$productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;

if ($productId <= 0) {
    ai_json_response(422, [
        'success' => false,
        'message' => 'A valid product ID is required.',
    ]);
}

$service = new AI_Review_Service();
$result = $service->getProductAnalysis($pdo, $productId, !empty($_GET['refresh']));

ai_json_response($result['success'] ? 200 : 404, $result);
