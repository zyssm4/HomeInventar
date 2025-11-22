<?php
session_start();

// Only allow if logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

require '/volume1/homes/web/dbconfig_Brings.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$barcode = $data['barcode'] ?? null;
$decrementOnly = $data['decrement_only'] ?? false; // If true, only reduce quantity by 1
$incrementOnly = $data['increment_only'] ?? false; // If true, only increase quantity by 1

if (!$id && !$barcode) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ID or barcode']);
    exit;
}

try {
    // Fetch the item
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM purchased_items WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM purchased_items WHERE barcode = ? LIMIT 1");
        $stmt->execute([$barcode]);
    }
    
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        exit;
    }
    
    $currentQuantity = intval($item['quantity']);
    
    // Decide action: decrement or delete
    if ($incrementOnly) {
    // Increment quantity by 1
    $newQuantity = $currentQuantity + 1;
    $stmt = $pdo->prepare("UPDATE purchased_items SET quantity = ? WHERE id = ?");
    $stmt->execute([$newQuantity, $item['id']]);
    
    $action = 'incremented';
    $message = "Quantity increased from $currentQuantity to $newQuantity";

    } elseif ($decrementOnly && $currentQuantity > 1) {
        // Decrement quantity by 1
        $newQuantity = $currentQuantity - 1;
        $stmt = $pdo->prepare("UPDATE purchased_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $item['id']]);
        
        $action = 'decremented';
        $message = "Quantity reduced from $currentQuantity to $newQuantity";
        
    } else {
        // Remove completely
        $stmt = $pdo->prepare("DELETE FROM purchased_items WHERE id = ?");
        $stmt->execute([$item['id']]);
        
        // Also clear sync tracking so item can be synced again if re-purchased
        $itemHash = md5($item['list_uuid'] . '|' . $item['item_name'] . '|' . ($item['specification'] ?? ''));
        $stmt = $pdo->prepare("DELETE FROM bring_synced_items WHERE item_hash = ?");
        $stmt->execute([$itemHash]);
        
        $action = 'deleted';
        $message = 'Item removed from inventory and sync tracking cleared';
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'action' => $action,
        'message' => $message,
        'item' => [
            'id' => $item['id'],
            'name' => $item['item_name'],
            'specification' => $item['specification'],
            'previous_quantity' => $currentQuantity,
            'new_quantity' => $action === 'decremented' ? $newQuantity : 0
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>