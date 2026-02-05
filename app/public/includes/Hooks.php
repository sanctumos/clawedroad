<?php

declare(strict_types=1);

final class Hooks
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function fire(string $eventName, array $payload): void
    {
        $stmt = $this->pdo->prepare('SELECT id, webhook_url FROM hooks WHERE event_name = ? AND enabled = 1');
        $stmt->execute([$eventName]);
        $hooks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Defensive: fetchAll returns false on error without exception mode; with ERRMODE_EXCEPTION this is never hit
        if ($hooks === false) {
            $hooks = [];
        }
        $payloadJson = json_encode($payload);
        $now = date('Y-m-d H:i:s');
        foreach ($hooks as $hook) {
            $hookId = (int) ($hook['id'] ?? 0);
            $webhookUrl = (string) ($hook['webhook_url'] ?? '');
            $this->pdo->prepare('INSERT INTO hook_events (hook_id, event_name, payload, fired_at) VALUES (?, ?, ?, ?)')
                ->execute([$hookId > 0 ? $hookId : null, $eventName, $payloadJson, $now]);
            if ($webhookUrl !== '') {
                $this->postWebhook($webhookUrl, $payloadJson);
            }
        }
    }

    private function postWebhook(string $url, ?string $payloadJson): void
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson ?? '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }
}
