<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar mensagens agendadas de follow-up
 */
class ScheduledMessageService
{
    /**
     * Cria uma nova mensagem agendada
     */
    public static function create(array $data): int
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO scheduled_messages (
                agenda_item_id, opportunity_id, lead_id, tenant_id, conversation_id,
                message_text, scheduled_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['agenda_item_id'] ?? null,
            $data['opportunity_id'] ?? null,
            $data['lead_id'] ?? null,
            $data['tenant_id'] ?? null,
            $data['conversation_id'] ?? null,
            $data['message_text'],
            $data['scheduled_at'],
            $data['created_by'] ?? null,
        ]);
        
        return (int)$db->lastInsertId();
    }
    
    /**
     * Busca mensagens pendentes que devem ser enviadas agora
     */
    public static function getPendingMessages(): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->query("
            SELECT sm.*, 
                   o.name as opportunity_name,
                   l.name as lead_name, l.phone as lead_phone,
                   t.name as tenant_name, t.phone as tenant_phone
            FROM scheduled_messages sm
            LEFT JOIN opportunities o ON sm.opportunity_id = o.id
            LEFT JOIN leads l ON sm.lead_id = l.id
            LEFT JOIN tenants t ON sm.tenant_id = t.id
            WHERE sm.status = 'pending'
            AND sm.scheduled_at <= NOW()
            ORDER BY sm.scheduled_at ASC
            LIMIT 50
        ");
        
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Envia uma mensagem agendada via WhatsApp
     */
    public static function send(int $messageId): bool
    {
        $db = DB::getConnection();
        
        // Busca a mensagem
        $stmt = $db->prepare("
            SELECT sm.*, 
                   l.phone as lead_phone,
                   t.phone as tenant_phone,
                   c.id as conversation_id, c.channel_id, c.contact_external_id
            FROM scheduled_messages sm
            LEFT JOIN leads l ON sm.lead_id = l.id
            LEFT JOIN tenants t ON sm.tenant_id = t.id
            LEFT JOIN conversations c ON (
                (sm.lead_id IS NOT NULL AND c.lead_id = sm.lead_id) OR
                (sm.tenant_id IS NOT NULL AND c.tenant_id = sm.tenant_id)
            )
            WHERE sm.id = ? AND sm.status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();
        
        if (!$message) {
            return false;
        }
        
        // Determina o telefone de destino
        $phone = $message['lead_phone'] ?? $message['tenant_phone'] ?? null;
        if (!$phone) {
            self::markAsFailed($messageId, 'Telefone não encontrado');
            return false;
        }
        
        // Normaliza telefone para E.164
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (!str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }
        
        try {
            // Envia via CommunicationHubController
            require_once __DIR__ . '/../Controllers/CommunicationHubController.php';
            $controller = new \PixelHub\Controllers\CommunicationHubController();
            
            // Simula POST para enviar mensagem
            $_POST = [
                'channel_id' => $message['channel_id'] ?? 'pixel12digital',
                'to' => $phone,
                'message' => $message['message_text'],
                'conversation_id' => $message['conversation_id'] ?? null,
            ];
            
            // Chama método de envio
            ob_start();
            $controller->send();
            ob_end_clean();
            
            // Marca como enviada
            self::markAsSent($messageId, $message['conversation_id']);
            
            return true;
        } catch (\Exception $e) {
            error_log("[ScheduledMessage] Erro ao enviar mensagem ID {$messageId}: " . $e->getMessage());
            self::markAsFailed($messageId, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marca mensagem como enviada
     */
    public static function markAsSent(int $messageId, ?int $conversationId = null): void
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE scheduled_messages 
            SET status = 'sent', 
                sent_at = NOW(),
                conversation_id = COALESCE(?, conversation_id)
            WHERE id = ?
        ");
        $stmt->execute([$conversationId, $messageId]);
    }
    
    /**
     * Marca mensagem como falha
     */
    public static function markAsFailed(int $messageId, string $reason): void
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE scheduled_messages 
            SET status = 'failed', 
                failed_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$reason, $messageId]);
    }
    
    /**
     * Detecta resposta do lead após envio
     */
    public static function detectResponse(int $conversationId): void
    {
        $db = DB::getConnection();
        
        // Busca mensagens enviadas para esta conversa que ainda não detectaram resposta
        $stmt = $db->prepare("
            SELECT id FROM scheduled_messages
            WHERE conversation_id = ?
            AND status = 'sent'
            AND response_detected = 0
        ");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll();
        
        foreach ($messages as $msg) {
            // Marca como resposta detectada
            $updateStmt = $db->prepare("
                UPDATE scheduled_messages
                SET response_detected = 1,
                    response_detected_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$msg['id']]);
            
            // Cria notificação no Inbox
            self::createResponseNotification($msg['id']);
        }
    }
    
    /**
     * Cria notificação de resposta no Inbox
     */
    private static function createResponseNotification(int $messageId): void
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT sm.*, 
                   o.name as opportunity_name,
                   l.name as lead_name,
                   t.name as tenant_name
            FROM scheduled_messages sm
            LEFT JOIN opportunities o ON sm.opportunity_id = o.id
            LEFT JOIN leads l ON sm.lead_id = l.id
            LEFT JOIN tenants t ON sm.tenant_id = t.id
            WHERE sm.id = ?
        ");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();
        
        if (!$message) return;
        
        $contactName = $message['lead_name'] ?? $message['tenant_name'] ?? 'Contato';
        $oppName = $message['opportunity_name'] ?? '';
        
        // Cria evento de sistema no Inbox
        require_once __DIR__ . '/EventIngestionService.php';
        EventIngestionService::ingest([
            'event_type' => 'system.notification',
            'source_system' => 'pixelhub_scheduler',
            'payload' => [
                'type' => 'followup_response',
                'message' => "✅ {$contactName} respondeu ao follow-up" . ($oppName ? " ({$oppName})" : ''),
                'scheduled_message_id' => $messageId,
            ],
            'conversation_id' => $message['conversation_id'],
            'metadata' => [
                'notification_type' => 'followup_response',
                'priority' => 'high',
            ],
        ]);
    }
    
    /**
     * Busca mensagens sem resposta após 24h para enviar lembrete
     */
    public static function getNoResponseReminders(): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->query("
            SELECT sm.*,
                   o.name as opportunity_name,
                   l.name as lead_name,
                   t.name as tenant_name
            FROM scheduled_messages sm
            LEFT JOIN opportunities o ON sm.opportunity_id = o.id
            LEFT JOIN leads l ON sm.lead_id = l.id
            LEFT JOIN tenants t ON sm.tenant_id = t.id
            WHERE sm.status = 'sent'
            AND sm.response_detected = 0
            AND sm.reminder_sent = 0
            AND sm.sent_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY sm.sent_at ASC
            LIMIT 50
        ");
        
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Envia lembrete de sem resposta
     */
    public static function sendNoResponseReminder(int $messageId): void
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT sm.*, 
                   o.name as opportunity_name,
                   l.name as lead_name,
                   t.name as tenant_name
            FROM scheduled_messages sm
            LEFT JOIN opportunities o ON sm.opportunity_id = o.id
            LEFT JOIN leads l ON sm.lead_id = l.id
            LEFT JOIN tenants t ON sm.tenant_id = t.id
            WHERE sm.id = ?
        ");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();
        
        if (!$message) return;
        
        $contactName = $message['lead_name'] ?? $message['tenant_name'] ?? 'Contato';
        $oppName = $message['opportunity_name'] ?? '';
        
        // Cria notificação de lembrete no Inbox
        require_once __DIR__ . '/EventIngestionService.php';
        EventIngestionService::ingest([
            'event_type' => 'system.notification',
            'source_system' => 'pixelhub_scheduler',
            'payload' => [
                'type' => 'followup_no_response',
                'message' => "⏰ {$contactName} não respondeu ao follow-up" . ($oppName ? " ({$oppName})" : '') . " - Ação necessária",
                'scheduled_message_id' => $messageId,
            ],
            'conversation_id' => $message['conversation_id'],
            'metadata' => [
                'notification_type' => 'followup_no_response',
                'priority' => 'medium',
            ],
        ]);
        
        // Marca lembrete como enviado
        $updateStmt = $db->prepare("
            UPDATE scheduled_messages
            SET reminder_sent = 1,
                reminder_sent_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$messageId]);
    }
}
