<?php

namespace PixelHub\Services;

/**
 * Registry centralizado para templates de cobrança
 * 
 * Expõe os templates que já existem no código sem alterar o fluxo de envio.
 * Usado pela tela de visualização de templates.
 */
class BillingTemplateRegistry
{
    /**
     * Placeholders disponíveis nos templates
     */
    public const PLACEHOLDERS = [
        '{CLIENT_NAME}' => 'Nome do cliente',
        '{INVOICE_ID}' => 'ID da fatura',
        '{INVOICE_AMOUNT}' => 'Valor da fatura',
        '{INVOICE_DUE_DATE}' => 'Data de vencimento',
        '{INVOICE_LINK}' => 'Link da fatura (Asaas)',
        '{CHARGE_TITLE}' => 'Descrição da cobrança (com fallback)',
        '{CHARGE_TITLE_SHORT}' => 'Descrição curta (até 80 chars)',
    ];

    /**
     * Retorna todos os templates disponíveis
     * 
     * @return array
     */
    public static function getAllTemplates(): array
    {
        $templates = [];

        // Templates de WhatsApp (já existentes no WhatsAppBillingService)
        $templates = array_merge($templates, self::getWhatsAppTemplates());

        // Templates de E-mail (baseado no controller)
        $templates = array_merge($templates, self::getEmailTemplates());

        return $templates;
    }

    /**
     * Dados de exemplo para preview dos templates
     */
    private static function getExampleData(string $stage): array
    {
        $tenant = [
            'name' => 'Charles Dietrich',
            'nome_fantasia' => 'Dietrich Representações',
            'person_type' => 'pj'
        ];

        $isPending = in_array($stage, ['pre_due', 'due_day']);
        $invoice = [
            'id' => '1089',
            'amount' => 150.00,
            'due_date' => date('Y-m-d', strtotime('+3 days')),
            'status' => $isPending ? 'pending' : 'overdue',
            'invoice_url' => 'https://www.asaas.com/i/cm4ceipm53qbfzgq',
            'description' => 'Desenvolvimento Web - Alterações no site'
        ];

        return ['tenant' => $tenant, 'invoice' => $invoice];
    }

    /**
     * Templates de WhatsApp — usa WhatsAppBillingService como fonte única
     * 
     * @return array
     */
    private static function getWhatsAppTemplates(): array
    {
        $templates = [];

        foreach (WhatsAppBillingService::STAGES as $stage => $label) {
            $example = self::getExampleData($stage);
            $body = WhatsAppBillingService::buildMessageForInvoice($example['tenant'], $example['invoice'], $stage);

            $templates[] = [
                'key' => "whatsapp.{$stage}",
                'channel' => 'WhatsApp',
                'stage' => $stage,
                'label' => $label,
                'format' => 'text',
                'body' => $body,
                'placeholders' => self::PLACEHOLDERS,
            ];
        }

        return $templates;
    }

