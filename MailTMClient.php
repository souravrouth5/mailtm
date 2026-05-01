<?php
/**
 * Mail.TM API Client - Shared functionality
 */
class MailTMClient {
    private string $baseUrl = 'https://api.mail.tm';
    private ?string $token = null;
    private array $account = [];

    public function getDomains(): ?array {
        $response = $this->request('GET', '/domains');
        return $response['hydra:member'] ?? null;
    }

    public function createAccount(string $email, string $password): ?array {
        $response = $this->request('POST', '/accounts', [
            'address' => $email,
            'password' => $password
        ]);
        if ($response) {
            $this->account = $response;
        }
        return $response;
    }

    public function getToken(string $email, string $password): ?string {
        $response = $this->request('POST', '/token', [
            'address' => $email,
            'password' => $password
        ]);
        if ($response && isset($response['token'])) {
            $this->token = $response['token'];
            $this->account = [
                'address' => $email,
                'id' => $response['id'] ?? null
            ];
            return $this->token;
        }
        return null;
    }

    public function getMessages(): ?array {
        if (!$this->token) return null;
        $response = $this->request('GET', '/messages', [], $this->token);
        return $response['hydra:member'] ?? null;
    }

    public function getMessageContent(string $messageId): ?array {
        if (!$this->token) return null;
        return $this->request('GET', "/messages/{$messageId}", [], $this->token);
    }

    public function extractOTP(string $text): ?string {
        $patterns = [
            '/\b(\d{4,8})\b/',
            '/(?:code|otp|pin|verification|confirm)[\s:]*(?:is\s*)?(\d{4,8})/i',
            '/(?:your\s+)?(?:code|otp|pin)[\s:]+\s*(\d{4,8})/i',
            '/\bOTP\s*:?\s*(\d{4,8})/i',
            '/\bCODE\s*:?\s*(\d{4,8})/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    public function generateRandomCredentials(): array {
        $domains = $this->getDomains();
        if (!$domains) {
            throw new Exception('Failed to fetch domains');
        }
        $domain = $domains[array_rand($domains)]['domain'];
        $random = bin2hex(random_bytes(8));
        return [
            'email' => "{$random}@{$domain}",
            'password' => bin2hex(random_bytes(12))
        ];
    }

    public function getAccountInfo(): array {
        return $this->account;
    }

    private function request(string $method, string $endpoint, array $data = [], ?string $token = null): ?array {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $headers = ['Content-Type: application/json'];
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300 && $response) {
            return json_decode($response, true) ?: null;
        }
        return null;
    }
}

/**
 * OTP Monitor - Continuously checks for OTP
 */
class OTPMonitor {
    private MailTMClient $client;
    private array $seenMessageIds = [];
    private int $checkInterval;
    private $outputCallback;

    public function __construct(MailTMClient $client, int $checkInterval = 5, callable $outputCallback = null) {
        $this->client = $client;
        $this->checkInterval = $checkInterval;
        $this->outputCallback = $outputCallback ?? function($msg) { echo $msg . PHP_EOL; };
    }

    public function start(): void {
        ($this->outputCallback)("Monitoring for OTP (Checking every {$this->checkInterval}s, Press Ctrl+C to stop)...");
        
        while (true) {
            $otp = $this->checkForNewOTP();
            if ($otp) {
                ($this->outputCallback)("OTP FOUND: {$otp}");
            }
            sleep($this->checkInterval);
        }
    }

    public function checkForNewOTP(): ?string {
        $messages = $this->client->getMessages();
        if (!$messages) return null;

        foreach ($messages as $message) {
            $msgId = $message['id'];
            
            if (!in_array($msgId, $this->seenMessageIds)) {
                $this->seenMessageIds[] = $msgId;
                
                $fullMessage = $this->client->getMessageContent($msgId);
                $text = ($fullMessage['text'] ?? '') . ' ' . ($fullMessage['subject'] ?? '') . ' ' . ($message['intro'] ?? '');
                
                $otp = $this->client->extractOTP($text);
                if ($otp) {
                    return $otp;
                }
            }
        }
        return null;
    }
}
