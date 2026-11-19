<?php
require_once __DIR__ . '/../auth/env.php';

function ar_get_openai_api_key()
{
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey && isset($_SERVER['OPENAI_API_KEY'])) {
        $apiKey = $_SERVER['OPENAI_API_KEY'];
    }
    if (!$apiKey && isset($_ENV['OPENAI_API_KEY'])) {
        $apiKey = $_ENV['OPENAI_API_KEY'];
    }

    return is_string($apiKey) ? trim($apiKey) : '';
}

?>
