<?php
/**
 * Bring! Shopping List Sync Script - Activity Feed Version
 * 
 * Uses the activity feed timeline to track purchases with real timestamps!
 * 
 * How it works:
 * 1. Gets activity timeline from Bring! API
 * 2. Processes LIST_ITEMS_REMOVED events (items purchased)
 * 3. Tracks last processed event to avoid duplicates
 * 4. Adds items with actual purchase timestamps
 * 
 * Recommended schedule: Every 15-30 minutes
 */

// Prevent direct browser access
if (php_sapi_name() !== 'cli' && !isset($_GET['manual_run'])) {
    die("This script should be run via cron job only. Add ?manual_run=1 to test manually.");
}

// Include database connection
require_once __DIR__ . '/db_connection.php';

// Include the Bring API class
require_once __DIR__ . '/BringInventorySync.php';

// Log file
$logFile = __DIR__ . '/logs/bring_sync.log';

if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

/**
 * Get the UUID of the last processed activity event
 */
function getLastProcessedEvent($pdo, $listUuid) {
    $stmt = $pdo->prepare("
        SELECT last_event_uuid 
        FROM bring_sync_state 
        WHERE list_uuid = ?
    ");
    $stmt->execute([$listUuid]);
    $result = $stmt->fetch();
    return $result ? $result['last_event_uuid'] : null;
}

/**
 * Save the last processed event UUID
 */
function saveLastProcessedEvent($pdo, $listUuid, $eventUuid) {
    $stmt = $pdo->prepare("
        INSERT INTO bring_sync_state (list_uuid, last_event_uuid, last_sync_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            last_event_uuid = ?, 
            last_sync_at = NOW()
    ");
    $stmt->execute([$listUuid, $eventUuid, $eventUuid]);
}

/**
 * Add or increment purchased item
 */
function addOrIncrementPurchasedItem($pdo, $list_uuid, $list_name, $item_name, $specification, $purchased_at) {
    try {
        // Check if item exists
        $checkSql = "
            SELECT id, quantity 
            FROM purchased_items 
            WHERE list_uuid = ? 
            AND item_name = ? 
            AND (
                (specification = ? AND ? IS NOT NULL) 
                OR (specification IS NULL AND ? IS NULL)
            )
        ";
        
        $stmt = $pdo->prepare($checkSql);
        $spec = $specification ?: null;
        $stmt->execute([$list_uuid, $item_name, $spec, $spec, $spec]);
        
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Item exists - increment quantity
            $newQuantity = intval($existing['quantity']) + 1;
            
            $updateSql = "
                UPDATE purchased_items 
                SET quantity = ?, 
                    synced_at = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";
            
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                $newQuantity,
                $purchased_at,
                $existing['id']
            ]);
            
            return [
                'action' => 'incremented',
                'quantity' => $newQuantity
            ];
        } else {
            // New item - insert
            $insertSql = "
                INSERT INTO purchased_items 
                (list_uuid, list_name, item_name, specification, quantity, purchased_at, synced_at, created_at, updated_at) 
                VALUES (?, ?, ?, ?, 1, ?, ?, NOW(), NOW())
            ";
            
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([
                $list_uuid,
                $list_name,
                $item_name,
                $spec,
                $purchased_at,
                $purchased_at
            ]);
            
            return [
                'action' => 'added',
                'quantity' => 1
            ];
        }
        
    } catch (Exception $e) {
        logMessage("❌ ERROR adding item '{$item_name}': " . $e->getMessage());
        return null;
    }
}

