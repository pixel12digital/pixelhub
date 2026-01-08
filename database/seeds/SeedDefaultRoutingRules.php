<?php

/**
 * Seed: Regras de roteamento padrão
 */
class SeedDefaultRoutingRules
{
    public function run(PDO $db): void
    {
        $rules = [
            // WhatsApp inbound → Chat interno
            [
                'event_type' => 'whatsapp.inbound.message',
                'source_system' => 'wpp_gateway',
                'channel' => 'chat',
                'template' => null,
                'priority' => 10,
                'is_enabled' => true,
                'metadata' => json_encode(['auto_reply' => false])
            ],
            // WhatsApp delivery ack → Apenas log
            [
                'event_type' => 'whatsapp.delivery.ack',
                'source_system' => 'wpp_gateway',
                'channel' => 'none',
                'template' => null,
                'priority' => 20,
                'is_enabled' => true,
                'metadata' => null
            ],
            // WhatsApp connection update → Apenas log
            [
                'event_type' => 'whatsapp.connection.update',
                'source_system' => 'wpp_gateway',
                'channel' => 'none',
                'template' => null,
                'priority' => 20,
                'is_enabled' => true,
                'metadata' => null
            ],
            // Billing invoice overdue → WhatsApp
            [
                'event_type' => 'billing.invoice.overdue',
                'source_system' => null, // Qualquer sistema
                'channel' => 'whatsapp',
                'template' => 'overdue_7d',
                'priority' => 30,
                'is_enabled' => true,
                'metadata' => null
            ],
            // Billing invoice pre_due → WhatsApp
            [
                'event_type' => 'billing.invoice.pre_due',
                'source_system' => null,
                'channel' => 'whatsapp',
                'template' => 'pre_due',
                'priority' => 30,
                'is_enabled' => true,
                'metadata' => null
            ],
            // Asaas payment → Apenas log (não envia WhatsApp direto)
            [
                'event_type' => 'asaas.payment.*',
                'source_system' => 'asaas',
                'channel' => 'none',
                'template' => null,
                'priority' => 50,
                'is_enabled' => true,
                'metadata' => null
            ],
        ];

        $stmt = $db->prepare("
            INSERT INTO routing_rules 
            (event_type, source_system, channel, template, priority, is_enabled, metadata, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        foreach ($rules as $rule) {
            $stmt->execute([
                $rule['event_type'],
                $rule['source_system'],
                $rule['channel'],
                $rule['template'],
                $rule['priority'],
                $rule['is_enabled'] ? 1 : 0,
                $rule['metadata']
            ]);
        }
    }
}

