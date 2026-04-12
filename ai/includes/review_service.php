<?php
// ai/includes/review_service.php
require_once __DIR__ . '/openai_client.php';
require_once __DIR__ . '/demo_reviews.php';
require_once __DIR__ . '/../../config/database.php';

class AI_Review_Service {
    private $client;
    private $config;

    public function __construct() {
        $this->client = new AI_OpenAI_Client();
        $this->config = ai_get_config();
    }

    public function ensureTables(PDO $pdo) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ai_review_moderation (
                    moderation_id INT PRIMARY KEY AUTO_INCREMENT,
                    product_id INT NOT NULL,
                    customer_id INT DEFAULT NULL,
                    rating INT DEFAULT NULL,
                    review_text TEXT,
                    decision VARCHAR(30) NOT NULL,
                    reason VARCHAR(255) DEFAULT NULL,
                    confidence DECIMAL(4, 2) DEFAULT NULL,
                    source VARCHAR(30) NOT NULL,
                    model_used VARCHAR(100) DEFAULT NULL,
                    raw_response LONGTEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_product_created (product_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS ai_review_analysis_cache (
                    product_id INT PRIMARY KEY,
                    review_count INT NOT NULL DEFAULT 0,
                    average_rating DECIMAL(4, 2) NOT NULL DEFAULT 0.00,
                    sentiment VARCHAR(30) NOT NULL DEFAULT 'mixed',
                    summary TEXT,
                    pros_json TEXT,
                    cons_json TEXT,
                    source VARCHAR(30) NOT NULL DEFAULT 'fallback',
                    model_used VARCHAR(100) DEFAULT NULL,
                    last_review_at DATETIME DEFAULT NULL,
                    raw_response LONGTEXT DEFAULT NULL,
                    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            return true;
        } catch (PDOException $exception) {
            ai_write_log('review_errors', [
                'error' => $exception->getMessage(),
                'context' => 'ensure_tables',
            ]);
            return false;
        }
    }

    public function moderateReview(PDO $pdo, array $payload) {
        $storageReady = $this->ensureTables($pdo);

        $comment = ai_clean_text(ai_array_get($payload, 'comment', ''), (int) $this->config['max_review_length']);
        $productId = (int) ai_array_get($payload, 'product_id', 0);
        $customerId = (int) ai_array_get($payload, 'customer_id', 0);
        $rating = (int) ai_array_get($payload, 'rating', 0);
        $productName = ai_clean_text((string) ai_array_get($payload, 'product_name', ''), 120);

        if ($comment === '') {
            $record = [
                'product_id' => $productId,
                'customer_id' => $customerId,
                'rating' => $rating,
                'review_text' => $comment,
                'decision' => 'rejected',
                'reason' => 'Please write a review before submitting.',
                'confidence' => 1,
                'source' => 'fallback_rules',
                'model_used' => null,
                'raw_response' => null,
            ];

            return $storageReady ? $this->storeModeration($pdo, $record) : $record;
        }

        $fallbackDecision = $this->fallbackModeration($comment);
        $finalDecision = $fallbackDecision;

        if ($fallbackDecision['decision'] === 'approved' && $this->client->isConfigured()) {
            $response = $this->client->createChatCompletion([
                [
                    'role' => 'system',
                    'content' => 'You moderate e-commerce product reviews. Reject only spam, harassment, hate, threats, unsafe content, personal data, or content unrelated to the product experience. Do not reject honest negative feedback.',
                ],
                [
                    'role' => 'user',
                    'content' => sprintf(
                        "Product: %s\nRating: %d/5\nReview: %s\nReturn JSON only.",
                        $productName ?: ('Product #' . $productId),
                        $rating,
                        $comment
                    ),
                ],
            ], [
                'model' => $this->config['openai_review_model'],
                'temperature' => 0,
                'max_tokens' => 220,
                'json_schema' => [
                    'name' => 'review_moderation',
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'decision' => [
                                'type' => 'string',
                                'enum' => ['approved', 'rejected', 'review'],
                            ],
                            'reason' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                        ],
                        'required' => ['decision', 'reason', 'confidence'],
                    ],
                ],
            ]);

            if ($response['success'] && !empty($response['json'])) {
                $aiDecision = [
                    'decision' => $response['json']['decision'],
                    'reason' => ai_clean_text($response['json']['reason'], 220),
                    'confidence' => max(0, min(1, (float) $response['json']['confidence'])),
                    'source' => 'openai',
                    'model_used' => $response['model'],
                    'raw_response' => json_encode($response['json'], JSON_UNESCAPED_SLASHES),
                ];

                if ($aiDecision['decision'] === 'review') {
                    $aiDecision['decision'] = 'rejected';
                    $aiDecision['reason'] = $aiDecision['reason'] ?: 'This review needs manual review before publishing.';
                }

                $finalDecision = array_merge($fallbackDecision, $aiDecision);
            } elseif (!empty($response['error'])) {
                ai_write_log('review_errors', [
                    'error' => $response['error'],
                    'product_id' => $productId,
                ]);
            }
        }