try {
    logMessage("=== Starting Bring! Sync (Activity Feed Version) ===");
    
    // Initialize Bring API with credentials from environment
    $bringEmail = getenv('BRING_EMAIL');
    $bringPassword = getenv('BRING_PASSWORD');

    if (!$bringEmail || !$bringPassword) {
        throw new Exception("BRING_EMAIL and BRING_PASSWORD must be set in environment");
    }

    $bring = new BringInventorySync($bringEmail, $bringPassword);
    
    // Login
    $bring->login();
    logMessage("✅ Successfully logged into Bring! API");
    
    // Get all lists
    $lists = $bring->getLists();
    
    if (!$lists || !isset($lists['lists'])) {
        throw new Exception("Failed to retrieve lists from Bring! API");
    }
    
    $totalItemsAdded = 0;
    $totalItemsIncremented = 0;
    $totalEventsProcessed = 0;
    
    // Process each list
    foreach ($lists['lists'] as $list) {
        $listName = $list['name'];
        $listUuid = $list['listUuid'];
        
        logMessage("Processing list: $listName (UUID: $listUuid)");
        
        // Get activity feed
        $activity = $bring->getActivityFeed($listUuid);
        
        if (!$activity || !isset($activity['timeline'])) {
            logMessage("⚠️  No activity feed available for this list");
            continue;
        }
        
        $timeline = $activity['timeline'];
        $totalEvents = $activity['totalEvents'] ?? count($timeline);
        
        logMessage("Found $totalEvents events in activity feed");
        
        // Get last processed event
        $lastProcessedUuid = getLastProcessedEvent($pdo, $listUuid);
        logMessage("Last processed event: " . ($lastProcessedUuid ?? 'none'));
        
        // Process events from newest to oldest until we hit last processed
        $newEventsToProcess = [];
        foreach ($timeline as $event) {
            $eventUuid = $event['content']['uuid'] ?? null;
            
            if (!$eventUuid) continue;
            
            // Stop if we've reached already processed events
            if ($eventUuid === $lastProcessedUuid) {
                logMessage("Reached last processed event, stopping");
                break;
            }
            
            $newEventsToProcess[] = $event;
        }
        
        if (empty($newEventsToProcess)) {
            logMessage("No new events to process");
            continue;
        }
        
        logMessage("Processing " . count($newEventsToProcess) . " new events");
        
        // Process events in reverse order (oldest first)
        $newEventsToProcess = array_reverse($newEventsToProcess);
        
        foreach ($newEventsToProcess as $event) {
            $eventType = $event['type'];
            $content = $event['content'];
            $eventUuid = $content['uuid'];
            $sessionDate = $content['sessionDate'];
            
            // Convert ISO8601 to MySQL datetime
            $purchasedAt = date('Y-m-d H:i:s', strtotime($sessionDate));
            
            logMessage("  Event: $eventType at $sessionDate");
            
            // We only care about LIST_ITEMS_REMOVED (items purchased)
            if ($eventType === 'LIST_ITEMS_REMOVED') {
                $items = $content['items'] ?? [];
                logMessage("    Found " . count($items) . " purchased items");
                
                foreach ($items as $item) {
                    $itemName = $item['itemId'];
                    $specification = $item['specification'] ?? '';
                    
                    $result = addOrIncrementPurchasedItem(
                        $pdo,
                        $listUuid,
                        $listName,
                        $itemName,
                        $specification,
                        $purchasedAt
                    );
                    
                    if ($result) {
                        if ($result['action'] === 'added') {
                            logMessage("      ✅ Added: $itemName");
                            $totalItemsAdded++;
                        } else {
                            logMessage("      ➕ Incremented: $itemName (qty: {$result['quantity']})");
                            $totalItemsIncremented++;
                        }
                    }
                }
                
                $totalEventsProcessed++;
            } else {
                logMessage("    Skipping event type: $eventType");
            }
            
            // Save this as last processed event
            saveLastProcessedEvent($pdo, $listUuid, $eventUuid);
        }
    }
    
    logMessage("=== Sync Complete ===");
    logMessage("Events processed: $totalEventsProcessed");
    logMessage("Items added: $totalItemsAdded");
    logMessage("Items incremented: $totalItemsIncremented");
    logMessage("Total changes: " . ($totalItemsAdded + $totalItemsIncremented));
    
} catch (Exception $e) {
    logMessage("❌ ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
?>