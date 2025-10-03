<?php

/**
 * Facebook Auto Poster
 * 
 * A comprehensive PHP library for automated Facebook page posting
 * with AI integration using Gemini API for content generation.
 * 
 * Features:
 * - Only Image post
 * - Only Caption post
 * - Image with Caption post
 * - AI Image/Caption generation (Gemini)
 * - Gemini Daily Free Limit 100
 * - Manual Content from JSON files
 * - Easy configuration and usage
 * 
 * @author BotolMehedi
 * @version 1.0.1
 */

class FacebookAutoPoster {
    
    private $page_id;
    private $page_access_token;
    private $image_folder;
    private $caption_file;
    private $ai_use;
    private $gemini_api_key;
    private $config;
    private $log_file;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct($config = []) {
        $this->config = array_merge([
            'page_id' => '',
            'page_access_token' => '',
            'image_folder' => './images/',
            'caption_file' => './captions.json',
            'ai_use' => 2, // 1 = use AI, 2 = manual
            'gemini_api_key' => '',
            'log_file' => './facebook_poster.log',
            'facebook_api_version' => 'v20.0',
            'max_retries' => 3,
            'retry_delay' => 2 // seconds
        ], $config);
        
        $this->page_id = $this->config['page_id'];
        $this->page_access_token = $this->config['page_access_token'];
        $this->image_folder = rtrim($this->config['image_folder'], '/') . '/';
        $this->caption_file = $this->config['caption_file'];
        $this->ai_use = $this->config['ai_use'];
        $this->gemini_api_key = $this->config['gemini_api_key'];
        $this->log_file = $this->config['log_file'];
        
        // Create directories if they don't exist
        if (!is_dir($this->image_folder)) {
            mkdir($this->image_folder, 0755, true);
        }
        
        if (!is_dir(dirname($this->caption_file))) {
            mkdir(dirname($this->caption_file), 0755, true);
        }
    }
    
    /**
     * Set configuration
     * 
     * @param string $key
     * @param mixed $value
     */
    public function setConfig($key, $value) {
        $this->config[$key] = $value;
        $this->$key = $value;
    }
    
    /**
     * Get configuration
     * 
     * @param string $key
     * @return mixed
     */
    public function getConfig($key = null) {
        return $key ? ($this->config[$key] ?? null) : $this->config;
    }
    
    /**
     * Create and post content
     * 
     * @param array $options Override options
     * @return array Response array
     */
    public function post($options = []) {
        try {
            $this->log('Starting Facebook auto post process');
            
            // Validate configuration
            $validation = $this->validateConfig();
            if (!$validation['valid']) {
                return $this->formatResponse('error', $validation['message']);
            }
            
            if ($this->ai_use == 1) {
                return $this->postWithAI($options);
            } else {
                return $this->postManually($options);
            }
            
        } catch (Exception $e) {
            $this->log('Error: ' . $e->getMessage());
            return $this->formatResponse('error', 'An error occurred: ' . $e->getMessage());
        }
    }
    
    /**
     * Post with AI generated content
     * 
     * @param array $options
     * @return array
     */
    private function postWithAI($options = []) {
        $this->log('Using AI mode for content generation');
        
        if (empty($this->gemini_api_key)) {
            return $this->formatResponse('error', 'Gemini API key is required for AI mode');
        }
        
        // Determine post type
        $post_type = $options['post_type'] ?? 'image_caption'; // image_only, caption_only, image_caption
        
        switch ($post_type) {
            case 'image_only':
                return $this->postAIImageOnly($options);
            case 'caption_only':
                return $this->postAICaptionOnly($options);
            case 'image_caption':
            default:
                return $this->postAIImageCaption($options);
        }
    }
    
