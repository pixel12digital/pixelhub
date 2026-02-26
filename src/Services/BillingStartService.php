<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PDO;

/**
 * Serviço de Mensagem de Start ao Ativar Cobrança Automática
 * 
 * PROTEÇÃO ANTI-DUPLICAÇÃO EM 5 CAMADAS:
 * 1. Verifica billing_started_at (já foi ativado antes?)
 * 2. Verifica billing_start_messages existente (já tem mensagem pendente?)
 * 3. UNIQUE constraint no banco (garantia de integridade)
 * 4. Transaction com lock (evita race condition)
 * 5. Validação de faturas (não gera se não houver faturas)
 */
class BillingStartService
{
    /**
     * Gera mensagem de start ao ativar cobrança automática
     * 
     * IMPORTANTE: Este método tem múltiplas proteções anti-duplicação.
     * Só gera mensagem se:
     * - Tenant nunca teve start antes (billing_started_at IS NULL)
     * - Não existe mensagem de start pendente/aprovada
     * - Existem faturas pendentes/vencidas
     * 
     * @param int $tenantId
     * @return array ['success' => bool, 'message' => string, 'start_message_id' => int|null]
     */
    public static function generateStartMessage(int $tenantId): array
    {
        $db = DB::getConnection();
        
        try {
            // Inicia transação com lock para evitar race condition
            $db->beginTransaction();
            
            // ═══ PROTEÇÃO 1: Verifica se já teve start antes ═══
            $stmt = $db->prepare("
                SELECT billing_started_at, billing_auto_send, billing_auto_channel, name
                FROM tenants
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tenant) {
                $db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Tenant não encontrado',
                    'start_message_id' => null,
                ];
            }
            
            if ($tenant['billing_started_at'] !== null) {
                $db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Cobrança automática já foi iniciada anteriormente em ' . 
                                 date('d/m/Y H:i', strtotime($tenant['billing_started_at'])),
                    'start_message_id' => null,
                    'already_started' => true,
                ];
            }
            
