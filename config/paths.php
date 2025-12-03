<?php
/**
 * TaskMesh - Path Configuration
 * Auto-detects installation path and provides it to all files
 */

class PathConfig {
    private static $installationPath = null;
    private static $basePath = null;
    
    /**
     * Get installation path (e.g., "/task" or "" for root)
     */
    public static function getInstallationPath() {
        if (self::$installationPath !== null) {
            return self::$installationPath;
        }
        
        // Try to get from settings first
        try {
            require_once __DIR__ . '/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'installation_path'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Use saved value only if it's not null AND not empty string
            if ($result && $result['setting_value'] !== null && $result['setting_value'] !== '') {
                self::$installationPath = $result['setting_value'];
                error_log("[PathConfig] Loaded from DB: " . self::$installationPath);
                return self::$installationPath;
            }
        } catch (Exception $e) {
            // Settings not available yet, auto-detect
            error_log("[PathConfig] DB load failed: " . $e->getMessage());
        }
        
        // Auto-detect from current script path
        error_log("[PathConfig] Auto-detecting installation path...");
        self::$installationPath = self::detectInstallationPath();
        
        // Try to save to database for future use
        self::saveInstallationPath(self::$installationPath);
        
        return self::$installationPath;
    }
    
    /**
     * Auto-detect installation path from REQUEST_URI
     */
    private static function detectInstallationPath() {
        // Get the request URI
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Debug logging
        error_log("[PathConfig] SCRIPT_NAME: $scriptName");
        
        // Extract base path from script name
        // e.g., /TaskMesh/api/config/paths.php -> /TaskMesh
        // e.g., /api/auth/login.php -> ""
        
        $scriptPath = dirname($scriptName);
        error_log("[PathConfig] scriptPath (dirname): $scriptPath");
        
        $parts = explode('/', trim($scriptPath, '/'));
        error_log("[PathConfig] parts: " . json_encode($parts));
        
        // Remove known directories (api, pages, assets, etc.)
        $knownDirs = ['api', 'pages', 'assets', 'config', 'middleware', 'database'];
        $baseParts = [];
        
        foreach ($parts as $part) {
            if (in_array(strtolower($part), $knownDirs)) {
                // Stop when we hit a known directory
                break;
            }
            $baseParts[] = $part;
        }
        
        error_log("[PathConfig] baseParts after filtering: " . json_encode($baseParts));
        
        // Build base path
        if (!empty($baseParts)) {
            $basePath = '/' . implode('/', $baseParts);
            
            // Verify it's not the document root
            $firstPart = strtolower($baseParts[0]);
            if (in_array($firstPart, ['localhost', 'www', 'public_html', 'htdocs'])) {
                error_log("[PathConfig] Detected document root, returning empty");
                return '';
            }
            
            error_log("[PathConfig] Detected basePath: $basePath");
            return $basePath;
        }
        
        error_log("[PathConfig] No basePath detected, returning empty");
        return '';
    }
    
    /**
     * Save detected installation path to database
     */
    private static function saveInstallationPath($path) {
        try {
            require_once __DIR__ . '/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("UPDATE system_settings SET setting_value = :path WHERE setting_key = 'installation_path'");
            $stmt->bindParam(':path', $path);
            $stmt->execute();
        } catch (Exception $e) {
            // Silently fail if we can't save
        }
    }
    
    /**
     * Get full base path (installation path)
     */
    public static function getBasePath() {
        if (self::$basePath !== null) {
            return self::$basePath;
        }
        
        self::$basePath = self::getInstallationPath();
        return self::$basePath;
    }
    
    /**
     * Get API URL base
     */
    public static function getApiBase() {
        return self::getBasePath() . '/api';
    }
    
    /**
     * Get assets URL base
     */
    public static function getAssetsBase() {
        return self::getBasePath() . '/assets';
    }
    
    /**
     * Get pages URL base
     */
    public static function getPagesBase() {
        return self::getBasePath() . '/pages';
    }
    
    /**
     * Build full URL path
     */
    public static function url($path) {
        $base = self::getBasePath();
        $path = ltrim($path, '/');
        
        if (empty($base)) {
            return '/' . $path;
        }
        
        return $base . '/' . $path;
    }
    
    /**
     * Get JSON response with path configuration
     */
    public static function getConfig() {
        return [
            'basePath' => self::getBasePath(),
            'apiBase' => self::getApiBase(),
            'assetsBase' => self::getAssetsBase(),
            'pagesBase' => self::getPagesBase()
        ];
    }
}

// Helper function for easy access
function app_path($path = '') {
    return PathConfig::url($path);
}

function api_url($endpoint = '') {
    return PathConfig::getApiBase() . '/' . ltrim($endpoint, '/');
}
