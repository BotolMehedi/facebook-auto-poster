<?php
require_once 'facebookAutoPoster.php';


// EX1 - Manual Method
$poster = new FacebookAutoPoster([
    'page_id' => 'PAGE_ID',
    'page_access_token' => 'PAGE_ACCESS_TOKEN',
    'ai_use' => 2, // 1 = AI, 2 = manual
    'image_folder' => '/images/',
    'caption_file' => '/captions.json'
]);

$result = $poster->post();

// Log the result
file_put_contents('./logs/cron.log', 
    date('Y-m-d H:i:s') . ' - ' . json_encode($result) . PHP_EOL, 
    FILE_APPEND
);




// EX2 - AI Method
try {
    $ai_cron_poster = new FacebookAutoPoster([
        'page_id' => 'PAGE_ID',
        'page_access_token' => 'PAGE_ACCESS_TOKEN',
        'ai_use' => 1,
        'gemini_api_key' => 'GEMINI_KEY'
    ]);
    
    // prompts
    $prompts = [
        'A serene morning landscape with mist over a lake',
        'Fresh healthy breakfast with fruits and smoothie',
        'Modern office interior with natural lighting',
        'Cozy reading corner with books and warm lighting',
        'Fitness motivation scene with gym equipment'
    ];
    
    // Pick Random Prompt
    $random_prompt = $prompts[array_rand($prompts)];
    
    $result = $ai_cron_poster->post([
        'post_type' => 'image_caption',
        'prompt' => $random_prompt
    ]);
    
    if ($result['status'] == 'success') {
        echo "✅ Post successful! Post ID: " . $result['post_id'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

?>