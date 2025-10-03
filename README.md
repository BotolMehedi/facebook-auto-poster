<h1 align="center">Facebook Auto Poster</h1>

<div align="center">

![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)
![Facebook API](https://img.shields.io/badge/Facebook-API%20v20.0-1877F2?style=for-the-badge&logo=facebook&logoColor=white)
![AI Powered](https://img.shields.io/badge/AI-Gemini-4285F4?style=for-the-badge&logo=google&logoColor=white)
[![Email](https://img.shields.io/badge/Email-D14836?style=for-the-badge&logo=gmail&logoColor=white)](mailto:hello@mehedi.fun)[![GitHub](https://img.shields.io/badge/BotolMehedi-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/BotolMehedi)

*A simple yet powerful PHP library for automating Facebook Page posts. Supports manual posting from local JSON/images as well as AI-powered content generation using Google Gemini. Designed for developers who want a clean, flexible, and cron frndly solution for Facebook automation.*

[Features](#-features) • [Installation](#-installation) • [Usage](#-usage) • [Examples](#-examples) • [Contributing](#-contributing)

</div>

---

## 🚀 Features

- 📝 Manual posts (JSON + Images)
- 🤖 AI-generated content
- 🎨 AI images with captions
- 📸 Only Image posts
- ✍️ Only Caption posts



---

## 📦 Requirements

- PHP 7.4 or higher
- cURL extension
- Facebook Access Token
- Gemini KEY (for AI features)

<center style="color:red;">To use Gemini for AI posting, you must enable billing on your Google Cloud account. Without billing, your API key will not work!</center>

---

## 🎨 Installation

I’m not going to tell you how to install it.  
If you can’t figure it out yourself, then fcuk u🖕This library is for people who know what they’re doing😪

---

## 🔑 Get Facebook Credentials

### A. Get Page ID
1. Go to your Facebook Page
2. Click "About"
3. Scroll down to find "Page ID"

### B. Get Access Token
1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create an app (if you don't have one)
3. Add "Facebook Login" product
4. Go to Graph API Explorer
5. Select your app
6. Generate token with these permissions:
   - `pages_show_list`
   - `pages_read_engagement`
   - `pages_manage_posts`
7. Click "Generate Access Token"
8. Copy the token

### C. Get Long-Lived Token (Recommended)

Your token expires in 1 hour by default. So get a long-lived token:

```php
<?php
$app_id = 'APP_ID';
$app_secret = 'APP_SECRET';
$short_token = 'ACCESS_TOKEN';

$url = "https://graph.facebook.com/oauth/access_token?" .
       "grant_type=fb_exchange_token&" .
       "client_id={$app_id}&" .
       "client_secret={$app_secret}&" .
       "fb_exchange_token={$short_token}";

$response = file_get_contents($url);
$data = json_decode($response, true);
echo "Long-lived token: " . $data['access_token'];
?>
```

---

## ⚙️ Config Setup

```php
<?php

require_once 'facebookAutoPoster.php';

$config = [
    'page_id'            => 'PAGE_ID',
    'page_access_token'  => 'PAGE_ACCESS_TOKEN',
    'image_folder'       => './images/',
    'caption_file'       => './captions.json',
    'ai_use'             => 2, // 1 = AI Mode, 2 = Manual Mode
    'gemini_api_key'     => 'GEMINI_KEY',
    'log_file'           => './logs/facebook_poster.log',
    'facebook_api_version' => 'v20.0'
];

$poster = new FacebookAutoPoster($config);
$result = $poster->post();

echo json_encode($result, JSON_PRETTY_PRINT);
```

> **💡 Pro Tip:** Make sure your `images/` and `logs/` directories are writable!

---

## 💡 Usage Examples

### 1️⃣ **Manual Posting**

```php
$config['ai_use'] = 2;
$poster = new FacebookAutoPoster($config);

$result = $poster->post();
print_r($result);
```

> **📌 Note:** Place your images in ./images/ and captions in captions.json

---

### 2️⃣ **AI Image + Caption**

```php
$config['ai_use'] = 1;
$ai_poster = new FacebookAutoPoster($config);

$result = $ai_poster->post([
    'post_type' => 'image_caption',
    'prompt'    => 'A beautiful sunset over mountains with vibrant colors'
]);
```

### 3️⃣ **AI Caption Only**

```php
$result = $ai_poster->post([
    'post_type' => 'caption_only',
    'prompt'    => 'Motivational quote about success and perseverance'
]);
```

### 4️⃣ **AI Image Only**

```php
$result = $ai_poster->post([
    'post_type' => 'image_only',
    'prompt'    => 'Abstract digital art with vibrant neon shapes'
]);
```

---

## ⏰ Cron Job Setup

**Create `cron.php`:**

```php
<?php
require_once 'facebookAutoPoster.php';

$poster = new FacebookAutoPoster([
    'page_id' => 'PAGE_ID',
    'page_access_token' => 'PAGE_ACCESS_TOKEN',
    'ai_use' => 2,
    'image_folder' => '/images/',
    'caption_file' => '/captions.json'
]);

$result = $poster->post();

file_put_contents('./logs/cron.log',
    date('Y-m-d H:i:s') . ' - ' . json_encode($result) . PHP_EOL,
    FILE_APPEND
);
```

**Cron Schedule Examples:**

```bash
# 🕐 Every hour
0 * * * * /usr/bin/php /fbauto/cron.php

# 🕒 3 times daily (9 AM, 3 PM, 9 PM)
0 9,15,21 * * * /usr/bin/php /fbauto/cron.php

# 🌙 Once daily at midnight
0 0 * * * /usr/bin/php /fbauto/cron.php
```

---

## 🔧 Utility Methods

```php
// Facebook Token Test
$poster->testFacebookConnection();

// Gemini Key Test
$poster->testGeminiConnection();

// Get Available images
$images = $poster->getAvailableImages();

// Count remaining captions
$count = $poster->getRemainingCaptionsCount();

// Current configuration
$configs = $poster->getConfig();
```

---

## 📊 Response Format

Every method returns a standardized JSON response:

```json
{
  "status": "success",
  "message": "Post published successfully!",
  "timestamp": "2025-10-03 12:00:00",
  "post_id": "123456789_987654321",
  "post_type": "image_caption"
}
```

---

## 🐛 Troubleshooting

<details>
<summary><b>❌ Invalid Token Error</b></summary>

**Problem:** `Invalid OAuth access token`

**Solution:** 
- Verify your Page Access Token is valid
- Ensure it has `pages_manage_posts` permission
- Regenerate token if expired
</details>

<details>
<summary><b>🔒 Permission Denied</b></summary>

**Problem:** Can't write to files or directories

**Solution:**
```bash
chmod 755 images/
chmod 755 logs/
chmod 644 captions.json
```
</details>

<details>
<summary><b>⏱️ Rate Limit Exceeded</b></summary>

**Problem:** Too many requests to Facebook API

**Solution:**
- The library retries automatically
- Reduce posting frequency in cron
- Wait a few minutes before retrying
</details>

<details>
<summary><b>📝 No Captions Available</b></summary>

**Problem:** `captions.json` is empty or invalid

**Solution:**
- Verify JSON format is correct
- Add more captions to the file
- Check file permissions
</details>

---

## 🔐 Best Practices

- Store tokens in `.env` files (never commit them!)
- Use environment variables for sensitive data
- Set proper file permissions (755 for dirs, 644 for files)
- Test in development before production
- Monitor logs regularly
- Rotate API keys periodically

---

## 📄 License

This project is licensed under the **MIT License** - feel free to use, modify, and share! 🎉

---

## 🤝 Contributing

Contributions are welcome! Here's how you can help:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request🎯

Found a bug? 🐛 [Open an issue](https://github.com/BotolMehedi/facebook-auto-poster/issues)

---

<div align="center">

### 🌟 Star this repo if you find it helpful!

Made with ❤️ and 💦 by <b>BotolMehedi</b>

</div>