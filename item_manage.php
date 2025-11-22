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
$action = $data['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing action']);
    exit;
}

try {
    switch ($action) {
        case 'add':
            $name = $data['name'] ?? null;
            $quantity = $data['quantity'] ?? 1;
            $category = $data['category'] ?? 'Lebensmittel';
            $location = $data['location'] ?? 'Vorratskammer';
            $barcode = $data['barcode'] ?? null;

            if (!$name) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing product name']);
                exit;
            }

            // Check if item with same name already exists
            $stmt = $pdo->prepare("SELECT * FROM purchased_items WHERE item_name = ? LIMIT 1");
            $stmt->execute([$name]);
            $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingItem) {
                // Increment quantity of existing item
                $newQuantity = intval($existingItem['quantity']) + $quantity;
                $stmt = $pdo->prepare("UPDATE purchased_items SET quantity = ?, location = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $location, $existingItem['id']]);

                echo json_encode([
                    'success' => true,
                    'action' => 'incremented',
                    'message' => 'Menge erhöht',
                    'item' => [
                        'id' => $existingItem['id'],
                        'name' => $name,
                        'new_quantity' => $newQuantity
                    ]
                ]);
            } else {
                // Insert new item
                $stmt = $pdo->prepare("
                    INSERT INTO purchased_items
                    (item_name, specification, barcode, quantity, purchased_at, list_name, location)
                    VALUES (?, '', ?, ?, NOW(), ?, ?)
                ");
                $stmt->execute([$name, $barcode, $quantity, $category, $location]);

                $newId = $pdo->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'action' => 'created',
                    'message' => 'Artikel hinzugefügt',
                    'item' => [
                        'id' => $newId,
                        'name' => $name,
                        'quantity' => $quantity
                    ]
                ]);
            }
            break;

        case 'edit':
            $id = $data['id'] ?? null;
            $name = $data['name'] ?? null;
            $quantity = $data['quantity'] ?? 1;
            $category = $data['category'] ?? 'Lebensmittel';
            $location = $data['location'] ?? 'Vorratskammer';

            if (!$id || !$name) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }

            $stmt = $pdo->prepare("
                UPDATE purchased_items
                SET item_name = ?, quantity = ?, list_name = ?, location = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $quantity, $category, $location, $id]);

            echo json_encode([
                'success' => true,
                'action' => 'updated',
                'message' => 'Artikel aktualisiert',
                'item' => [
                    'id' => $id,
                    'name' => $name,
                    'quantity' => $quantity
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
