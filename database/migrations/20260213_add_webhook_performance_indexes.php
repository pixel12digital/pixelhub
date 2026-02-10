<?php

/**
 * Migration: Índices para otimizar o fluxo do webhook (resposta rápida ao gateway)
 *
 * - tenant_message_channels: índice composto (provider, is_enabled) para
 *   resolveTenantByChannel e resolveChannelAccountId no ingest.
 * Seguro: só adiciona índices, não altera dados.
 */
class AddWebhookPerformanceIndexes
{
    public function up(PDO $db): void
    {
        // tenant_message_channels: lookup por provider + is_enabled (webhook e ConversationService)
        $stmt = $db->query("SHOW INDEX FROM tenant_message_channels WHERE Key_name = 'idx_provider_enabled'");
        if ($stmt->rowCount() === 0) {
            $db->exec("CREATE INDEX idx_provider_enabled ON tenant_message_channels (provider, is_enabled)");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP INDEX IF EXISTS idx_provider_enabled ON tenant_message_channels");
    }
}