    /**
     * Post manually using JSON file
     * 
     * @param array $options
     * @return array
     */
    private function postManually($options = []) {
        $this->log('Using manual mode for content');
        
        // Load captions from JSON file
        $captions_data = $this->loadCaptionsFile();
        if (!$captions_data) {
            return $this->formatResponse('error', 'Failed to load captions file');
        }
        
        if (empty($captions_data['captions'])) {
            return $this->formatResponse('error', 'No captions available in the file');
        }
        
        
        $caption_data = array_shift($captions_data['captions']);
        
        $caption = $caption_data['caption'] ?? '';
        $image_file = $caption_data['file'] ?? '';
        $image_path = !empty($image_file) ? $this->image_folder . $image_file : null;
        

        if (!empty($image_file) && !empty($caption)) {
            $post_type = 'image_caption';
        } elseif (!empty($image_file)) {
            $post_type = 'image_only';
            $caption = $caption_data['caption'] ?? '';
        } elseif (!empty($caption)) {
            $post_type = 'caption_only';
        } else {
            return $this->formatResponse('error', 'No valid content found');
        }
        
        // Publish to Facebook
        $result = $this->publishToFacebook($caption, $image_path, $post_type);
        
        // If post was successful, delete the image file and update JSON
        if ($result['status'] == 'success') {
            
            if (!empty($image_file) && file_exists($image_path)) {
                if (unlink($image_path)) {
                    $this->log('Image file deleted: ' . $image_file);
                } else {
                    $this->log('Warning: Failed to delete image file: ' . $image_file);
                }
            }
            
            // Update caption file
            if ($this->saveCaptionsFile(['captions' => $captions_data['captions']])) {
                $this->log('Caption removed from JSON file');
            } else {
                $this->log('Warning: Failed to update captions file');
            }
        }
        
        return $result;
    }
    
    /**
     * Post AI generated image only
     * 
     * @param array $options
     * @return array
     */
    private function postAIImageOnly($options = []) {
        $prompt = $options['image_prompt'] ?? 'Generate a creative and engaging image';
        
        $image_result = $this->generateImageWithGemini($prompt);
        if (!$image_result['success']) {
            return $this->formatResponse('error', 'Failed to generate image: ' . $image_result['message']);
        }
        
        return $this->publishToFacebook('', $image_result['image_path'], 'image_only');
    }
    
    /**
     * Post AI generated caption only
     * 
     * @param array $options
     * @return array
     */
    private function postAICaptionOnly($options = []) {
        $prompt = $options['caption_prompt'] ?? 'Generate an engaging social media caption';
        
        $caption_result = $this->generateCaptionWithGemini($prompt);
        if (!$caption_result['success']) {
            return $this->formatResponse('error', 'Failed to generate caption: ' . $caption_result['message']);
        }
        
        return $this->publishToFacebook($caption_result['caption'], null, 'caption_only');
    }
    
    /**
     * Post AI generated image with caption
     * 
     * @param array $options
     * @return array
     */
    private function postAIImageCaption($options = []) {
        $image_prompt = $options['image_prompt'] ?? 'Generate a creative and engaging image';
        
        // Generate image first
        $image_result = $this->generateImageWithGemini($image_prompt);
        if (!$image_result['success']) {
            return $this->formatResponse('error', 'Failed to generate image: ' . $image_result['message']);
        }
        
        // Generate caption based on the image
        $caption_prompt = $options['caption_prompt'] ?? 'Generate an engaging social media caption for this image';
        $caption_result = $this->generateCaptionForImage($image_result['image_path'], $caption_prompt);
        
        if (!$caption_result['success']) {
            return $this->formatResponse('error', 'Failed to generate caption: ' . $caption_result['message']);
        }
        
        return $this->publishToFacebook($caption_result['caption'], $image_result['image_path'], 'image_caption');
    }
    
