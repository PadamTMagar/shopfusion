<?php
// ai/includes/openai_client.php
require_once __DIR__ . '/helpers.php';

class AI_OpenAI_Client {
    private $config;

    public function __construct() {
        $this->config = ai_get_config();
    }

    public function isConfigured() {
        return !empty($this->config['openai_api_key']);
    }

    public function createChatCompletion(array $messages, array $options = []) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'OpenAI API key is not configured.',
                'configured' => false,
            ];
        }

        $payload = [
            'model' => $options['model'] ?? $this->config['openai_chat_model'],
            'messages' => $messages,
            'temperature' => isset($options['temperature']) ? (float) $options['temperature'] : 0.2,
        ];

        if (!empty($options['max_tokens'])) {
            $payload['max_completion_tokens'] = (int) $options['max_tokens'];
        }

        if (!empty($options['json_schema']) && is_array($options['json_schema'])) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $options['json_schema']['name'],
                    'strict' => true,
                    'schema' => $options['json_schema']['schema'],
                ],
            ];
        }

        $response = $this->request($payload);

        if (!$response['success']) {
            return $response;
        }

        $message = $response['data']['choices'][0]['message'] ?? [];
        $content = $message['content'] ?? '';

        if (is_array($content)) {
            $textParts = [];
            foreach ($content as $part) {
                if (isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
            }
            $content = trim(implode("\n", $textParts));
        }

        $result = [
            'success' => true,
            'configured' => true,
            'text' => is_string($content) ? trim($content) : '',
            'model' => $response['data']['model'] ?? ($options['model'] ?? $this->config['openai_chat_model']),
            'usage' => $response['data']['usage'] ?? [],
            'raw' => $response['data'],
        ];

        if (!empty($options['json_schema'])) {
            $decoded = json_decode($result['text'], true);
            if (!is_array($decoded)) {
                return [
                    'success' => false,
                    'configured' => true,
                    'error' => 'Invalid structured response received from OpenAI.',
                    'raw' => $response['data'],
                ];
            }

            $result['json'] = $decoded;
        }

        return $result;
    }

    private function request(array $payload) {
        $curl = curl_init($this->config['openai_endpoint']);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config['openai_api_key'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => (int) $this->config['openai_timeout'],
        ]);

        $body = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($body === false || $curlError) {
            return [
                'success' => false,
                'configured' => true,
                'error' => 'OpenAI request failed: ' . ($curlError ?: 'Unknown cURL error.'),
            ];
        }

        $decoded = json_decode($body, true);

        if ($statusCode >= 400) {
            $errorPayload = is_array($decoded) ? ($decoded['error'] ?? []) : [];
            return [
                'success' => false,
                'configured' => true,
                'error' => ai_array_get($errorPayload, 'message', 'OpenAI request was rejected.'),
                'raw' => $decoded,
            ];
        }

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'configured' => true,
                'error' => 'OpenAI returned an unreadable response.',
            ];
        }

        return [
            'success' => true,
            'configured' => true,
            'data' => $decoded,
        ];
    }
}
