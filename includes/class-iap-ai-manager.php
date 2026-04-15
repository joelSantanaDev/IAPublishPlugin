<?php

if (!defined('ABSPATH')) {
    exit;
}

class IAP_AI_Manager {
    
    private $providers = [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'google' => 'Google (Gemini)',
        'groq' => 'Groq'
    ];
    
    private function log_api_response($provider, $response) {
        error_log('=== IAP API Response - ' . $provider . ' ===');
        error_log(print_r($response, true));
        error_log('=== End API Response ===');
    }
    
    private function log_api_error($provider, $error) {
        error_log('!!! IAP API Error - ' . $provider . ' !!!');
        error_log($error);
        error_log('!!! End API Error !!!');
    }
    
    public function get_providers() {
        return $this->providers;
    }
    
    public function test_connection($provider, $config) {
        switch ($provider) {
            case 'openai':
                return $this->test_openai($config);
            case 'anthropic':
                return $this->test_anthropic($config);
            case 'google':
                return $this->test_google($config);
            case 'groq':
                return $this->test_groq($config);
            default:
                return ['success' => false, 'message' => 'Provedor não suportado'];
        }
    }
    
    public function generate_content($provider, $config, $prompt) {
        switch ($provider) {
            case 'openai':
                return $this->generate_openai($config, $prompt);
            case 'anthropic':
                return $this->generate_anthropic($config, $prompt);
            case 'google':
                return $this->generate_google($config, $prompt);
            case 'groq':
                return $this->generate_groq($config, $prompt);
            default:
                return ['success' => false, 'message' => 'Provedor não suportado'];
        }
    }
    
    private function test_openai($config) {
        $api_key = isset($config['api_key']) ? $config['api_key'] : '';
        
        if (empty($api_key)) {
            return ['success' => false, 'message' => 'API Key não fornecida'];
        }
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => isset($config['model']) ? $config['model'] : 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => 'Test']
                ],
                'max_tokens' => 10
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }
        
        return ['success' => true, 'message' => 'Conexão bem-sucedida com OpenAI'];
    }
    
    private function test_anthropic($config) {
        $api_key = isset($config['api_key']) ? $config['api_key'] : '';
        
        if (empty($api_key)) {
            return ['success' => false, 'message' => 'API Key não fornecida'];
        }
        
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => isset($config['model']) ? $config['model'] : 'claude-3-5-haiku-20241022',
                'messages' => [
                    ['role' => 'user', 'content' => 'Test']
                ],
                'max_tokens' => 10
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }
        
        return ['success' => true, 'message' => 'Conexão bem-sucedida com Anthropic'];
    }
    
    private function test_google($config) {
        $api_key = isset($config['api_key']) ? $config['api_key'] : '';
        
        if (empty($api_key)) {
            return ['success' => false, 'message' => 'API Key não fornecida'];
        }
        
        $model = isset($config['model']) ? $config['model'] : 'gemini-2.0-flash-exp';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'contents' => [
                    ['parts' => [['text' => 'Test']]]
                ]
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            $this->log_api_error('Google Test', $response->get_error_message());
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->log_api_response('Google Test', $body);
        
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }
        
        return ['success' => true, 'message' => 'Conexão bem-sucedida com Google Gemini'];
    }
    
    private function test_groq($config) {
        $api_key = isset($config['api_key']) ? $config['api_key'] : '';
        
        if (empty($api_key)) {
            return ['success' => false, 'message' => 'API Key não fornecida'];
        }
        
        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => isset($config['model']) ? $config['model'] : 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => 'Test']
                ],
                'max_tokens' => 10
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }
        
        return ['success' => true, 'message' => 'Conexão bem-sucedida com Groq'];
    }
    
    private function generate_openai($config, $prompt) {
        $api_key = isset($config['api_key']) ? $config['api_key'] : '';
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => isset($config['model']) ? $config['model'] : 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um jornalista especializado em criar notícias originais baseadas em múltiplas fontes.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => isset($config['temperature']) ? floatval($config['temperature']) : 0.7,
                'max_tokens' => isset($config['max_tokens']) ? intval($config['max_tokens']) : 2000
            ]),
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }
        
        if (isset($body['choices'][0]['message']['content'])) {
            return ['success' => true, 'content' => $body['choices'][0]['message']['content']];
        }
        
        return ['success' => false, 'message' => 'Resposta inválida da API'];
    }
    
    private function generate_anthropic($config, $prompt) {
        $api_key = isset($config['api_key']) ? $config['api_key'] : '';
        
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => isset($config['model']) ? $config['model'] : 'claude-3-5-haiku-20241022',
                'system' => 'Você é um jornalista especializado em criar notícias originais baseadas em múltiplas fontes.',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => isset($config['temperature']) ? floatval($config['temperature']) : 0.7,
                'max_tokens' => isset($config['max_tokens']) ? intval($config['max_tokens']) : 2000
            ]),
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }
        
        if (isset($body['content'][0]['text'])) {
            return ['success' => true, 'content' => $body['content'][0]['text']];
        }
        
        return ['success' => false, 'message' => 'Resposta inválida da API'];
    }
    
    private function generate_google($config, $prompt) {
        $api_key = isset($config['api_key']) ? $config['api_key'] : '';
        $model = isset($config['model']) ? $config['model'] : 'gemini-2.0-flash-exp';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
        
        $system_instruction = 'Você é um jornalista especializado em criar notícias originais baseadas em múltiplas fontes.';
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'system_instruction' => ['parts' => [['text' => $system_instruction]]],
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => [
                    'temperature' => isset($config['temperature']) ? floatval($config['temperature']) : 0.7,
                    'maxOutputTokens' => isset($config['max_tokens']) ? intval($config['max_tokens']) : 2000
                ]
            ]),
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            $this->log_api_error('Google Generate', $response->get_error_message());
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->log_api_response('Google Generate', $body);
        
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }
        
        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return ['success' => true, 'content' => $body['candidates'][0]['content']['parts'][0]['text']];
        }
        
        return ['success' => false, 'message' => 'Resposta inválida da API. Estrutura: ' . json_encode(array_keys($body))];
    }
    
    private function generate_groq($config, $prompt) {
        $api_key = isset($config['api_key']) ? $config['api_key'] : '';
        
        $response = wp_remote_post('https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => isset($config['model']) ? $config['model'] : 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um jornalista especializado em criar notícias originais baseadas em múltiplas fontes.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => isset($config['temperature']) ? floatval($config['temperature']) : 0.7,
                'max_tokens' => isset($config['max_tokens']) ? intval($config['max_tokens']) : 2000
            ]),
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return ['success' => false, 'message' => $body['error']['message']];
        }
        
        if (isset($body['choices'][0]['message']['content'])) {
            return ['success' => true, 'content' => $body['choices'][0]['message']['content']];
        }
        
        return ['success' => false, 'message' => 'Resposta inválida da API'];
    }
}
