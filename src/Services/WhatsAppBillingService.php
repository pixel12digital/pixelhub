<?php

namespace PixelHub\Services;

use PDO;

/**
 * Service para gerenciar cobranças via WhatsApp Web
 * 
 * Responsável por normalizar telefones, sugerir estágios de cobrança,
 * montar mensagens e preparar notificações.
 */
class WhatsAppBillingService
{
    /**
     * Normaliza um telefone para uso no wa.me
     * 
     * - Remove tudo que não for dígito
     * - Se tiver 11 dígitos e não começar com 55, prefixa 55
     * - Se tiver 10 dígitos (telefone fixo), também prefixa 55
     * 
     * @param string|null $rawPhone Telefone original
     * @return string|null Telefone normalizado (ex: 5511999999999) ou null se inválido
     */
    public static function normalizePhone(?string $rawPhone): ?string
    {
        if (empty($rawPhone)) {
            return null;
        }

        // Remove tudo que não for dígito
        $digits = preg_replace('/[^0-9]/', '', $rawPhone);

        if (empty($digits)) {
            return null;
        }

        // Se já começa com 55 e tem pelo menos 12 dígitos, retorna como está
        if (strlen($digits) >= 12 && substr($digits, 0, 2) === '55') {
            return $digits;
        }

        // Se tem 11 dígitos (celular BR sem DDI), adiciona 55
        if (strlen($digits) === 11) {
            return '55' . $digits;
        }

        // Se tem 10 dígitos (fixo BR sem DDI), adiciona 55
        if (strlen($digits) === 10) {
            return '55' . $digits;
        }

        // Se tem 13 dígitos e começa com 55, retorna como está
        if (strlen($digits) === 13 && substr($digits, 0, 2) === '55') {
            return $digits;
        }

        // Caso não se encaixe em nenhum padrão conhecido, retorna os dígitos limpos
        // (pode ser um número internacional já formatado)
        return $digits;
    }

    /**
     * Sugere o estágio/template de cobrança baseado na fatura
     * 
     * @param array $invoice Dados da fatura (deve ter due_date, status)
     * @return array ['stage' => string, 'label' => string, 'days_overdue' => int]
     */
    public static function suggestStageForInvoice(array $invoice): array
    {
        $dueDate = $invoice['due_date'] ?? null;
        $status = $invoice['status'] ?? 'pending';
        $daysOverdue = 0;

        if ($dueDate) {
            try {
                $due = new \DateTime($dueDate);
                $now = new \DateTime();
                $diff = $now->diff($due);
                $daysOverdue = (int) $diff->format('%r%a'); // negativo se ainda não venceu
            } catch (\Exception $e) {
                error_log("Erro ao calcular dias de atraso: " . $e->getMessage());
            }
        }

        // Se status é pending e ainda não venceu (ou vence hoje)
        if ($status === 'pending' && $daysOverdue >= 0) {
            return [
                'stage' => 'pre_due',
                'label' => 'Lembrete pré-vencimento',
                'days_overdue' => 0
            ];
        }

        // Se status é overdue e tem entre 1 e 5 dias de atraso
        if ($status === 'overdue' && $daysOverdue >= 1 && $daysOverdue <= 5) {
            return [
                'stage' => 'overdue_3d',
                'label' => 'Cobrança 1 (vencido +3d)',
                'days_overdue' => $daysOverdue
            ];
        }

        // Se status é overdue e tem 6 ou mais dias de atraso
        if ($status === 'overdue' && $daysOverdue >= 6) {
            return [
                'stage' => 'overdue_7d',
                'label' => 'Cobrança 2 (vencido +7d)',
                'days_overdue' => $daysOverdue
            ];
        }

        // Fallback: se está pending mas já venceu, trata como overdue_3d
        if ($status === 'pending' && $daysOverdue < 0) {
            return [
                'stage' => 'overdue_3d',
                'label' => 'Cobrança 1 (vencido)',
                'days_overdue' => abs($daysOverdue)
            ];
        }

        // Default
        return [
            'stage' => 'pre_due',
            'label' => 'Lembrete pré-vencimento',
            'days_overdue' => 0
        ];
    }

