<?php
// ai/config/config.php
// Central AI configuration. Keep secrets in environment variables.

return [
    'openai_api_key' => trim((string) getenv('OPENAI_API_KEY')),
    'openai_chat_model' => getenv('OPENAI_CHAT_MODEL') ?: 'gpt-4o-mini',
    'openai_review_model' => getenv('OPENAI_REVIEW_MODEL') ?: 'gpt-4o-mini',
    'openai_endpoint' => 'https://api.openai.com/v1/chat/completions',
    'openai_timeout' => 25,
    'max_chat_length' => 800,
    'max_review_length' => 1500,
    'chat_history_limit' => 8,
    'support' => [
        'shipping' => [
            'standard' => 'Standard shipping is free and usually takes 5-7 business days.',
            'express' => 'Express shipping usually takes 2-3 business days and may include extra charges.',
        ],
        'payments' => [
            'methods' => ['PayPal', 'Credit/Debit Card', 'Bank Transfer'],
            'status' => 'Payments are processed securely on the checkout flow.',
        ],
        'returns' => [
            'window' => 'Customers can return items within 30 days.',
            'condition' => 'Returned items should be in original condition.',
            'defective' => 'Defective items qualify for free returns.',
            'non_defective' => 'For non-defective returns, the customer may pay the return shipping cost.',
        ],
        'future_features' => [
            'order_tracking' => false,
            'stock_checking' => false,
            'product_search' => false,
            'faq_retrieval' => false,
        ],
    ],
];
