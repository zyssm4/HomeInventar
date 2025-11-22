<?php
session_start();

// Only allow if logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Include database connection
require '/volume1/homes/web/dbconfig_Brings.php';

try {
    // Get all inventory items ordered by name
    $stmt = $pdo->query("
        SELECT
            id,
            item_name as name,
            specification,
            list_name as category,
            quantity,
            barcode,
            purchased_at as date,
            synced_at,
            COALESCE(location, 'Vorratskammer') as location,
            CASE
                WHEN DATE(purchased_at) = CURDATE() THEN 1
                ELSE 0
            END as is_new
        FROM purchased_items
        ORDER BY item_name ASC
    ");
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add icon based on category or item name
    foreach ($items as &$item) {
        $item['icon'] = getItemIcon($item['name'], $item['category']);
        
        // Format specification if exists
        if (!empty($item['specification'])) {
            $item['display_name'] = $item['name'] . ' (' . $item['specification'] . ')';
        } else {
            $item['display_name'] = $item['name'];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'items' => $items,
        'total_items' => count($items),
        'total_quantity' => array_sum(array_column($items, 'quantity'))
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Get emoji icon for item based on name or category
 */
function getItemIcon($name, $category) {
    $name = strtolower($name);
    
    // Specific items
    $icons = [
        'apfel' => '🍎',
        'äpfel' => '🍎',
        'apple' => '🍎',
        'aprikose' => '🍑',
        'aprikosen' => '🍑',
        'banane' => '🍌',
        'bananen' => '🍌',
        'milch' => '🥛',
        'milk' => '🥛',
        'brot' => '🍞',
        'bread' => '🍞',
        'käse' => '🧀',
        'cheese' => '🧀',
        'ei' => '🥚',
        'eier' => '🥚',
        'eggs' => '🥚',
        'tomate' => '🍅',
        'tomaten' => '🍅',
        'kartoffel' => '🥔',
        'kartoffeln' => '🥔',
        'reis' => '🍚',
        'rice' => '🍚',
        'pasta' => '🍝',
        'nudeln' => '🍝',
        'kaffee' => '☕',
        'coffee' => '☕',
        'tee' => '🍵',
        'tea' => '🍵',
        'wasser' => '💧',
        'water' => '💧',
        'fleisch' => '🥩',
        'meat' => '🥩',
        'fisch' => '🐟',
        'fish' => '🐟',
        'gemüse' => '🥬',
        'vegetables' => '🥬',
        'obst' => '🍇',
        'fruit' => '🍇',
        'öl' => '🫒',
        'oil' => '🫒',
        'butter' => '🧈',
        'zucker' => '🍬',
        'sugar' => '🍬',
        'salz' => '🧂',
        'salt' => '🧂',
        'pfeffer' => '🌶️',
        'pepper' => '🌶️',
    ];
    
    // Check for exact match
    foreach ($icons as $keyword => $icon) {
        if (strpos($name, $keyword) !== false) {
            return $icon;
        }
    }
    
    // Category-based fallback
    switch (strtolower($category)) {
        case 'groceries':
        case 'lebensmittel':
            return '🛒';
        case 'home':
        case 'haushalt':
            return '🏠';
        case 'electronics':
        case 'elektronik':
            return '📱';
        default:
            return '📦';
    }
}
?>