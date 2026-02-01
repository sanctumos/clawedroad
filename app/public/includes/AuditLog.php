<?php

declare(strict_types=1);

/**
 * Audit log helper for staff/admin actions. Writes to audit_log table.
 */
final class AuditLog
{
    public static function write(\PDO $pdo, string $actorUserUuid, string $actionType, string $targetType, string $targetId, ?array $metadata = null): void
    {
        $metaJson = $metadata !== null ? json_encode($metadata) : null;
        $stmt = $pdo->prepare('INSERT INTO audit_log (actor_user_uuid, action_type, target_type, target_id, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorUserUuid, $actionType, $targetType, $targetId, $metaJson, date('Y-m-d H:i:s')]);
    }
}
