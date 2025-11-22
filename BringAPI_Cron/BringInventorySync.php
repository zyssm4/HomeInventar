<?php
/**
 * Bring! API Integration Class
 * Handles authentication and data retrieval from Bring! Shopping List API
 */

define("BRING_API_BASE", "https://api.getbring.com/rest/v2");

class BringInventorySync {
    private $email;
    private $password;
    private $token;
    private $uuid;
    private $refreshToken;
    private $apiKey;

    public function __construct($email, $password) {
        $this->email = $email;
        $this->password = $password;
        $this->token = null;
        $this->uuid = null;
        $this->refreshToken = null;
        $this->apiKey = getenv('BRING_API_KEY') ?: '';

        if (empty($this->apiKey)) {
            throw new Exception("BRING_API_KEY must be set in environment");
        }
    }

    /**
     * Login to Bring! API and obtain access token
     */
    public function login() {
        $url = BRING_API_BASE . "/bringauth";

        $data = [
            "email" => $this->email,
            "password" => $this->password
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new Exception("cURL error: " . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("Login failed, HTTP status: $httpCode - Response: $response");
        }

        $json = json_decode($response, true);
        
        $this->token = $json['access_token'] ?? null;
        $this->uuid = $json['uuid'] ?? null;
        $this->refreshToken = $json['refresh_token'] ?? null;
        
        if (!$this->token || !$this->uuid) {
            throw new Exception("Login response missing required fields");
        }
    }

    /**
     * Get all shopping lists for the user
     */
    public function getLists() {
        if (!$this->token || !$this->uuid) {
            throw new Exception("Not logged in. Call login() first.");
        }

        $url = BRING_API_BASE . "/bringusers/" . $this->uuid . "/lists";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-BRING-API-KEY: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->token,
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new Exception("cURL error: " . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("Failed to get lists, HTTP status: $httpCode - Response: $response");
        }

        return json_decode($response, true);
    }

    /**
     * Get all items from a specific list
     * Note: Bring! API may limit results. Recently list max is 24 items.
     */
    public function getListItems($listUuid) {
        if (!$this->token || !$this->uuid) {
            throw new Exception("Not logged in. Call login() first.");
        }

        $url = BRING_API_BASE . "/bringlists/" . $listUuid;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-BRING-API-KEY: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->token,
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new Exception("cURL error: " . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("Failed to get list items, HTTP status: $httpCode - Response: $response");
        }

        $data = json_decode($response, true);
        
        // Log if recently list seems truncated
        if (isset($data['recently']) && count($data['recently']) >= 12) {
            error_log("Bring! API: Recently list has " . count($data['recently']) . " items (may be limited by API)");
        }
        
        return $data;
    }
    
    /**
     * Get activity feed/history (if available in API)
     * This would be the best way to track purchases
     */
    public function getActivityFeed($listUuid) {
        if (!$this->token || !$this->uuid) {
            throw new Exception("Not logged in. Call login() first.");
        }

        // Try different possible endpoints for activity
        $possibleEndpoints = [
            BRING_API_BASE . "/bringlists/" . $listUuid . "/activity",
            BRING_API_BASE . "/bringlists/" . $listUuid . "/history",
            BRING_API_BASE . "/bringlists/" . $listUuid . "/log",
            BRING_API_BASE . "/bringusers/" . $this->uuid . "/lists/" . $listUuid . "/activity"
        ];

        foreach ($possibleEndpoints as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-BRING-API-KEY: cof4Nc6D8saplXjE3h3HXqHH8m7VU2i1Gs0g85Sp',
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return json_decode($response, true);
            }
        }

        // No activity endpoint found
        return null;
    }

    /**
     * Get recently purchased items within a specified time range
     * NOTE: Bring! API doesn't provide timestamps, so time filtering is unreliable
     * 
     * @param string $listUuid The UUID of the list
     * @param int $hoursBack Number of hours to look back (NOT RELIABLE - use state tracking instead)
     * @return array Array of recently purchased items
     */
    public function getRecentlyPurchased($listUuid, $hoursBack = 24) {
        $items = $this->getListItems($listUuid);
        
        if (!$items || !isset($items['recently'])) {
            return [];
        }

        // WARNING: This method is kept for backward compatibility
        // but is NOT reliable since Bring! doesn't provide timestamps
        // Use state tracking (sync_bring_items.php) instead
        return $items['recently'];
    }

    public function getToken() {
        return $this->token;
    }

    public function getUuid() {
        return $this->uuid;
    }
}
?>