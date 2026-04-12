<?php
// ai/includes/demo_reviews.php

function ai_demo_reviews_dataset() {
    static $dataset = null;

    if ($dataset === null) {
        $dataset = require __DIR__ . '/../demo/dummy_reviews.php';
    }

    return $dataset;
}

function ai_normalize_review_key($value) {
    $value = strtolower((string) $value);
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return trim((string) $value);
}

function ai_get_dummy_reviews_for_product(array $product) {
    $dataset = ai_demo_reviews_dataset();
    $nameKey = ai_normalize_review_key($product['product_name'] ?? '');

    if ($nameKey !== '') {
        foreach ($dataset as $key => $reviews) {
            if ($key === 'default') {
                continue;
            }

            if (strpos($nameKey, $key) !== false || strpos($key, $nameKey) !== false) {
                return ai_prepare_dummy_reviews($reviews);
            }
        }
    }

    return ai_prepare_dummy_reviews($dataset['default']);
}

function ai_prepare_dummy_reviews(array $reviews) {
    $prepared = [];

    foreach ($reviews as $index => $review) {
        $prepared[] = [
            'review_id' => 'demo-' . ($index + 1),
            'customer_name' => $review['customer_name'],
            'username' => 'demo_customer',
            'rating' => (int) $review['rating'],
            'comment' => $review['comment'],
            'created_at' => $review['created_at'],
            'helpful_count' => $review['helpful_count'] ?? 0,
            'title' => $review['title'] ?? '',
            'is_demo' => true,
        ];
    }

    return $prepared;
}

function ai_build_review_snapshot(array $reviews, $fallbackRating = 0) {
    $snapshot = [
        'count' => count($reviews),
        'average_rating' => 0,
        'breakdown' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
        'best_review' => null,
        'most_helpful_review' => null,
    ];

    if (empty($reviews)) {
        $snapshot['average_rating'] = (float) $fallbackRating;
        return $snapshot;
    }

    $total = 0;
    $bestScore = -1;
    $helpfulScore = -1;

    foreach ($reviews as $review) {
        $rating = (int) ($review['rating'] ?? 0);
        $total += $rating;

        if (isset($snapshot['breakdown'][$rating])) {
            $snapshot['breakdown'][$rating]++;
        }

        $bestCandidate = $rating * 10 + (int) ($review['helpful_count'] ?? 0);
        if ($bestCandidate > $bestScore) {
            $bestScore = $bestCandidate;
            $snapshot['best_review'] = $review;
        }

        $candidateHelpful = (int) ($review['helpful_count'] ?? 0) + $rating;
        if ($candidateHelpful > $helpfulScore) {
            $helpfulScore = $candidateHelpful;
            $snapshot['most_helpful_review'] = $review;
        }
    }

    $snapshot['average_rating'] = round($total / max(1, count($reviews)), 1);
    return $snapshot;
}