    /**
     * Generate image using Gemini AI
     * 
     * @param string $prompt
     * @return array
     */
    private function generateImageWithGemini($prompt) {
        try {
            $this->log('Generating image with Imagen API');
            
            
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict?key=' . $this->gemini_api_key;
            
            // Payload
            $data = [
                'instances' => [
                    [
                        'prompt' => $prompt
                    ]
                ],
                'parameters' => [
                    'sampleCount' => 1,
                    'aspectRatio' => '1:1', 
                    'safetyFilterLevel' => 'block_some',
                    'personGeneration' => 'allow_adult'
                ]
            ];
            
            $response = $this->makeAPIRequest($url, $data, 'POST');
            
            if (!$response['success']) {
                $error_msg = $response['message'];
                // Log
                if (isset($response['data'])) {
                    $this->log('Imagen API Error Details: ' . json_encode($response['data']));
                }
                return ['success' => false, 'message' => $error_msg];
            }
            
            $result = $response['data'];
            
            
            if (isset($result['predictions'][0]['bytesBase64Encoded'])) {
                $image_data = base64_decode($result['predictions'][0]['bytesBase64Encoded']);
                $filename = 'ai_generated_' . time() . '_' . uniqid() . '.png';
                $image_path = $this->image_folder . $filename;
                
                if (file_put_contents($image_path, $image_data)) {
                    $this->log('Image generated successfully: ' . $filename);
                    return ['success' => true, 'image_path' => $image_path, 'filename' => $filename];
                } else {
                    return ['success' => false, 'message' => 'Failed to save generated image'];
                }
            } elseif (isset($result['predictions'][0]['mimeType']) && isset($result['predictions'][0]['image'])) {
                
                $image_data = base64_decode($result['predictions'][0]['image']);
                $filename = 'ai_generated_' . time() . '_' . uniqid() . '.png';
                $image_path = $this->image_folder . $filename;
                
                if (file_put_contents($image_path, $image_data)) {
                    $this->log('Image generated successfully: ' . $filename);
                    return ['success' => true, 'image_path' => $image_path, 'filename' => $filename];
                } else {
                    return ['success' => false, 'message' => 'Failed to save generated image'];
                }
            } else {
                $this->log('Unexpected API response: ' . json_encode($result));
                return ['success' => false, 'message' => 'No image data received from Imagen API. Response: ' . json_encode($result)];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Image generation error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate caption using Gemini AI
     * 
     * @param string $prompt
     * @return array
     */
    private function generateCaptionWithGemini($prompt) {
        try {
            $this->log('Generating caption with Gemini AI');
            
            
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . $this->gemini_api_key;
            
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt . ' Keep it engaging, concise, and suitable for social media. Include relevant hashtags.'
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 200
                ]
            ];
            
            $response = $this->makeAPIRequest($url, $data, 'POST');
            
            if (!$response['success']) {
                return ['success' => false, 'message' => $response['message']];
            }
            
            $result = $response['data'];
            
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $caption = trim($result['candidates'][0]['content']['parts'][0]['text']);
                $this->log('Caption generated successfully');
                return ['success' => true, 'caption' => $caption];
            } else {
                $this->log('Unexpected caption response: ' . json_encode($result));
                return ['success' => false, 'message' => 'No caption generated by Gemini'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Caption generation error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate caption for existing image using Gemini AI
     * 
     * @param string $image_path
     * @param string $prompt
     * @return array
     */
    private function generateCaptionForImage($image_path, $prompt) {
        try {
            if (!file_exists($image_path)) {
                return ['success' => false, 'message' => 'Image file not found'];
            }
            
            $this->log('Generating caption for image with Gemini AI');
            
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
            
            $image_data = base64_encode(file_get_contents($image_path));
            $mime_type = mime_content_type($image_path);
            
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt . ' Based on this image, create an engaging social media caption with relevant hashtags.'
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $mime_type,
                                    'data' => $image_data
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 200
                ]
            ];
            
            $response = $this->makeAPIRequest($url . '?key=' . $this->gemini_api_key, $data, 'POST');
            
            if (!$response['success']) {
                return ['success' => false, 'message' => $response['message']];
            }
            
            $result = $response['data'];
            
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $caption = trim($result['candidates'][0]['content']['parts'][0]['text']);
                $this->log('Caption generated successfully for image');
                return ['success' => true, 'caption' => $caption];
            } else {
                return ['success' => false, 'message' => 'No caption generated by Gemini for image'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Image caption generation error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Publish content to Facebook
     * 
     * @param string $caption
     * @param string|null $image_path
     * @param string $post_type
     * @return array
     */
    private function publishToFacebook($caption, $image_path, $post_type) {
        try {
            $this->log("Publishing to Facebook - Type: $post_type");
            

            $page_token = $this->getPageAccessToken();
            if (!$page_token) {
                return $this->formatResponse('error', 'Failed to get page access token');
            }
            
            $result = $this->postToFacebook($page_token, $caption, $image_path);
            
            if ($result['status'] == 'success') {
                $this->log('Post published successfully. Post ID: ' . $result['post_id']);
                return $this->formatResponse('success', 'Post published successfully', [
                    'post_id' => $result['post_id'],
                    'post_type' => $post_type,
                    'caption' => $caption,
                    'image_used' => !empty($image_path),
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                return $this->formatResponse('error', $result['message'], $result);
            }
            
        } catch (Exception $e) {
            return $this->formatResponse('error', 'Publishing error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get page access token
     * 
     * @return string|null
     */
    private function getPageAccessToken() {
        $url = "https://graph.facebook.com/{$this->config['facebook_api_version']}/{$this->page_id}?fields=access_token&access_token={$this->page_access_token}";
        
        $response = $this->makeAPIRequest($url, null, 'GET');
        
        if ($response['success'] && isset($response['data']['access_token'])) {
            return $response['data']['access_token'];
        }
        
        return null;
    }
    
    /**
     * Post to Facebook page
     * 
     * @param string $page_token
     * @param string $caption
     * @param string|null $image_path
     * @return array
     */
    private function postToFacebook($page_token, $caption, $image_path = null) {
        $retries = 0;
        
        while ($retries < $this->config['max_retries']) {
            try {
                $url = $image_path && file_exists($image_path)
                    ? "https://graph.facebook.com/{$this->config['facebook_api_version']}/{$this->page_id}/photos"
                    : "https://graph.facebook.com/{$this->config['facebook_api_version']}/{$this->page_id}/feed";

                $post_fields = $image_path && file_exists($image_path)
                    ? ['caption' => $caption, 'access_token' => $page_token, 'source' => new CURLFile($image_path)]
                    : ['message' => $caption, 'access_token' => $page_token];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $result = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    throw new Exception("cURL Error: $error");
                }
                
                $decoded = json_decode($result, true);
                
                if ($http_code == 200 && isset($decoded['id'])) {
                    return [
                        'status' => 'success', 
                        'message' => 'Post successful.', 
                        'post_id' => $decoded['id']
                    ];
                } else {
                    // Handle rate limiting
                    if ($http_code == 429 || (isset($decoded['error']['code']) && $decoded['error']['code'] == 4)) {
                        $retries++;
                        if ($retries < $this->config['max_retries']) {
                            $this->log("Rate limited. Retrying in {$this->config['retry_delay']} seconds... (Attempt $retries)");
                            sleep($this->config['retry_delay']);
                            continue;
                        }
                    }
                    
                    return [
                        'status' => 'error', 
                        'message' => 'Post failed: ' . ($decoded['error']['message'] ?? 'Unknown error'),
                        'response' => $decoded,
                        'http_code' => $http_code
                    ];
                }
                
            } catch (Exception $e) {
                $retries++;
                if ($retries < $this->config['max_retries']) {
                    $this->log("Error occurred. Retrying... (Attempt $retries): " . $e->getMessage());
                    sleep($this->config['retry_delay']);
                } else {
                    return ['status' => 'error', 'message' => $e->getMessage()];
                }
            }
        }
        
        return ['status' => 'error', 'message' => 'Maximum retries exceeded'];
    }
    
    /**
     * Load captions from JSON file
     * 
     * @return array|false
     */
    private function loadCaptionsFile() {
        if (!file_exists($this->caption_file)) {
            // Create sample file
            $sample_data = [
                'captions' => [
                    [
                        'file' => 'sample1.jpg',
                        'caption' => 'Sample caption for your first post!'
                    ],
                    [
                        'file' => 'sample2.jpg',
                        'caption' => 'Another amazing post caption here!'
                    ]
                ]
            ];
            file_put_contents($this->caption_file, json_encode($sample_data, JSON_PRETTY_PRINT));
        }
        
        $json_content = file_get_contents($this->caption_file);
        $data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('JSON decode error: ' . json_last_error_msg());
            return false;
        }
        
        return $data;
    }
    
    /**
     * Save captions to JSON file
     * 
     * @param array $data
     * @return bool
     */
    private function saveCaptionsFile($data) {
        $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->caption_file, $json_content) !== false;
    }
    
    /**
     * Make API request
     * 
     * @param string $url
     * @param array|null $data
     * @param string $method
     * @return array
     */
    private function makeAPIRequest($url, $data = null, $method = 'GET') {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if ($method == 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
        }
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => "cURL Error: $error"];
        }
        
        $decoded = json_decode($result, true);
        
        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true, 'data' => $decoded];
        } else {
            return [
                'success' => false, 
                'message' => $decoded['error']['message'] ?? "HTTP Error: $http_code",
                'data' => $decoded
            ];
        }
    }
    
    /**
     * Validate configuration
     * 
     * @return array
     */
    private function validateConfig() {
        if (empty($this->page_id)) {
            return ['valid' => false, 'message' => 'Page ID is required'];
        }
        
        if (empty($this->page_access_token)) {
            return ['valid' => false, 'message' => 'Page access token is required'];
        }
        
        if ($this->ai_use == 1 && empty($this->gemini_api_key)) {
            return ['valid' => false, 'message' => 'Gemini API key is required for AI mode'];
        }
        
        if (!is_writable(dirname($this->image_folder))) {
            return ['valid' => false, 'message' => 'Image folder directory is not writable'];
        }
        
        return ['valid' => true, 'message' => 'Configuration is valid'];
    }
    
    /**
     * Format response array
     * 
     * @param string $status
     * @param string $message
     * @param array $additional_data
     * @return array
     */
    private function formatResponse($status, $message, $additional_data = []) {
        return array_merge([
            'status' => $status,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ], $additional_data);
    }
    
    /**
     * Log messages
     * 
     * @param string $message
     */
    private function log($message) {
        $log_entry = date('Y-m-d H:i:s') . " - " . $message . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get available images in the images folder
     * 
     * @return array
     */
    public function getAvailableImages() {
        $images = [];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (is_dir($this->image_folder)) {
            $files = scandir($this->image_folder);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($extension, $allowed_extensions)) {
                        $images[] = $file;
                    }
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Get remaining captions count
     * 
     * @return int
     */
    public function getRemainingCaptionsCount() {
        $captions_data = $this->loadCaptionsFile();
        return $captions_data ? count($captions_data['captions']) : 0;
    }
    
    /**
     * Test Facebook connection
     * 
     * @return array
     */
    public function testFacebookConnection() {
        try {
            $page_token = $this->getPageAccessToken();
            if ($page_token) {
                return $this->formatResponse('success', 'Facebook connection successful');
            } else {
                return $this->formatResponse('error', 'Failed to connect to Facebook');
            }
        } catch (Exception $e) {
            return $this->formatResponse('error', 'Connection test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test Gemini AI connection
     * 
     * @return array
     */
    public function testGeminiConnection() {
        try {
            if (empty($this->gemini_api_key)) {
                return $this->formatResponse('error', 'Gemini API key not provided');
            }
            
            $result = $this->generateCaptionWithGemini('Test connection');
            if ($result['success']) {
                return $this->formatResponse('success', 'Gemini AI connection successful');
            } else {
                return $this->formatResponse('error', 'Gemini AI connection failed: ' . $result['message']);
            }
        } catch (Exception $e) {
            return $this->formatResponse('error', 'Gemini connection test failed: ' . $e->getMessage());
        }
    }
}

?>