<?php
/**
 * Environment Variable Loader
 * Loads variables from .env file into $_ENV and getenv()
 */

function loadEnv($path = null) {
    if ($path === null) {
        $path = __DIR__ . '/.env';
    }

    if (!file_exists($path)) {
        // Try parent directory if in subdirectory
        $parentPath = dirname(__DIR__) . '/.env';
        if (file_exists($parentPath)) {
            $path = $parentPath;
        } else {
            return false;
        }
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^["\'](.*)["\']\s*$/', $value, $matches)) {
                $value = $matches[1];
            }

            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    return true;
}

// Auto-load environment variables
loadEnv();
?>
