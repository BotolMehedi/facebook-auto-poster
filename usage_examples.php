<?php

/**
 * Facebook Auto Poster - Usage Examples
 */

// Include the library
require_once 'facebookAutoPoster.php';

// Configuration
$config = [
    'page_id' => 'PAGE_ID',
    'page_access_token' => 'PAGE_ACCESS_TOKEN',
    'image_folder' => './images/',
    'caption_file' => './captions.json',
    'ai_use' => 2, // 1 = AI, 2 = manual
    'gemini_api_key' => 'GEMINI_API_KEY', // Required only for AI mode
    'log_file' => './logs/facebook_poster.log',
    'facebook_api_version' => 'v20.0',
    'max_retries' => 3,
    'retry_delay' => 2 // seconds
];

// ===== EX1: Manual Method =====

// Note : After post successful, the image file will be DELETED and the caption entry will be REMOVED from JSON automatically

$config['ai_use'] = 2;
$poster = new FacebookAutoPoster($config);
$result = $poster->post();
echo "Result : " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";



// ===== EX2: AI Method =====

// IMPORTANT: AI mode now uses a SINGLE prompt for both image and caption
// The AI will generate an image based on your prompt, then create a matching caption that perfectly describes the generated image

$config['ai_use'] = 1;
$ai_poster = new FacebookAutoPoster($config);



// AI generated image with matching caption (RECOMMENDED)
$result = $ai_poster->post([
    'post_type' => 'image_caption',
    'prompt' => 'A beautiful sunset over mountains with vibrant orange and purple colors'
]);
echo "AI Image+Caption Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";



// AI generated caption only (no image)
$result = $ai_poster->post([
    'post_type' => 'caption_only',
    'prompt' => 'A motivational quote about success and perseverance in business'
]);
echo "AI Caption Only Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";



// AI generated image only (no caption)
$result = $ai_poster->post([
    'post_type' => 'image_only',
    'prompt' => 'Abstract geometric art with bright colors and modern design'
]);
echo "AI Image Only Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";







// ===== EX3: Testing Connection =====

$test_poster = new FacebookAutoPoster($config);

// Test Facebook Token
$fb_test = $test_poster->testFacebookConnection();
echo "Facebook Connection Test: " . json_encode($fb_test, JSON_PRETTY_PRINT) . "\n";

// Test Gemini API KEY
if ($config['ai_use'] == 1) {
    $ai_test = $test_poster->testGeminiConnection();
    echo "Gemini AI Connection Test: " . json_encode($ai_test, JSON_PRETTY_PRINT) . "\n";
}

// ===== EX4: Utility Functions =====

// Get available images
$images = $test_poster->getAvailableImages();
echo "Available Images: " . implode(', ', $images) . "\n";

// Get remaining captions count
$captions_count = $test_poster->getRemainingCaptionsCount();
echo "Remaining Captions: " . $captions_count . "\n";

// Get current configuration
$current_config = $test_poster->getConfig();
echo "Current Config: " . json_encode($current_config, JSON_PRETTY_PRINT) . "\n";


?>