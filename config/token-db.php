<?php
/**
 * Simple JSON-based Token Database
 * Stores tokens with short IDs for cleaner URLs
 */

class TokenDatabase
{
    private string $dbFile;
    
    public function __construct()
    {
        $this->dbFile = __DIR__ . '/../storage/tokens.json';
        $this->ensureDbExists();
    }
    
    /**
     * Generate a short random token ID
     */
    private function generateShortId(): string
    {
        // Verwende Base32 ohne verwirrende Zeichen (0, O, I, L, 1)
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $id = '';
        for ($i = 0; $i < 8; $i++) {
            $id .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $id;
    }
    
    /**
     * Store token data and return short ID
     */
    public function storeToken(array $tokenData): string
    {
        $shortId = $this->generateShortId();
        
        // Prüfe ob ID bereits existiert (sehr unwahrscheinlich bei 8 Zeichen Base32)
        $data = $this->loadData();
        while (isset($data[$shortId])) {
            $shortId = $this->generateShortId();
        }
        
        // Token mit Metadaten speichern
        $data[$shortId] = [
            'guest_name' => $tokenData['guest_name'],
            'starts' => $tokenData['starts'],
            'expires' => $tokenData['expires'],
            'access_type' => $tokenData['access_type'] ?? 'house_only',
            'created' => time(),
            'used_count' => 0,
            'last_used' => null
        ];
        
        $this->saveData($data);
        return $shortId;
    }
    
    /**
     * Retrieve token data by short ID
     */
    public function getToken(string $shortId): ?array
    {
        $data = $this->loadData();
        return $data[$shortId] ?? null;
    }
    
    /**
     * Mark token as used (for statistics)
     */
    public function markTokenUsed(string $shortId): void
    {
        $data = $this->loadData();
        if (isset($data[$shortId])) {
            $data[$shortId]['used_count']++;
            $data[$shortId]['last_used'] = time();
            $this->saveData($data);
        }
    }
    
    /**
     * Delete expired tokens (cleanup)
     */
    public function cleanupExpiredTokens(): int
    {
        $data = $this->loadData();
        $deleted = 0;
        $now = time();
        
        foreach ($data as $id => $token) {
            if ($token['expires'] < $now) {
                unset($data[$id]);
                $deleted++;
            }
        }
        
        if ($deleted > 0) {
            $this->saveData($data);
        }
        
        return $deleted;
    }
    
    /**
     * Get statistics
     */
    public function getStats(): array
    {
        $data = $this->loadData();
        $total = count($data);
        $active = 0;
        $expired = 0;
        $now = time();
        
        foreach ($data as $token) {
            if ($token['expires'] > $now && $token['starts'] <= $now) {
                $active++;
            } elseif ($token['expires'] <= $now) {
                $expired++;
            }
        }
        
        return [
            'total_tokens' => $total,
            'active_tokens' => $active,
            'expired_tokens' => $expired,
            'pending_tokens' => $total - $active - $expired
        ];
    }
    
    /**
     * Load data from JSON file
     */
    private function loadData(): array
    {
        if (!file_exists($this->dbFile)) {
            return [];
        }
        
        $content = file_get_contents($this->dbFile);
        if ($content === false) {
            return [];
        }
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }
    
    /**
     * Save data to JSON file
     */
    private function saveData(array $data): bool
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->dbFile, $json, LOCK_EX) !== false;
    }
    
    /**
     * Ensure database file and directory exist
     */
    private function ensureDbExists(): void
    {
        $dir = dirname($this->dbFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (!file_exists($this->dbFile)) {
            $this->saveData([]);
        }
    }
}