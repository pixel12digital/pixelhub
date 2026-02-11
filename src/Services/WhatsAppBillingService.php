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
     * Todos os estágios de cobrança disponíveis (fonte de verdade)
     */
    public const STAGES = [
        'pre_due'     => 'Pré-vencimento',
        'due_day'     => 'Dia do vencimento',
        'overdue_1d'  => 'Vencido +1 dia',
        'overdue_3d'  => 'Vencido +3 dias',
        'overdue_7d'  => 'Vencido +7 dias',
        'overdue_15d' => 'Vencido +15 dias',
    ];

    /**
     * Sugere o estágio/template de cobrança baseado na fatura
     * 
     * Estágios:
     *   pre_due     → antes do vencimento
     *   due_day     → dia do vencimento
     *   overdue_1d  → 1 dia após vencimento
     *   overdue_3d  → 2-5 dias após vencimento
     *   overdue_7d  → 6-14 dias após vencimento
     *   overdue_15d → 15+ dias após vencimento
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
                $due->setTime(0, 0, 0);
                $now = new \DateTime();
                $now->setTime(0, 0, 0);
                $diff = $now->diff($due);
                // Positivo = vencido há N dias, negativo = falta N dias, 0 = hoje
                $daysOverdue = (int) $diff->format('%r%a') * -1;
            } catch (\Exception $e) {
                error_log("Erro ao calcular dias de atraso: " . $e->getMessage());
            }
        }

        // Vence hoje
        if ($daysOverdue === 0 && ($status === 'pending' || $status === 'overdue')) {
            return [
                'stage' => 'due_day',
                'label' => self::STAGES['due_day'],
                'days_overdue' => 0
            ];
        }

        // Ainda não venceu (daysOverdue negativo = faltam dias)
        if ($daysOverdue < 0) {
            return [
                'stage' => 'pre_due',
                'label' => self::STAGES['pre_due'],
                'days_overdue' => 0
            ];
        }

        // Vencido há 1 dia
        if ($daysOverdue === 1) {
            return [
                'stage' => 'overdue_1d',
                'label' => self::STAGES['overdue_1d'],
                'days_overdue' => $daysOverdue
            ];
        }

        // Vencido há 2-5 dias
        if ($daysOverdue >= 2 && $daysOverdue <= 5) {
            return [
                'stage' => 'overdue_3d',
                'label' => self::STAGES['overdue_3d'],
                'days_overdue' => $daysOverdue
            ];
        }

        // Vencido há 6-14 dias
        if ($daysOverdue >= 6 && $daysOverdue <= 14) {
            return [
                'stage' => 'overdue_7d',
                'label' => self::STAGES['overdue_7d'],
                'days_overdue' => $daysOverdue
            ];
        }

        // Vencido há 15+ dias
        if ($daysOverdue >= 15) {
            return [
                'stage' => 'overdue_15d',
                'label' => self::STAGES['overdue_15d'],
                'days_overdue' => $daysOverdue
            ];
        }

        // Fallback
        return [
            'stage' => 'pre_due',
            'label' => self::STAGES['pre_due'],
            'days_overdue' => 0
        ];
    }

    /**
     * Monta a mensagem de cobrança de acordo com o estágio
     * 
     * Estrutura padrão de todas as mensagens:
     *   1. Saudação
     *   2. Contexto (cobrança da Pixel12 Digital)
     *   3. Serviço (descrição limpa, separada)
     *   4. Valor e vencimento
     *   5. Link de pagamento
     *   6. Encerramento
     * 
     * @param array $tenant Dados do tenant
     * @param array $invoice Dados da fatura
     * @param string $stage Estágio (pre_due, due_day, overdue_1d, overdue_3d, overdue_7d, overdue_15d)
     * @return string Mensagem formatada
     */
    public static function buildMessageForInvoice(array $tenant, array $invoice, string $stage): string
    {
        // ─── Dados do cliente ───
        $clientName = $tenant['name'] ?? 'Cliente';
        if (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['nome_fantasia'])) {
            $clientName = $tenant['nome_fantasia'];
        } elseif (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['razao_social'])) {
            $clientName = $tenant['razao_social'];
        }

        // ─── Data de vencimento ───
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

        // ─── Valor ───
        $amount = (float) ($invoice['amount'] ?? 0);
        $amountFormatted = 'R$ ' . number_format($amount, 2, ',', '.');

        // ─── Link da fatura ───
        $invoiceLink = $invoice['invoice_url'] ?? ('https://hub.pixel12digital.com.br/billing/view_invoice?id=' . $invoice['id']);

        // ─── Descrição do serviço (limpa e curta) ───
        $description = trim($invoice['description'] ?? '');
        $serviceDescription = !empty($description) ? $description : 'Serviço Pixel12 Digital';

        // ─── Monta mensagem por estágio ───
        switch ($stage) {
            case 'pre_due':
                return "Oi {$clientName}, tudo bem?\n\n" .
                       "Passando para lembrar que existe uma cobrança da *Pixel12 Digital* referente a:\n" .
                       "{$serviceDescription}\n\n" .
                       "*Vencimento:* {$dueDateFormatted}\n" .
                       "*Valor:* {$amountFormatted}\n\n" .
                       "Link para pagamento:\n{$invoiceLink}\n\n" .
                       "Qualquer dúvida, fico à disposição.";

            case 'due_day':
                return "Oi {$clientName}, tudo bem?\n\n" .
                       "Sua cobrança da *Pixel12 Digital* vence *hoje*.\n\n" .
                       "*Serviço:* {$serviceDescription}\n" .
                       "*Vencimento:* {$dueDateFormatted}\n" .
                       "*Valor:* {$amountFormatted}\n\n" .
                       "Link para pagamento:\n{$invoiceLink}\n\n" .
                       "Se já realizou o pagamento, pode desconsiderar esta mensagem.";

            case 'overdue_1d':
                return "Oi {$clientName}, tudo bem?\n\n" .
                       "Identificamos que a cobrança abaixo venceu ontem e ainda consta em aberto:\n\n" .
                       "*Serviço:* {$serviceDescription}\n" .
                       "*Vencimento:* {$dueDateFormatted}\n" .
                       "*Valor:* {$amountFormatted}\n\n" .
                       "Link para pagamento:\n{$invoiceLink}\n\n" .
                       "Se já efetuou o pagamento, por favor desconsidere. Caso precise de ajuda, estamos à disposição.";

            case 'overdue_3d':
                return "Oi {$clientName}, tudo bem?\n\n" .
                       "Gostaríamos de informar que a cobrança abaixo segue em aberto:\n\n" .
                       "*Serviço:* {$serviceDescription}\n" .
                       "*Vencimento:* {$dueDateFormatted}\n" .
                       "*Valor:* {$amountFormatted}\n\n" .
                       "Link para pagamento:\n{$invoiceLink}\n\n" .
                       "Pedimos a gentileza de verificar a regularização para evitar qualquer impacto no serviço.\n\n" .
                       "Se já pagou, pode desconsiderar. Qualquer dúvida, estamos à disposição.";

            case 'overdue_7d':
                return "Oi {$clientName},\n\n" .
                       "Identificamos que a cobrança referente ao serviço abaixo ainda está em aberto e já ultrapassou 7 dias de vencimento:\n\n" .
                       "*Serviço:* {$serviceDescription}\n" .
                       "*Vencimento:* {$dueDateFormatted}\n" .
                       "*Valor:* {$amountFormatted}\n\n" .
                       "Link para pagamento:\n{$invoiceLink}\n\n" .
                       "Para evitar eventual bloqueio do serviço, pedimos a gentileza de verificar a regularização.\n\n" .
                       "Caso esteja enfrentando alguma dificuldade, por favor entre em contato conosco para que possamos conversar.";

            case 'overdue_15d':
                return "Oi {$clientName},\n\n" .
                       "A cobrança abaixo permanece em aberto há mais de 15 dias:\n\n" .
                       "*Serviço:* {$serviceDescription}\n" .
                       "*Vencimento:* {$dueDateFormatted}\n" .
                       "*Valor:* {$amountFormatted}\n\n" .
                       "Link para pagamento:\n{$invoiceLink}\n\n" .
                       "Informamos que o serviço poderá ser suspenso caso a regularização não seja efetuada.\n\n" .
                       "Se houver qualquer dificuldade ou necessidade de negociação, estamos à disposição para conversar.";

            default:
                return "Oi {$clientName}, tudo bem?\n\n" .
                       "Existe uma cobrança da *Pixel12 Digital* referente a:\n" .
                       "{$serviceDescription}\n\n" .
                       "*Vencimento:* {$dueDateFormatted}\n" .
                       "*Valor:* {$amountFormatted}\n\n" .
                       "Link para pagamento:\n{$invoiceLink}\n\n" .
                       "Qualquer dúvida, fico à disposição.";
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

