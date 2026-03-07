<?php
/**
 * API Keys Endpoint
 * Returns API keys for frontend use
 * IMPORTANT: In production, limit which keys are exposed to the client
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Return only the keys that are safe to use from client-side
$apiKeys = [
    'gemini' => GEMINI_API_KEY,
    'googleSearch' => GOOGLE_SEARCH_API_KEY,
    'serpapi' => SERPAPI_KEY
];

echo json_encode($apiKeys);