        $record = [
            'product_id' => $productId,
            'customer_id' => $customerId,
            'rating' => $rating,
            'review_text' => $comment,
            'decision' => $finalDecision['decision'],
            'reason' => $finalDecision['reason'],
            'confidence' => $finalDecision['confidence'],
            'source' => $finalDecision['source'],
            'model_used' => ai_array_get($finalDecision, 'model_used'),
            'raw_response' => ai_array_get($finalDecision, 'raw_response'),
        ];

        return $storageReady ? $this->storeModeration($pdo, $record) : $record;
    }

    public function getProductAnalysis(PDO $pdo, $productId, $forceRefresh = false) {
        $productId = (int) $productId;
        $storageReady = $this->ensureTables($pdo);

        $stmt = $pdo->prepare("
            SELECT p.product_id, p.product_name, p.rating, p.total_reviews
            FROM products p
            WHERE p.product_id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found.',
            ];
        }

        $stmt = $pdo->prepare("
            SELECT rating, comment, created_at
            FROM reviews
            WHERE product_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$productId]);
        $reviews = $stmt->fetchAll();

        if (empty($reviews)) {
            $reviews = ai_get_dummy_reviews_for_product($product);
        }

        $reviewCount = count($reviews);
        $lastReviewAt = $reviewCount ? $reviews[0]['created_at'] : null;

        if (!$forceRefresh && $storageReady) {
            $cache = $this->getCachedAnalysis($pdo, $productId, $reviewCount, $lastReviewAt);
            if ($cache) {
                return [
                    'success' => true,
                    'analysis' => $cache,
                ];
            }
        }

        $analysis = $reviewCount > 0
            ? $this->generateAnalysis($product, $reviews)
            : $this->emptyAnalysis($product);

        if ($storageReady) {
            $this->saveAnalysis($pdo, $productId, $reviewCount, $product['rating'], $lastReviewAt, $analysis);
        }

        return [
            'success' => true,
            'analysis' => $analysis,
        ];
    }

    private function getCachedAnalysis(PDO $pdo, $productId, $reviewCount, $lastReviewAt) {
        try {
            $stmt = $pdo->prepare("
                SELECT *
                FROM ai_review_analysis_cache
                WHERE product_id = ? AND review_count = ? AND (
                    (last_review_at IS NULL AND ? IS NULL) OR last_review_at = ?
                )
            ");
            $stmt->execute([$productId, $reviewCount, $lastReviewAt, $lastReviewAt]);
            $row = $stmt->fetch();
        } catch (PDOException $exception) {
            ai_write_log('review_errors', [
                'error' => $exception->getMessage(),
                'context' => 'get_cached_analysis',
            ]);
            return null;
        }

        if (!$row) {
            return null;
        }

        return [
            'product_id' => (int) $row['product_id'],
            'review_count' => (int) $row['review_count'],
            'average_rating' => (float) $row['average_rating'],
            'sentiment' => $row['sentiment'],
            'summary' => $row['summary'],
            'pros' => json_decode($row['pros_json'], true) ?: [],
            'cons' => json_decode($row['cons_json'], true) ?: [],
            'source' => $row['source'],
            'generated_at' => $row['generated_at'],
        ];
    }

    private function generateAnalysis(array $product, array $reviews) {
        $fallback = $this->fallbackAnalysis($product, $reviews);

        if (!$this->client->isConfigured()) {
            return $fallback;
        }

        $excerptRows = [];
        foreach (array_slice($reviews, 0, 12) as $review) {
            $excerptRows[] = '- Rating ' . (int) $review['rating'] . '/5: ' . ai_clean_text($review['comment'], 220);
        }

        $response = $this->client->createChatCompletion([
            [
                'role' => 'system',
                'content' => 'You analyze e-commerce product reviews. Return compact factual JSON. Base every conclusion on the supplied reviews only.',
            ],
            [
                'role' => 'user',
                'content' => sprintf(
                    "Product: %s\nAverage rating: %.2f\nReview count: %d\nReview excerpts:\n%s",
                    $product['product_name'],
                    (float) $product['rating'],
                    count($reviews),
                    implode("\n", $excerptRows)
                ),
            ],
        ], [
            'model' => $this->config['openai_review_model'],
            'temperature' => 0.2,
            'max_tokens' => 350,
            'json_schema' => [
                'name' => 'review_analysis',
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'summary' => ['type' => 'string'],
                        'pros' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'cons' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'sentiment' => [
                            'type' => 'string',
                            'enum' => ['positive', 'mixed', 'negative'],
                        ],
                    ],
                    'required' => ['summary', 'pros', 'cons', 'sentiment'],
                ],
            ],
        ]);

        if ($response['success'] && !empty($response['json'])) {
            return [
                'product_id' => (int) $product['product_id'],
                'review_count' => count($reviews),
                'average_rating' => (float) $product['rating'],
                'sentiment' => $response['json']['sentiment'],
                'summary' => ai_clean_text($response['json']['summary'], 240),
                'pros' => array_slice(array_values(array_filter(array_map('ai_clean_text', $response['json']['pros']))), 0, 4),
                'cons' => array_slice(array_values(array_filter(array_map('ai_clean_text', $response['json']['cons']))), 0, 4),
                'source' => 'openai',
                'model_used' => $response['model'],
                'raw_response' => json_encode($response['json'], JSON_UNESCAPED_SLASHES),
                'generated_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($response['error'])) {
            ai_write_log('review_errors', [
                'error' => $response['error'],
                'product_id' => $product['product_id'],
            ]);
        }

        return $fallback;
    }

    private function fallbackModeration($comment) {
        $lower = strtolower($comment);
        $blockedTerms = ['hate', 'kill', 'stupid', 'idiot', 'damn', 'hell', 'xxx'];
        $looksLikeSpam = preg_match('/https?:\/\/|www\.|free money|click here/i', $comment);

        foreach ($blockedTerms as $term) {
            if (strpos($lower, $term) !== false) {
                return [
                    'decision' => 'rejected',
                    'reason' => 'Please remove abusive or inappropriate language from your review.',
                    'confidence' => 0.92,
                    'source' => 'fallback_rules',
                ];
            }
        }

        if ($looksLikeSpam) {
            return [
                'decision' => 'rejected',
                'reason' => 'Links and spam-like content are not allowed in reviews.',
                'confidence' => 0.95,
                'source' => 'fallback_rules',
            ];
        }

        return [
            'decision' => 'approved',
            'reason' => 'Approved.',
            'confidence' => 0.7,
            'source' => 'fallback_rules',
        ];
    }

    private function fallbackAnalysis(array $product, array $reviews) {
        $average = (float) $product['rating'];
        $sentiment = 'mixed';

        if ($average >= 4) {
            $sentiment = 'positive';
        } elseif ($average <= 2.5) {
            $sentiment = 'negative';
        }

        $pros = $this->extractThemes($reviews, function ($review) {
            return (int) $review['rating'] >= 4;
        }, ['quality', 'value', 'delivery', 'design', 'easy to use']);

        $cons = $this->extractThemes($reviews, function ($review) {
            return (int) $review['rating'] <= 2;
        }, ['packaging', 'durability', 'size', 'performance', 'expectation mismatch']);

        $summary = sprintf(
            '%s has an average rating of %.1f from %d review%s. Customers are mostly %s about the product overall.',
            $product['product_name'],
            $average,
            count($reviews),
            count($reviews) === 1 ? '' : 's',
            $sentiment === 'positive' ? 'positive' : ($sentiment === 'negative' ? 'critical' : 'mixed')
        );

        return [
            'product_id' => (int) $product['product_id'],
            'review_count' => count($reviews),
            'average_rating' => $average,
            'sentiment' => $sentiment,
            'summary' => $summary,
            'pros' => $pros,
            'cons' => $cons,
            'source' => 'fallback',
            'model_used' => null,
            'raw_response' => null,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function emptyAnalysis(array $product) {
        return [
            'product_id' => (int) $product['product_id'],
            'review_count' => 0,
            'average_rating' => (float) $product['rating'],
            'sentiment' => 'mixed',
            'summary' => 'This product does not have enough customer feedback yet for an AI review summary.',
            'pros' => [],
            'cons' => [],
            'source' => 'fallback',
            'model_used' => null,
            'raw_response' => null,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function extractThemes(array $reviews, callable $filter, array $defaults) {
        $dictionary = [
            'quality' => ['quality', 'build', 'material', 'premium', 'solid'],
            'value' => ['value', 'worth', 'price', 'affordable', 'budget'],
            'delivery' => ['delivery', 'shipping', 'arrived', 'fast'],
            'design' => ['design', 'look', 'style', 'beautiful'],
            'easy to use' => ['easy', 'simple', 'user-friendly', 'convenient'],
            'packaging' => ['package', 'packaging', 'box'],
            'durability' => ['durable', 'broke', 'durability', 'lasting'],
            'size' => ['size', 'fit', 'small', 'large'],
            'performance' => ['performance', 'speed', 'battery', 'sound'],
            'expectation mismatch' => ['expected', 'different', 'description', 'pictured'],
        ];

        $scores = [];
        foreach ($dictionary as $label => $keywords) {
            $scores[$label] = 0;
        }

        foreach ($reviews as $review) {
            if (!$filter($review)) {
                continue;
            }

            $text = strtolower((string) $review['comment']);
            foreach ($dictionary as $label => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        $scores[$label]++;
                    }
                }
            }
        }

        arsort($scores);
        $items = array_keys(array_filter($scores));

        if (empty($items)) {
            return array_slice($defaults, 0, 3);
        }

        return array_slice($items, 0, 3);
    }

    private function saveAnalysis(PDO $pdo, $productId, $reviewCount, $averageRating, $lastReviewAt, array $analysis) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ai_review_analysis_cache
                    (product_id, review_count, average_rating, sentiment, summary, pros_json, cons_json, source, model_used, last_review_at, raw_response)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    review_count = VALUES(review_count),
                    average_rating = VALUES(average_rating),
                    sentiment = VALUES(sentiment),
                    summary = VALUES(summary),
                    pros_json = VALUES(pros_json),
                    cons_json = VALUES(cons_json),
                    source = VALUES(source),
                    model_used = VALUES(model_used),
                    last_review_at = VALUES(last_review_at),
                    raw_response = VALUES(raw_response)
            ");

            $stmt->execute([
                $productId,
                $reviewCount,
                $averageRating,
                $analysis['sentiment'],
                $analysis['summary'],
                json_encode($analysis['pros'], JSON_UNESCAPED_SLASHES),
                json_encode($analysis['cons'], JSON_UNESCAPED_SLASHES),
                $analysis['source'],
                ai_array_get($analysis, 'model_used'),
                $lastReviewAt,
                ai_array_get($analysis, 'raw_response'),
            ]);
        } catch (PDOException $exception) {
            ai_write_log('review_errors', [
                'error' => $exception->getMessage(),
                'context' => 'save_analysis',
            ]);
        }
    }

    private function storeModeration(PDO $pdo, array $record) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ai_review_moderation
                    (product_id, customer_id, rating, review_text, decision, reason, confidence, source, model_used, raw_response)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $record['product_id'],
                $record['customer_id'] ?: null,
                $record['rating'] ?: null,
                $record['review_text'],
                $record['decision'],
                $record['reason'],
                $record['confidence'],
                $record['source'],
                $record['model_used'],
                $record['raw_response'],
            ]);
        } catch (PDOException $exception) {
            ai_write_log('review_errors', [
                'error' => $exception->getMessage(),
                'context' => 'store_moderation',
            ]);
        }

        return $record;
    }
}