    /**
     * Monta mensagem de email para um estágio específico
     * 
     * @param array $tenant Dados do tenant
     * @param array $invoice Dados da fatura
     * @param string $stage Estágio
     * @return array ['subject' => string, 'body' => string]
     */
    public static function buildEmailForInvoice(array $tenant, array $invoice, string $stage): array
    {
        $clientName = $tenant['name'] ?? 'Cliente';
        if (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['nome_fantasia'])) {
            $clientName = $tenant['nome_fantasia'];
        } elseif (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['razao_social'])) {
            $clientName = $tenant['razao_social'];
        }

        $dueDate = $invoice['due_date'] ?? null;
        $dueDateFormatted = 'N/A';
        if ($dueDate) {
            try { $dueDateFormatted = (new \DateTime($dueDate))->format('d/m/Y'); } catch (\Exception $e) {}
        }

        $amount = 'R$ ' . number_format((float)($invoice['amount'] ?? 0), 2, ',', '.');
        $invoiceLink = $invoice['invoice_url'] ?? ('https://hub.pixel12digital.com.br/billing/view_invoice?id=' . $invoice['id']);
        $description = trim($invoice['description'] ?? '');
        $serviceDescription = !empty($description) ? $description : 'Serviço Pixel12 Digital';
        $chargeTitleShort = strlen($serviceDescription) > 80 ? substr($serviceDescription, 0, 77) . '...' : $serviceDescription;

        switch ($stage) {
            case 'pre_due':
                $subject = "[Pixel12] Lembrete: {$chargeTitleShort} — vence {$dueDateFormatted}";
                $body = "Olá {$clientName},\n\n" .
                        "Gostaríamos de lembrar que existe uma cobrança da Pixel12 Digital referente a:\n\n" .
                        "Serviço: {$serviceDescription}\n" .
                        "Vencimento: {$dueDateFormatted}\n" .
                        "Valor: {$amount}\n\n" .
                        "Para acessar a fatura e efetuar o pagamento:\n{$invoiceLink}\n\n" .
                        "Qualquer dúvida, estamos à disposição.\n\n" .
                        "Atenciosamente,\nEquipe Pixel12 Digital";
                break;

            case 'due_day':
                $subject = "[Pixel12] Sua cobrança vence hoje — {$chargeTitleShort}";
                $body = "Olá {$clientName},\n\n" .
                        "Informamos que sua cobrança da Pixel12 Digital vence hoje.\n\n" .
                        "Serviço: {$serviceDescription}\n" .
                        "Vencimento: {$dueDateFormatted}\n" .
                        "Valor: {$amount}\n\n" .
                        "Para acessar a fatura e efetuar o pagamento:\n{$invoiceLink}\n\n" .
                        "Se já realizou o pagamento, por favor desconsidere este e-mail.\n\n" .
                        "Atenciosamente,\nEquipe Pixel12 Digital";
                break;

            case 'overdue_1d':
                $subject = "[Pixel12] Cobrança vencida — {$chargeTitleShort}";
                $body = "Olá {$clientName},\n\n" .
                        "Identificamos que a cobrança abaixo venceu ontem e ainda consta em aberto:\n\n" .
                        "Serviço: {$serviceDescription}\n" .
                        "Vencimento: {$dueDateFormatted}\n" .
                        "Valor: {$amount}\n\n" .
                        "Para acessar a fatura e efetuar o pagamento:\n{$invoiceLink}\n\n" .
                        "Se já efetuou o pagamento, por favor desconsidere. Caso precise de ajuda, estamos à disposição.\n\n" .
                        "Atenciosamente,\nEquipe Pixel12 Digital";
                break;

            case 'overdue_3d':
                $subject = "[Pixel12] Cobrança em aberto — {$chargeTitleShort}";
                $body = "Olá {$clientName},\n\n" .
                        "Gostaríamos de informar que a cobrança abaixo segue em aberto:\n\n" .
                        "Serviço: {$serviceDescription}\n" .
                        "Vencimento: {$dueDateFormatted}\n" .
                        "Valor: {$amount}\n\n" .
                        "Para acessar a fatura e efetuar o pagamento:\n{$invoiceLink}\n\n" .
                        "Pedimos a gentileza de verificar a regularização para evitar qualquer impacto no serviço.\n\n" .
                        "Se já pagou, pode desconsiderar. Qualquer dúvida, estamos à disposição.\n\n" .
                        "Atenciosamente,\nEquipe Pixel12 Digital";
                break;

            case 'overdue_7d':
                $subject = "[Pixel12] Atenção: cobrança em atraso — {$chargeTitleShort}";
                $body = "Olá {$clientName},\n\n" .
                        "Identificamos que a cobrança referente ao serviço abaixo ainda está em aberto e já ultrapassou 7 dias de vencimento:\n\n" .
                        "Serviço: {$serviceDescription}\n" .
                        "Vencimento: {$dueDateFormatted}\n" .
                        "Valor: {$amount}\n\n" .
                        "Para acessar a fatura e efetuar o pagamento:\n{$invoiceLink}\n\n" .
                        "Para evitar eventual bloqueio do serviço, pedimos a gentileza de verificar a regularização.\n\n" .
                        "Caso esteja enfrentando alguma dificuldade, por favor entre em contato conosco para que possamos conversar.\n\n" .
                        "Atenciosamente,\nEquipe Pixel12 Digital";
                break;

            case 'overdue_15d':
                $subject = "[Pixel12] Urgente: cobrança em atraso há mais de 15 dias — {$chargeTitleShort}";
                $body = "Olá {$clientName},\n\n" .
                        "A cobrança abaixo permanece em aberto há mais de 15 dias:\n\n" .
                        "Serviço: {$serviceDescription}\n" .
                        "Vencimento: {$dueDateFormatted}\n" .
                        "Valor: {$amount}\n\n" .
                        "Para acessar a fatura e efetuar o pagamento:\n{$invoiceLink}\n\n" .
                        "Informamos que o serviço poderá ser suspenso caso a regularização não seja efetuada.\n\n" .
                        "Se houver qualquer dificuldade ou necessidade de negociação, estamos à disposição para conversar.\n\n" .
                        "Atenciosamente,\nEquipe Pixel12 Digital";
                break;

            default:
                $subject = "[Pixel12] Cobrança — {$chargeTitleShort}";
                $body = "Olá {$clientName},\n\n" .
                        "Existe uma cobrança da Pixel12 Digital referente a:\n\n" .
                        "Serviço: {$serviceDescription}\n" .
                        "Vencimento: {$dueDateFormatted}\n" .
                        "Valor: {$amount}\n\n" .
                        "Para acessar a fatura e efetuar o pagamento:\n{$invoiceLink}\n\n" .
                        "Qualquer dúvida, estamos à disposição.\n\n" .
                        "Atenciosamente,\nEquipe Pixel12 Digital";
                break;
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Templates de E-mail — usa buildEmailForInvoice como fonte única
     * 
     * @return array
     */
    private static function getEmailTemplates(): array
    {
        $templates = [];

        foreach (WhatsAppBillingService::STAGES as $stage => $label) {
            $example = self::getExampleData($stage);
            $email = self::buildEmailForInvoice($example['tenant'], $example['invoice'], $stage);

            $templates[] = [
                'key' => "email.{$stage}",
                'channel' => 'E-mail',
                'stage' => $stage,
                'label' => $label,
                'format' => 'text',
                'subject' => $email['subject'],
                'body' => $email['body'],
                'placeholders' => self::PLACEHOLDERS,
            ];
        }

        return $templates;
    }

    /**
     * Retorna um template específico
     * 
     * @param string $key
     * @return array|null
     */
    public static function getTemplate(string $key): ?array
    {
        $templates = self::getAllTemplates();
        
        foreach ($templates as $template) {
            if ($template['key'] === $key) {
                return $template;
            }
        }
        
        return null;
    }

    /**
     * Retorna placeholders disponíveis
     * 
     * @return array
     */
    public static function getPlaceholders(): array
    {
        return self::PLACEHOLDERS;
    }

    /**
     * Gera charge_title e charge_title_short a partir da fatura
     * 
     * @param array $invoice Dados da fatura
     * @return array ['title' => string, 'title_short' => string]
     */
    public static function generateChargeTitles(array $invoice): array
    {
        // Pega descrição do Asaas, limpa e sanitiza
        $description = trim($invoice['description'] ?? '');
        $description = preg_replace('/\s+/', ' ', $description); // remove excesso de espaços
        $description = substr($description, 0, 200); // limita tamanho
        
        // Fallback genérico
        $fallback = 'hospedagem/serviço da Pixel12 Digital';
        
        // Define charge_title
        $chargeTitle = !empty($description) ? $description : $fallback;
        
        // Define charge_title_short (até 80 chars para subject)
        $chargeTitleShort = strlen($chargeTitle) > 80 
            ? substr($chargeTitle, 0, 77) . '...' 
            : $chargeTitle;
        
        return [
            'title' => $chargeTitle,
            'title_short' => $chargeTitleShort
        ];
    }
}
