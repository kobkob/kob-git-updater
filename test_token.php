<?php
/**
 * Quick test to verify GitHub token configuration and access
 */

echo "=== GitHub Token Test ===\n";

// Test 1: Check if we can read WordPress options (requires WordPress context)
if (!defined('ABSPATH')) {
    echo "❌ Not running in WordPress context\n";
    echo "Run this script from within WordPress admin or load WordPress first\n";
    exit;
}

// Test 2: Check token configuration
$token = get_option('giu_github_token', '');
if (empty($token)) {
    echo "❌ No GitHub token configured in WordPress\n";
    echo "Please check the plugin settings\n";
    exit;
}

echo "✅ GitHub token found\n";
echo "- Length: " . strlen($token) . " characters\n";
echo "- Prefix: " . substr($token, 0, 7) . "...\n";
echo "- Type: " . (strpos($token, 'ghp_') === 0 ? 'Personal Access Token' : 
                (strpos($token, 'github_pat_') === 0 ? 'Fine-grained Token' : 'Unknown')) . "\n\n";

// Test 3: Direct API test with the token
echo "=== Direct API Test ===\n";

$test_url = 'https://api.github.com/repos/kobkob/ai-child';
$args = [
    'headers' => [
        'Authorization' => 'token ' . $token,
        'User-Agent' => 'KobGitUpdater-Test/1.0',
        'Accept' => 'application/vnd.github.v3+json'
    ],
    'timeout' => 10
];

echo "Testing: {$test_url}\n";
$response = wp_remote_get($test_url, $args);

if (is_wp_error($response)) {
    echo "❌ Request failed: " . $response->get_error_message() . "\n";
    exit;
}

$status_code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);

echo "- Status Code: {$status_code}\n";

if ($status_code === 200) {
    $data = json_decode($body, true);
    echo "✅ Repository accessible!\n";
    echo "- Repository Name: " . ($data['full_name'] ?? 'unknown') . "\n";
    echo "- Private: " . (($data['private'] ?? false) ? 'Yes' : 'No') . "\n";
    echo "- Default Branch: " . ($data['default_branch'] ?? 'unknown') . "\n";
    echo "- Last Updated: " . ($data['updated_at'] ?? 'unknown') . "\n";
} else {
    echo "❌ Repository not accessible\n";
    echo "- Error: " . ($status_code === 404 ? 'Not Found (repository doesn\'t exist or no access)' : 
                       ($status_code === 401 ? 'Unauthorized (invalid token)' : 
                        ($status_code === 403 ? 'Forbidden (insufficient permissions)' : "HTTP {$status_code}"))) . "\n";
    
    // Try to parse error message
    $error_data = json_decode($body, true);
    if ($error_data && isset($error_data['message'])) {
        echo "- Message: " . $error_data['message'] . "\n";
    }
}

// Test 4: Check releases endpoint specifically
echo "\n=== Releases Endpoint Test ===\n";
$releases_url = 'https://api.github.com/repos/kobkob/ai-child/releases/latest';
echo "Testing: {$releases_url}\n";

$response = wp_remote_get($releases_url, $args);
$status_code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);

echo "- Status Code: {$status_code}\n";

if ($status_code === 200) {
    $data = json_decode($body, true);
    echo "✅ Latest release found!\n";
    echo "- Tag: " . ($data['tag_name'] ?? 'unknown') . "\n";
    echo "- Published: " . ($data['published_at'] ?? 'unknown') . "\n";
} elseif ($status_code === 404) {
    echo "ℹ️  No releases found (this is normal for repositories without releases)\n";
} else {
    echo "❌ Error accessing releases\n";
    $error_data = json_decode($body, true);
    if ($error_data && isset($error_data['message'])) {
        echo "- Message: " . $error_data['message'] . "\n";
    }
}

echo "\n=== Test Complete ===\n";