            // ═══ PROTEÇÃO 2: Verifica se já existe mensagem de start ═══
            $stmt = $db->prepare("
                SELECT id, status
                FROM billing_start_messages
                WHERE tenant_id = ?
                  AND is_start_message = 1
                  AND status IN ('pending', 'approved')
            ");
            $stmt->execute([$tenantId]);
            $existingMessage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingMessage) {
                $db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Já existe uma mensagem de start pendente (ID: ' . $existingMessage['id'] . ')',
                    'start_message_id' => (int) $existingMessage['id'],
                    'already_exists' => true,
                ];
            }
            
            // ═══ Analisa situação financeira usando método do assistente IA ═══
            $billingContext = AISuggestReplyService::analyzeBillingContext($tenantId);
            
            // ═══ PROTEÇÃO 5: Valida se existem faturas ═══
            if (empty($billingContext['invoices_data']['invoices'])) {
                $db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Nenhuma fatura pendente ou vencida encontrada. Mensagem de start não é necessária.',
                    'start_message_id' => null,
                    'no_invoices' => true,
                ];
            }
            
            // ═══ Gera mensagem usando IA ═══
            $messageText = self::generateMessageText($billingContext, $tenant['name']);
            
            // ═══ Extrai IDs das faturas ═══
            $invoiceIds = array_column($billingContext['invoices_data']['invoices'], 'id');
            
            // ═══ Insere mensagem de start (UNIQUE constraint garante não duplicar) ═══
            $stmt = $db->prepare("
                INSERT INTO billing_start_messages (
                    tenant_id,
                    total_amount,
                    overdue_count,
                    pending_count,
                    invoice_ids,
                    message_type,
                    message_text,
                    ai_context,
                    status,
                    channel,
                    is_start_message
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 1)
            ");
            
            $stmt->execute([
                $tenantId,
                $billingContext['invoices_data']['total_amount'],
                $billingContext['invoices_data']['overdue_count'],
                $billingContext['invoices_data']['pending_count'],
                json_encode($invoiceIds),
                $billingContext['objective'],
                $messageText,
                $billingContext['context'],
                $tenant['billing_auto_channel'],
            ]);
            
            $startMessageId = (int) $db->lastInsertId();
            
            // ═══ Marca billing_started_at no tenant ═══
            $stmt = $db->prepare("
                UPDATE tenants
                SET billing_started_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$tenantId]);
            
            $db->commit();
            
            return [
                'success' => true,
                'message' => 'Mensagem de start gerada com sucesso! Aguardando aprovação.',
                'start_message_id' => $startMessageId,
                'message_type' => $billingContext['objective'],
                'total_amount' => $billingContext['invoices_data']['total_amount'],
                'overdue_count' => $billingContext['invoices_data']['overdue_count'],
                'pending_count' => $billingContext['invoices_data']['pending_count'],
            ];
            
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            // ═══ PROTEÇÃO 3: UNIQUE constraint violation ═══
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'unique_start_per_tenant') !== false) {
                return [
                    'success' => false,
                    'message' => 'Mensagem de start já existe para este tenant (proteção anti-duplicação)',
                    'start_message_id' => null,
                    'duplicate_prevented' => true,
                ];
            }
            
            error_log('[BILLING_START] Erro ao gerar mensagem: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar mensagem: ' . $e->getMessage(),
                'start_message_id' => null,
            ];
        }
    }
    
    /**
     * Gera texto da mensagem baseado no contexto financeiro
     * 
     * @param array $billingContext Contexto retornado por analyzeBillingContext()
     * @param string $tenantName Nome do tenant
     * @return string Texto da mensagem
     */
    private static function generateMessageText(array $billingContext, string $tenantName): string
    {
        $data = $billingContext['invoices_data'];
        $objective = $billingContext['objective'];
        
        $totalFormatted = 'R$ ' . number_format($data['total_amount'], 2, ',', '.');
        
        // Monta resumo de serviços
        $servicesText = [];
        foreach ($data['services_summary'] as $serviceName => $serviceData) {
            $servicesText[] = "• {$serviceData['count']}x {$serviceName}";
        }
        $servicesLine = implode("\n", $servicesText);
        
        // Gera mensagem baseada na gravidade
        if ($objective === 'billing_critical') {
            // 3+ faturas vencidas - RENEGOCIAÇÃO
            return "Olá! 👋\n\n" .
                   "Identificamos {$data['overdue_count']} faturas vencidas em sua conta, totalizando {$totalFormatted}.\n\n" .
                   "Serviços em aberto:\n{$servicesLine}\n\n" .
                   "⚠️ Precisamos regularizar esta situação em até 48 horas.\n\n" .
                   "Após este prazo, os serviços serão suspensos e haverá custos de reativação.\n\n" .
                   "Podemos conversar sobre formas de pagamento?\n\n" .
                   "Você prefere regularizar ou que a gente suspenda os serviços?";
            
        } elseif ($objective === 'billing_collection') {
            // 1-2 faturas vencidas - COBRANÇA
            $message = "Olá! 👋\n\n" .
                      "Identificamos {$data['overdue_count']} fatura(s) vencida(s)";
            
            if ($data['pending_count'] > 0) {
                $message .= " e {$data['pending_count']} a vencer";
            }
            
            $message .= ", totalizando {$totalFormatted}.\n\n" .
                       "Serviços:\n{$servicesLine}\n\n" .
                       "Por favor, regularize o quanto antes para evitar suspensão dos serviços.\n\n" .
                       "Os links de pagamento estão disponíveis no seu painel ou posso enviar aqui. Precisa de ajuda?";
            
            return $message;
            
        } else {
            // Apenas faturas a vencer - LEMBRETE
            return "Olá! 👋\n\n" .
                   "Lembrete: você possui {$data['pending_count']} fatura(s) a vencer, totalizando {$totalFormatted}.\n\n" .
                   "Serviços:\n{$servicesLine}\n\n" .
                   "Os links de pagamento estão disponíveis no seu painel. Precisa de ajuda?";
        }
    }
    
    /**
     * Busca mensagem de start pendente de aprovação
     * 
     * @param int $tenantId
     * @return array|null
     */
    public static function getPendingStartMessage(int $tenantId): ?array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT *
            FROM billing_start_messages
            WHERE tenant_id = ?
              AND is_start_message = 1
              AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($message) {
            $message['invoice_ids'] = json_decode($message['invoice_ids'], true);
        }
        
        return $message ?: null;
    }
    
    /**
     * Aprova mensagem de start (muda status para approved)
     * 
     * @param int $startMessageId
     * @param int $userId ID do usuário que aprovou
     * @return bool
     */
    public static function approveStartMessage(int $startMessageId, int $userId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE billing_start_messages
            SET status = 'approved',
                sent_by = ?
            WHERE id = ?
              AND status = 'pending'
        ");
        
        return $stmt->execute([$userId, $startMessageId]);
    }
    
    /**
     * Cancela mensagem de start
     * 
     * @param int $startMessageId
     * @return bool
     */
    public static function cancelStartMessage(int $startMessageId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE billing_start_messages
            SET status = 'cancelled'
            WHERE id = ?
              AND status IN ('pending', 'approved')
        ");
        
        return $stmt->execute([$startMessageId]);
    }
    
    /**
     * Marca mensagem como enviada
     * 
     * @param int $startMessageId
     * @param string|null $gatewayMessageId
     * @return bool
     */
    public static function markAsSent(int $startMessageId, ?string $gatewayMessageId = null): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE billing_start_messages
            SET status = 'sent',
                sent_at = NOW(),
                gateway_message_id = ?
            WHERE id = ?
              AND status = 'approved'
        ");
        
        return $stmt->execute([$gatewayMessageId, $startMessageId]);
    }
}
