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
        
        // Verifica se mensagem já foi processada (enviada ou falha) - evita duplicidade
        $stmt = $db->prepare("
            SELECT id, status, sent_at 
            FROM scheduled_messages 
            WHERE id = ? AND status IN ('sent', 'failed')
        ");
        $stmt->execute([$messageId]);
        $alreadyProcessed = $stmt->fetch();
        
        if ($alreadyProcessed) {
            error_log("[ScheduledMessage] Mensagem ID {$messageId} já processada em " . $alreadyProcessed['sent_at'] . " (status: " . $alreadyProcessed['status'] . ")");
            return true; // Considera sucesso pois já foi processada
        }
        
        // Busca a mensagem
        $stmt = $db->prepare("
            SELECT sm.*, 
                   l.phone as lead_phone,
                   t.phone as tenant_phone,
                   c.id as conversation_id, c.channel_id, c.contact_external_id,
                   o.lead_id as opportunity_lead_id
            FROM scheduled_messages sm
            LEFT JOIN opportunities o ON sm.opportunity_id = o.id
            LEFT JOIN leads l ON (sm.lead_id = l.id OR (sm.lead_id IS NULL AND o.lead_id = l.id))
            LEFT JOIN tenants t ON sm.tenant_id = t.id
            LEFT JOIN conversations c ON (
                (l.id IS NOT NULL AND c.lead_id = l.id) OR
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
            // Envia via WhatsAppGatewayClient (funciona em CLI)
            require_once __DIR__ . '/../Integrations/WhatsAppGateway/WhatsAppGatewayClient.php';
            require_once __DIR__ . '/../Services/GatewaySecret.php';
            
            $client = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
            
            // Define request ID para rastreamento
            $client->setRequestId('scheduled_msg_' . $messageId . '_' . time());
            
            // Envia mensagem
            $result = $client->sendText(
                $message['channel_id'] ?? 'pixel12digital',
                $phone,
                $message['message_text'],
                ['source' => 'scheduled_message', 'message_id' => $messageId]
            );
            
            if ($result['success']) {
                // Marca como enviada
                self::markAsSent($messageId, $message['conversation_id']);
                error_log("[ScheduledMessage] Mensagem ID {$messageId} enviada com sucesso. Message ID: " . ($result['message_id'] ?? 'N/A'));
                return true;
            } else {
                // Marca como falha
                $errorMsg = $result['error'] ?? 'Erro desconhecido no gateway';
                self::markAsFailed($messageId, $errorMsg);
                error_log("[ScheduledMessage] Erro ao enviar mensagem ID {$messageId}: {$errorMsg}");
                return false;
            }
            
        } catch (\Exception $e) {
            error_log("[ScheduledMessage] Erro ao enviar mensagem ID {$messageId}: " . $e->getMessage());
            self::markAsFailed($messageId, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marca mensagem como enviada e atualiza tarefa relacionada
     */
    public static function markAsSent(int $messageId, ?int $conversationId = null): void
    {
        $db = DB::getConnection();
        
        // Busca dados da mensagem antes de atualizar
        $stmt = $db->prepare("
            SELECT sm.*, ami.id as agenda_item_id
            FROM scheduled_messages sm
            LEFT JOIN agenda_manual_items ami ON sm.agenda_item_id = ami.id
            WHERE sm.id = ?
        ");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();
        
        if (!$message) {
            error_log("[ScheduledMessage] Mensagem ID {$messageId} não encontrada");
            return;
        }
        
        // Atualiza scheduled_messages
        $stmt = $db->prepare("
            UPDATE scheduled_messages 
            SET status = 'sent', 
                sent_at = NOW(),
                conversation_id = COALESCE(?, conversation_id)
            WHERE id = ?
        ");
        $stmt->execute([$conversationId, $messageId]);
        
        // Se há agenda_item_id relacionado, atualiza a tarefa
        if ($message['agenda_item_id']) {
            self::updateRelatedTask($message['agenda_item_id'], 'completed', $messageId);
        }
        
        error_log("[ScheduledMessage] Mensagem ID {$messageId} marcada como enviada e tarefa atualizada");
    }
    
    /**
     * Atualiza tarefa relacionada (agenda_manual_items)
     */
    private static function updateRelatedTask(int $agendaItemId, string $status, int $scheduledMessageId): void
    {
        $db = DB::getConnection();
        
        // Atualiza status da tarefa
        $stmt = $db->prepare("
            UPDATE agenda_manual_items 
            SET status = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $agendaItemId]);
        
        // Registra atividade no pipeline (se houver opportunity_id)
        $stmt = $db->prepare("
            SELECT opportunity_id, lead_id, title 
            FROM agenda_manual_items 
            WHERE id = ?
        ");
        $stmt->execute([$agendaItemId]);
        $task = $stmt->fetch();
        
        if ($task && $task['opportunity_id']) {
            self::createPipelineActivity($task['opportunity_id'], $task['lead_id'], $task['title'], $status, $scheduledMessageId);
        }
    }
    
    /**
     * Cria atividade no pipeline
     */
    private static function createPipelineActivity(?int $opportunityId, ?int $leadId, string $taskTitle, string $status, int $scheduledMessageId): void
    {
        $db = DB::getConnection();
        
        // Busca conversation para registrar atividade
        $conversationId = null;
        if ($leadId) {
            $stmt = $db->prepare("
                SELECT id FROM conversations 
                WHERE lead_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$leadId]);
            $conversationId = $stmt->fetchColumn();
        }
        
        // Cria registro de atividade na estrutura correta
        $stmt = $db->prepare("
            INSERT INTO communication_events (
                event_id,
                event_type, 
                source_system,
                tenant_id,
                conversation_id,
                payload,
                metadata,
                status,
                created_at
            ) VALUES (
                ?,
                'followup_completed',
                'scheduled_messages_worker',
                ?,
                ?,
                ?,
                ?,
                'processed',
                NOW()
            )
        ");
        
        $eventId = uniqid('followup_', true);
        $payload = json_encode([
            'task_title' => $taskTitle,
            'status' => $status,
            'scheduled_message_id' => $scheduledMessageId,
            'opportunity_id' => $opportunityId,
            'lead_id' => $leadId,
            'conversation_id' => $conversationId
        ]);
        
        $metadata = json_encode([
            'source' => 'scheduled_messages_worker',
            'followup_completed' => true
        ]);
        
        // Busca tenant_id da opportunity
        $tenantId = null;
        if ($opportunityId) {
            $stmt2 = $db->prepare("SELECT tenant_id FROM opportunities WHERE id = ?");
            $stmt2->execute([$opportunityId]);
            $tenantId = $stmt2->fetchColumn();
        }
        
        $stmt->execute([
            $eventId,
            $tenantId,
            $conversationId,
            $payload,
            $metadata
        ]);
        
        error_log("[ScheduledMessage] Atividade registrada no pipeline para opportunity_id: {$opportunityId}");
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
    
    /**
     * Agenda follow-up de prospecção em 22h (dentro da janela gratuita de 24h do Meta)
     * 
     * @param int $conversationId ID da conversa
     * @param string $phone Telefone do lead
     * @param string $triggerEvent Evento que gerou o agendamento (ex: 'vou_analisar_primeiro')
     * @param array $metadata Metadados adicionais
     * @return int ID da mensagem agendada
     */
    public static function scheduleProspectingFollowup(
        int $conversationId,
        string $phone,
        string $triggerEvent = 'no_response_23h',
        array $metadata = []
    ): int {
        $db = DB::getConnection();
        
        // Calcula horário de envio: 23 horas a partir de agora (antes da janela de 24h da Meta)
        $scheduledAt = date('Y-m-d H:i:s', strtotime('+23 hours'));
        
        // Mensagem de follow-up conforme especificação
        $message = "Olá! Só passando para confirmar se você conseguiu ver a mensagem que enviei sobre a estrutura que ajuda corretores a captar interessados em imóveis pelo WhatsApp.\n\nSe quiser, posso te mostrar rapidamente como funciona.\n\nhttps://imobsites.com.br/";
        
        // Busca lead_id da conversa
        $stmt = $db->prepare("SELECT lead_id FROM conversations WHERE id = ? LIMIT 1");
        $stmt->execute([$conversationId]);
        $leadId = $stmt->fetchColumn();
        
        // Botões para o follow-up (mesmos do template inicial)
        $buttons = [
            ['id' => 'btn_quero_conhecer', 'text' => 'Quero conhecer'],
            ['id' => 'btn_sem_interesse', 'text' => 'Sem interesse']
        ];
        
        // Cria mensagem agendada com botões
        $stmt = $db->prepare("
            INSERT INTO scheduled_messages (
                conversation_id, lead_id, phone,
                message_type, message_content, template_params,
                scheduled_at, status, trigger_event, metadata,
                created_at, updated_at
            ) VALUES (?, ?, ?, 'text', ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $conversationId,
            $leadId,
            $phone,
            $message,
            json_encode(['buttons' => $buttons]),
            $scheduledAt,
            $triggerEvent,
            json_encode($metadata)
        ]);
        
        $messageId = (int)$db->lastInsertId();
        
        error_log("[ScheduledMessage] Follow-up de prospecção agendado para {$scheduledAt} (ID: {$messageId}, conversa: {$conversationId})");
        
        return $messageId;
    }
    
    /**
     * Cancela follow-up agendado se lead responder antes
     * 
     * @param int $conversationId ID da conversa
     * @param string $triggerEvent Tipo de follow-up a cancelar
     */
    public static function cancelProspectingFollowup(int $conversationId, string $triggerEvent = 'no_response_23h'): void
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE scheduled_messages 
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE conversation_id = ?
            AND trigger_event = ?
            AND status = 'pending'
        ");
        
        $stmt->execute([$conversationId, $triggerEvent]);
        
        $cancelledCount = $stmt->rowCount();
        
        if ($cancelledCount > 0) {
            error_log("[ScheduledMessage] {$cancelledCount} follow-up(s) cancelado(s) para conversa {$conversationId} (lead respondeu)");
        }
    }
}
