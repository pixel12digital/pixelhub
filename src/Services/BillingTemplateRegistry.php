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
     * Templates de WhatsApp
     * 
     * @return array
     */
    private static function getWhatsAppTemplates(): array
    {
        $templates = [];

        $stages = [
            'pre_due' => 'Pré-vencimento',
            'overdue_3d' => 'Cobrança 1 (vencido +3d)',
            'overdue_7d' => 'Cobrança 2 (vencido +7d)',
            'default' => 'Cobrança Padrão',
        ];

        foreach ($stages as $stage => $label) {
            // Dados de exemplo para renderizar o template
            $exampleTenant = [
                'name' => 'Charles Dietrich',
                'nome_fantasia' => 'Dietrich Representações',
                'person_type' => 'pj'
            ];

            $exampleInvoice = [
                'id' => '1089',
                'amount' => 150.00,
                'due_date' => date('Y-m-d', strtotime('+3 days')),
                'status' => $stage === 'pre_due' ? 'pending' : 'overdue',
                'invoice_url' => 'https://www.asaas.com/i/cm4ceipm53qbfzgq',
                'description' => 'Mensalidade Fevereiro - Plano Professional'
            ];

            $body = WhatsAppBillingService::buildMessageForInvoice($exampleTenant, $exampleInvoice, $stage);

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
     * Templates de E-mail
     * 
     * @return array
     */
    private static function getEmailTemplates(): array
    {
        $templates = [];

        $stages = [
            'pre_due' => 'Pré-vencimento',
            'overdue_3d' => 'Cobrança 1 (vencido +3d)',
            'overdue_7d' => 'Cobrança 2 (vencido +7d)',
            'default' => 'Cobrança Padrão',
        ];

        foreach ($stages as $stage => $label) {
            // Dados de exemplo para renderizar o template
            $exampleTenant = [
                'name' => 'Charles Dietrich',
                'nome_fantasia' => 'Dietrich Representações',
                'person_type' => 'pj'
            ];

            $exampleInvoice = [
                'id' => '1089',
                'amount' => 150.00,
                'due_date' => date('Y-m-d', strtotime('+3 days')),
                'status' => $stage === 'pre_due' ? 'pending' : 'overdue',
                'invoice_url' => 'https://www.asaas.com/i/cm4ceipm53qbfzgq',
                'description' => 'Mensalidade Fevereiro - Plano Professional'
            ];

            // Simula o buildEmailMessage do controller
            $tenantName = $exampleTenant['nome_fantasia'] ?? $exampleTenant['name'];
            $amount = number_format($exampleInvoice['amount'], 2, ',', '.');
            $dueDate = (new \DateTime($exampleInvoice['due_date']))->format('d/m/Y');
            
            // Gera charge_title
            $chargeTitles = self::generateChargeTitles($exampleInvoice);
            $chargeTitle = $chargeTitles['title'];
            $chargeTitleShort = $chargeTitles['title_short'];
            
            $subject = "[Pixel12] {$chargeTitleShort} — vence {$dueDate}";
            
            $body = "Olá {$tenantName},\n\n";
            $body .= "Gostaríamos de lembrar sobre sua fatura:\n\n";
            $body .= "Descrição: {$chargeTitle}\n";
            $body .= "Fatura: #{$exampleInvoice['id']}\n";
            $body .= "Valor: R$ {$amount}\n";
            $body .= "Vencimento: {$dueDate}\n";
            $body .= "Status: " . ($exampleInvoice['status'] === 'paid' ? 'Paga' : 'Pendente') . "\n\n";
            
            if ($exampleInvoice['status'] !== 'paid') {
                $body .= "Por favor, regularize o pagamento para evitar juros.\n\n";
                $invoiceLink = $exampleInvoice['invoice_url'] ?? "https://hub.pixel12digital.com.br/billing/view_invoice?id={$exampleInvoice['id']}";
                $body .= "Para acessar a fatura: {$invoiceLink}\n\n";
            }
            
            $body .= "Atenciosamente,\n";
            $body .= "Equipe Pixel12 Digital";

            $templates[] = [
                'key' => "email.{$stage}",
                'channel' => 'E-mail',
                'stage' => $stage,
                'label' => $label,
                'format' => 'text',
                'subject' => $subject,
                'body' => $body,
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
