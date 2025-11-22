<?php
session_start();

// Only allow if logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

require '/volume1/homes/web/dbconfig_Brings.php';

header('Content-Type: application/json');

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
$barcode = $data['barcode'] ?? null;
$productName = $data['product_name'] ?? null;
$quantity = $data['quantity'] ?? 1;

if (!$barcode) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing barcode']);
    exit;
}

try {
    // Check if product with this barcode already exists in inventory
    $stmt = $pdo->prepare("SELECT * FROM purchased_items WHERE barcode = ? LIMIT 1");
    $stmt->execute([$barcode]);
    $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingItem) {
        // Item exists, increment quantity
        $newQuantity = intval($existingItem['quantity']) + $quantity;
        $stmt = $pdo->prepare("UPDATE purchased_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $existingItem['id']]);

        echo json_encode([
            'success' => true,
            'action' => 'incremented',
            'message' => 'Menge erhöht',
            'item' => [
                'id' => $existingItem['id'],
                'name' => $existingItem['item_name'],
                'barcode' => $barcode,
                'new_quantity' => $newQuantity
            ]
        ]);
        exit;
    }

    // Product not in inventory - try to look up product info
    if (!$productName) {
        // Try Open Food Facts API for product lookup
        $productInfo = lookupProduct($barcode);

        if ($productInfo && !empty($productInfo['name'])) {
            $productName = $productInfo['name'];
        } else {
            // Product not found, ask user for name
            echo json_encode([
                'success' => false,
                'needs_name' => true,
                'barcode' => $barcode,
                'message' => 'Produkt nicht gefunden. Bitte Namen eingeben.'
            ]);
            exit;
        }
    }

    // Insert new product into inventory
    $stmt = $pdo->prepare("
        INSERT INTO purchased_items
        (item_name, specification, barcode, quantity, purchased_at, list_name)
        VALUES (?, '', ?, ?, NOW(), 'Gescannt')
    ");
    $stmt->execute([$productName, $barcode, $quantity]);

    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'action' => 'created',
        'message' => 'Produkt hinzugefügt',
        'item' => [
            'id' => $newId,
            'name' => $productName,
            'barcode' => $barcode,
            'quantity' => $quantity
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Look up product information from Open Food Facts API
 */
function lookupProduct($barcode) {
    $url = "https://world.openfoodfacts.org/api/v0/product/" . urlencode($barcode) . ".json";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: HomeInventar/1.0'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $data = json_decode($response, true);

    if (!$data || $data['status'] !== 1 || !isset($data['product'])) {
        return null;
    }

    $product = $data['product'];

    // Try to get German name first, then generic name, then product name
    $name = $product['product_name_de']
        ?? $product['product_name']
        ?? $product['generic_name_de']
        ?? $product['generic_name']
        ?? null;

    return [
        'name' => $name,
        'brand' => $product['brands'] ?? null,
        'category' => $product['categories'] ?? null
    ];
}
?>
