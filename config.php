<?php
    session_start();

    // Load environment variables
    require_once __DIR__ . '/env_loader.php';

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (preg_match('#^https://homeinventar\.zyssethome\.synology\.me$#', $origin)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    // Handle preflight requests (OPTIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        exit;
    }

    // JSON output
    header('Content-Type: application/json');


    // Get password from environment variable
    $CORRECT_PASSWORD = getenv('APP_PASSWORD') ?: '12345';

    // Get password from POST (JSON)
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';

    if ($password === $CORRECT_PASSWORD) {
        // Set session flag
        $_SESSION['logged_in'] = true;
        echo json_encode(['success' => true, 'message' => 'Login erfolgreich']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falsche Zugangsdaten']);
    }
?>

