<?php
// ai/includes/chat_service.php
require_once __DIR__ . '/openai_client.php';
require_once __DIR__ . '/../../config/database.php';

class AI_Chat_Service {
    private $client;
    private $config;

    public function __construct() {
        $this->client = new AI_OpenAI_Client();
        $this->config = ai_get_config();
    }

    public function respond($message, array $context = []) {
        $message = ai_clean_text($message, (int) $this->config['max_chat_length']);

        if ($message === '') {
            return [
                'success' => false,
                'message' => 'Please enter a question before sending.',
            ];
        }

        $productContext = $this->getProductContext(ai_array_get($context, 'product_id'));
        $history = $this->getHistory();

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($context, $productContext),
            ],
        ];

        foreach ($history as $item) {
            $messages[] = $item;
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        $response = $this->client->createChatCompletion($messages, [
            'model' => $this->config['openai_chat_model'],
            'temperature' => 0.4,
            'max_tokens' => 350,
        ]);

        if ($response['success'] && !empty($response['text'])) {
            $answer = trim($response['text']);
            $this->pushHistory($message, $answer);

            return [
                'success' => true,
                'message' => $answer,
                'source' => 'openai',
                'suggestions' => $this->buildSuggestions($productContext),
            ];
        }

        $fallback = $this->fallbackReply($message, $productContext);
        $this->pushHistory($message, $fallback);

        if (!empty($response['error'])) {
            ai_write_log('chat_errors', [
                'error' => $response['error'],
                'context' => $context,
            ]);
        }

        return [
            'success' => true,
            'message' => $fallback,
            'source' => 'fallback',
            'suggestions' => $this->buildSuggestions($productContext),
        ];
    }

    private function buildSystemPrompt(array $context, array $productContext = null) {
        $support = $this->config['support'];
        $productBlock = 'No specific product context is currently active.';

        if ($productContext) {
            $productBlock = sprintf(
                "Active product context:\n- Name: %s\n- Price: %s\n- Category: %s\n- Stock: %s\n- Shop: %s\n- Description: %s",
                $productContext['product_name'],
                formatPrice($productContext['price']),
                $productContext['category_name'],
                $productContext['stock_quantity'] > 0 ? $productContext['stock_quantity'] . ' available' : 'Out of stock',
                $productContext['shop_name'],
                ai_clean_text($productContext['description'], 300)
            );
        }

        return implode("\n\n", [
            'You are ShopFusion Support Assistant for an e-commerce website.',
            'Answer clearly, politely, and briefly. Stay within the supplied store information. If information is unavailable, say so instead of inventing details.',
            'You can help with product questions, shipping, payment methods, return/refund policy, and general shopping guidance.',
            'Do not claim order tracking, live stock lookup across the catalog, FAQ retrieval, or account actions are available unless the context explicitly says so.',
            'Store policy reference:' .
                "\n- " . $support['shipping']['standard'] .
                "\n- " . $support['shipping']['express'] .
                "\n- Accepted payment methods: " . implode(', ', $support['payments']['methods']) .
                "\n- " . $support['returns']['window'] .
                "\n- " . $support['returns']['condition'] .
                "\n- " . $support['returns']['defective'] .
                "\n- " . $support['returns']['non_defective'],
            'Page context:' .
                "\n- Page type: " . ai_clean_text((string) ai_array_get($context, 'page_type', 'storefront'), 80) .
                "\n- Page title: " . ai_clean_text((string) ai_array_get($context, 'page_title', 'ShopFusion'), 120),
            $productBlock,
        ]);
    }

    private function getProductContext($productId) {
        if (!$productId) {
            return null;
        }

        try {
            $product = getProductById((int) $productId);
            return $product ?: null;
        } catch (Exception $exception) {
            ai_write_log('chat_errors', [
                'error' => $exception->getMessage(),
                'product_id' => $productId,
            ]);
            return null;
        }
    }

    private function fallbackReply($message, array $productContext = null) {
        $lower = strtolower($message);
        $support = $this->config['support'];

        if ((strpos($lower, 'ship') !== false) || (strpos($lower, 'deliver') !== false)) {
            return $support['shipping']['standard'] . ' ' . $support['shipping']['express'];
        }

        if ((strpos($lower, 'refund') !== false) || (strpos($lower, 'return') !== false) || (strpos($lower, 'exchange') !== false)) {
            return $support['returns']['window'] . ' ' . $support['returns']['condition'] . ' ' . $support['returns']['defective'];
        }

        if ((strpos($lower, 'payment') !== false) || (strpos($lower, 'paypal') !== false) || (strpos($lower, 'card') !== false) || (strpos($lower, 'bank') !== false)) {
            return 'ShopFusion currently supports ' . implode(', ', $support['payments']['methods']) . '. ' . $support['payments']['status'];
        }

        if ((strpos($lower, 'track') !== false) || (strpos($lower, 'order') !== false)) {
            return 'For order-related help, start from My Orders after signing in. Live order tracking is planned for a future chatbot update, but I can still help explain shipping stages and delivery timing.';
        }

        if ($productContext && ((strpos($lower, 'product') !== false) || (strpos($lower, 'item') !== false) || (strpos($lower, 'this') !== false))) {
            return sprintf(
                '%s costs %s and is sold by %s. %s It is currently %s.',
                $productContext['product_name'],
                formatPrice($productContext['price']),
                $productContext['shop_name'],
                ai_clean_text($productContext['description'], 180),
                $productContext['stock_quantity'] > 0 ? 'in stock' : 'out of stock'
            );
        }

        if ((strpos($lower, 'best-selling') !== false) || (strpos($lower, 'best selling') !== false) || (strpos($lower, 'popular') !== false)) {
            return 'You can explore the homepage sections for featured picks, new arrivals, and best-selling products. They are the quickest way to spot popular items right now.';
        }

        if ((strpos($lower, 'stock') !== false) || (strpos($lower, 'search') !== false)) {
            return 'That advanced support feature is not wired into the chatbot yet, but I can still help with shipping, payments, returns, and product guidance on the current page.';
        }

        return 'I can help with product questions, shipping, payment methods, and return/refund policy. Ask me something like "How long does shipping take?" or "Can I return this item?"';
    }

    private function getHistory() {
        if (empty($_SESSION['ai_chat_history']) || !is_array($_SESSION['ai_chat_history'])) {
            return [];
        }

        return array_slice($_SESSION['ai_chat_history'], -1 * (int) $this->config['chat_history_limit']);
    }

    private function pushHistory($userMessage, $assistantMessage) {
        if (!isset($_SESSION['ai_chat_history']) || !is_array($_SESSION['ai_chat_history'])) {
            $_SESSION['ai_chat_history'] = [];
        }

        $_SESSION['ai_chat_history'][] = [
            'role' => 'user',
            'content' => $userMessage,
        ];
        $_SESSION['ai_chat_history'][] = [
            'role' => 'assistant',
            'content' => $assistantMessage,
        ];

        $_SESSION['ai_chat_history'] = array_slice(
            $_SESSION['ai_chat_history'],
            -1 * ((int) $this->config['chat_history_limit'] * 2)
        );
    }

    private function buildSuggestions(array $productContext = null) {
        $suggestions = [
            'Shipping time',
            'Return policy',
            'Payment methods',
        ];

        if ($productContext) {
            $suggestions[] = 'Tell me about this product';
        }

        return $suggestions;
    }
}
