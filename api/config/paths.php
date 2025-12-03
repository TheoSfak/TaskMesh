<?php
/**
 * API: Get Path Configuration
 * Returns installation path and base URLs for frontend
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/cors.php';

// No authentication required - this is public config

try {
    $config = PathConfig::getConfig();
    
    // Add full origin for absolute URLs
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $response = [
        'success' => true,
        'config' => [
            'basePath' => $config['basePath'],
            'apiBase' => $config['apiBase'],
            'assetsBase' => $config['assetsBase'],
            'pagesBase' => $config['pagesBase'],
            'fullUrl' => $protocol . '://' . $host . $config['basePath'],
            'apiUrl' => $protocol . '://' . $host . $config['apiBase']
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get path configuration: ' . $e->getMessage()
    ]);
}
