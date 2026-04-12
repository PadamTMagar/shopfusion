<?php
// ai/includes/bootstrap.php
require_once __DIR__ . '/helpers.php';

function ai_render_head_assets($basePath = '..') {
    $cssUrl = ai_relative_url($basePath, 'ai/css/ai.css');
    return '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
}

function ai_render_chat_widget($basePath = '..', array $context = []) {
    $config = [
        'apiUrl' => ai_relative_url($basePath, 'ai/api/chat.php'),
        'csrfToken' => ai_get_csrf_token(),
        'pageType' => ai_array_get($context, 'page_type', 'storefront'),
        'pageTitle' => ai_array_get($context, 'page_title', 'ShopFusion'),
        'productId' => (int) ai_array_get($context, 'product_id', 0),
        'productName' => ai_array_get($context, 'product_name', ''),
    ];

    $scriptUrl = ai_relative_url($basePath, 'ai/js/chat.js');

    return
        '<div class="ai-chat-shell" data-ai-chat-root data-config="' . ai_escape_attr_json($config) . '"></div>' . PHP_EOL .
        '<script src="' . htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8') . '" defer></script>' . PHP_EOL;
}

function ai_render_review_panel($basePath = '..', array $context = []) {
    $config = [
        'apiUrl' => ai_relative_url($basePath, 'ai/api/review_summary.php'),
        'productId' => (int) ai_array_get($context, 'product_id', 0),
        'reviewCount' => (int) ai_array_get($context, 'review_count', 0),
    ];

    return '
        <section class="ai-review-analysis-card" data-ai-review-analysis data-config="' . ai_escape_attr_json($config) . '">
            <div class="ai-review-analysis-header">
                <div>
                    <span class="ai-kicker">AI Review Analysis</span>
                    <h3>Customer feedback snapshot</h3>
                </div>
                <span class="ai-review-analysis-status" data-ai-review-status>Analyzing</span>
            </div>
            <p class="ai-review-analysis-summary" data-ai-review-summary>Review signals are loading for this product.</p>
            <div class="ai-review-analysis-grid">
                <div class="ai-review-analysis-column">
                    <h4>Pros</h4>
                    <ul data-ai-review-pros>
                        <li>Waiting for review insights</li>
                    </ul>
                </div>
                <div class="ai-review-analysis-column">
                    <h4>Cons</h4>
                    <ul data-ai-review-cons>
                        <li>Waiting for review insights</li>
                    </ul>
                </div>
            </div>
            <div class="ai-review-analysis-footer">
                <span class="ai-sentiment-badge" data-ai-review-sentiment>Loading</span>
                <span class="ai-review-meta" data-ai-review-meta></span>
            </div>
        </section>
    ';
}

function ai_render_review_assets($basePath = '..') {
    $scriptUrl = ai_relative_url($basePath, 'ai/js/review.js');
    return '<script src="' . htmlspecialchars($scriptUrl, ENT_QUOTES, 'UTF-8') . '" defer></script>' . PHP_EOL;
}