    /**
     * Monta a mensagem padrão de acordo com o estágio
     * 
     * @param array $tenant Dados do tenant
     * @param array $invoice Dados da fatura
     * @param string $stage Estágio (pre_due, overdue_3d, overdue_7d)
     * @return string Mensagem formatada
     */
    public static function buildMessageForInvoice(array $tenant, array $invoice, string $stage): string
    {
        // Nome do cliente
        $clientName = $tenant['name'] ?? 'Cliente';
        if (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['nome_fantasia'])) {
            $clientName = $tenant['nome_fantasia'];
        } elseif (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['razao_social'])) {
            $clientName = $tenant['razao_social'];
        }

        // Data de vencimento formatada
        $dueDate = $invoice['due_date'] ?? null;
        $dueDateFormatted = 'N/A';
        if ($dueDate) {
            try {
                $date = new \DateTime($dueDate);
                $dueDateFormatted = $date->format('d/m/Y');
            } catch (\Exception $e) {
                // mantém N/A
            }
        }

        // Valor formatado
        $amount = (float) ($invoice['amount'] ?? 0);
        $amountFormatted = 'R$ ' . number_format($amount, 2, ',', '.');

        // Monta mensagem baseada no estágio
        switch ($stage) {
            case 'pre_due':
                return "Oi {$clientName}, tudo bem? 😊\n\n" .
                       "Passando para lembrar que sua hospedagem/serviço da Pixel12 Digital vence em {$dueDateFormatted}, no valor de {$amountFormatted}.\n\n" .
                       "Qualquer dúvida ou se precisar de ajuda com o pagamento, me avisa por aqui.";

            case 'overdue_3d':
                return "Oi {$clientName}, tudo bem?\n\n" .
                       "Notei que sua fatura da Pixel12 Digital com vencimento em {$dueDateFormatted}, no valor de {$amountFormatted}, ainda consta em aberto.\n\n" .
                       "Consegue verificar pra mim, por favor? Se já tiver pago, pode desconsiderar essa mensagem.";

            case 'overdue_7d':
                return "Oi {$clientName}, tudo bem?\n\n" .
                       "Sua fatura da Pixel12 Digital (venc. {$dueDateFormatted}, valor {$amountFormatted}) ainda está em aberto há alguns dias.\n\n" .
                       "Precisa de alguma ajuda ou quer combinar uma forma de pagamento? Me avisa pra gente evitar qualquer bloqueio do serviço.";

            default:
                return "Oi {$clientName}, tudo bem?\n\n" .
                       "Sua fatura da Pixel12 Digital vence em {$dueDateFormatted}, no valor de {$amountFormatted}.\n\n" .
                       "Qualquer dúvida, me avisa por aqui.";
        }
    }

    /**
     * Prepara/cria registro em billing_notifications
     * 
     * @param PDO $db Conexão com banco
     * @param array $tenant Dados do tenant
     * @param array $invoice Dados da fatura
     * @param string $stage Estágio (pre_due, overdue_3d, overdue_7d)
     * @param string $message Mensagem preparada
     * @return int ID da notificação criada
     */
    public static function prepareNotificationForInvoice(
        PDO $db,
        array $tenant,
        array $invoice,
        string $stage,
        string $message
    ): int {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $invoiceId = !empty($invoice['id']) ? (int) $invoice['id'] : null;
        $phoneRaw = $tenant['phone'] ?? null;
        $phoneNormalized = self::normalizePhone($phoneRaw);

        // Verifica se já existe notificação enviada para esta fatura neste estágio
        if ($invoiceId) {
            $stmt = $db->prepare("
                SELECT id FROM billing_notifications
                WHERE invoice_id = ? AND template = ? AND status = 'sent_manual'
                LIMIT 1
            ");
            $stmt->execute([$invoiceId, $stage]);
            $existing = $stmt->fetch();
            
            // Se já existe uma enviada, cria uma nova com status prepared (permite reenvio)
            // Mas não duplica se já existe uma prepared recente (últimas 24h)
            if ($existing) {
                $stmt = $db->prepare("
                    SELECT id FROM billing_notifications
                    WHERE invoice_id = ? AND template = ? AND status = 'prepared'
                    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    LIMIT 1
                ");
                $stmt->execute([$invoiceId, $stage]);
                $recentPrepared = $stmt->fetch();
                
                if ($recentPrepared) {
                    // Atualiza a existente
                    $stmt = $db->prepare("
                        UPDATE billing_notifications
                        SET message = ?, phone_raw = ?, phone_normalized = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$message, $phoneRaw, $phoneNormalized, $recentPrepared['id']]);
                    return (int) $recentPrepared['id'];
                }
            }
        }

        // Cria nova notificação
        $stmt = $db->prepare("
            INSERT INTO billing_notifications
            (tenant_id, invoice_id, channel, template, status, message, phone_raw, phone_normalized, created_at, updated_at)
            VALUES (?, ?, 'whatsapp_web', ?, 'prepared', ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $tenantId,
            $invoiceId,
            $stage,
            $message,
            $phoneRaw,
            $phoneNormalized
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Monta mensagem única com todas as faturas pendentes/vencidas do cliente
     * 
     * @param array $tenant Dados do tenant
     * @param array $invoices Array de faturas (pending/overdue, não deletadas)
     * @return string Mensagem formatada
     */
    public static function buildReminderMessageForTenant(array $tenant, array $invoices): string
    {
        // Nome do cliente
        $clientName = $tenant['name'] ?? 'Cliente';
        if (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['nome_fantasia'])) {
            $clientName = $tenant['nome_fantasia'];
        } elseif (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['razao_social'])) {
            $clientName = $tenant['razao_social'];
        }

        // Saudação
        $message = "Olá {$clientName}, tudo bem? 😊\n\n";
        
        // Separa faturas em três grupos: vencidas, vence hoje, e a vencer
        $overdueInvoices = [];
        $dueTodayInvoices = [];
        $upcomingInvoices = [];
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        foreach ($invoices as $invoice) {
            $status = $invoice['status'] ?? 'pending';
            $dueDate = $invoice['due_date'] ?? null;
            
            $isOverdue = false;
            $isDueToday = false;
            
            if ($status === 'overdue') {
                $isOverdue = true;
            } elseif ($dueDate) {
                try {
                    $due = new \DateTime($dueDate);
                    $due->setTime(0, 0, 0);
                    if ($due < $today) {
                        $isOverdue = true;
                    } elseif ($due == $today) {
                        $isDueToday = true;
                    }
                } catch (\Exception $e) {
                    $isOverdue = ($status === 'overdue');
                }
            }
            
            if ($isOverdue) {
                $overdueInvoices[] = $invoice;
            } elseif ($isDueToday) {
                $dueTodayInvoices[] = $invoice;
            } else {
                $upcomingInvoices[] = $invoice;
            }
        }
        
        // Seção de faturas em atraso
        if (count($overdueInvoices) > 0) {
            $overdueCount = count($overdueInvoices);
            $message .= "Você possui {$overdueCount} fatura(s) em atraso:\n\n";
            
            foreach ($overdueInvoices as $invoice) {
                $dueDate = $invoice['due_date'] ?? null;
                $dueDateFormatted = 'N/A';
                if ($dueDate) {
                    try {
                        $date = new \DateTime($dueDate);
                        $dueDateFormatted = $date->format('d/m/Y');
                    } catch (\Exception $e) {
                        // mantém N/A
                    }
                }
                
                $amount = (float) ($invoice['amount'] ?? 0);
                $amountFormatted = 'R$ ' . number_format($amount, 2, ',', '.');
                
                $description = $invoice['description'] ?? 'Cobrança';
                $invoiceUrl = $invoice['invoice_url'] ?? '';
                
                $message .= "• Vencida – Vencimento {$dueDateFormatted} – {$amountFormatted} – {$description}";
                
                if ($invoiceUrl) {
                    $message .= "\n  Link: {$invoiceUrl}";
                }
                
                $message .= "\n\n";
            }
        }
        
        // Seção de faturas que vencem HOJE
        if (count($dueTodayInvoices) > 0) {
            $hasPrevious = count($overdueInvoices) > 0;
            if ($hasPrevious) {
                $message .= "Além disso, ";
            }
            if (count($dueTodayInvoices) === 1) {
                $message .= ($hasPrevious ? "s" : "S") . "ua fatura vence *hoje*! 📌\n\n";
            } else {
                $message .= ($hasPrevious ? "v" : "V") . "ocê tem " . count($dueTodayInvoices) . " faturas vencendo *hoje*! 📌\n\n";
            }
            
            foreach ($dueTodayInvoices as $invoice) {
                $amount = (float) ($invoice['amount'] ?? 0);
                $amountFormatted = 'R$ ' . number_format($amount, 2, ',', '.');
                $description = $invoice['description'] ?? 'Cobrança';
                $invoiceUrl = $invoice['invoice_url'] ?? '';
                
                $message .= "• Hoje – {$amountFormatted} – {$description}";
                if ($invoiceUrl) {
                    $message .= "\n  Link: {$invoiceUrl}";
                }
                $message .= "\n\n";
            }
        }
        
        // Seção de próximas faturas a vencer
        if (count($upcomingInvoices) > 0) {
            $hasPrevious = count($overdueInvoices) > 0 || count($dueTodayInvoices) > 0;
            if ($hasPrevious) {
                if (count($upcomingInvoices) === 1) {
                    $message .= "Além disso, sua próxima fatura a vencer é:\n\n";
                } else {
                    $message .= "Além disso, suas próximas faturas a vencer são:\n\n";
                }
            } else {
                if (count($upcomingInvoices) === 1) {
                    $message .= "Sua próxima fatura a vencer é:\n\n";
                } else {
                    $message .= "Suas próximas faturas a vencer são:\n\n";
                }
            }
            
            foreach ($upcomingInvoices as $invoice) {
                $dueDate = $invoice['due_date'] ?? null;
                $dueDateFormatted = 'N/A';
                if ($dueDate) {
                    try {
                        $date = new \DateTime($dueDate);
                        $dueDateFormatted = $date->format('d/m/Y');
                    } catch (\Exception $e) {
                        // mantém N/A
                    }
                }
                
                $amount = (float) ($invoice['amount'] ?? 0);
                $amountFormatted = 'R$ ' . number_format($amount, 2, ',', '.');
                
                $description = $invoice['description'] ?? 'Cobrança';
                $invoiceUrl = $invoice['invoice_url'] ?? '';
                
                $message .= "• Vencimento {$dueDateFormatted} – {$amountFormatted} – {$description}";
                
                if ($invoiceUrl) {
                    $message .= "\n  Link: {$invoiceUrl}";
                }
                
                $message .= "\n\n";
            }
        }
        
        // Parágrafo final
        if (count($overdueInvoices) > 0) {
            $message .= "O pagamento das faturas em atraso mantém seus serviços ativos normalmente.\n\n";
        }
        $message .= "Qualquer dúvida, é só responder por aqui que eu te ajudo. 👍";
        
        return $message;
    }
}

