<?php

namespace PixelHub\Services;

/**
 * Mapper para preparar dados de planos para integração com Asaas
 * 
 * Esta classe prepara os payloads que serão enviados para a API do Asaas
 * quando a integração for implementada.
 */
class AsaasPlanMapper
{
    /**
     * Constrói payload para criar uma subscription mensal no Asaas
     * 
     * @param array $plan Dados do plano (hosting_plans)
     * @param array $customer Dados do cliente (tenants com asaas_customer_id)
     * @return array Payload formatado para API de subscriptions do Asaas
     */
    public static function buildMonthlySubscriptionPayload(array $plan, array $customer): array
    {
        // Estrutura base para subscription mensal no Asaas
        return [
            'customer' => $customer['asaas_customer_id'], // ID do cliente no Asaas
            'billingType' => 'CREDIT_CARD', // ou 'BOLETO', 'PIX', etc.
            'value' => (float) $plan['amount'], // Valor mensal
            'cycle' => 'MONTHLY', // Ciclo mensal
            'description' => $plan['name'] . ' - Mensal',
            // 'nextDueDate' => date('Y-m-d', strtotime('+1 month')), // Data de vencimento
            // Outros campos conforme documentação do Asaas
        ];
    }

    /**
     * Constrói payload para criar um pagamento anual no Asaas
     * 
     * Pode ser usado para:
     * - Payment único (à vista)
     * - Installment (parcelado)
     * 
     * @param array $plan Dados do plano (hosting_plans)
     * @param array $customer Dados do cliente (tenants com asaas_customer_id)
     * @return array Payload formatado para API de payments/installments do Asaas
     */
    public static function buildYearlyPaymentPayload(array $plan, array $customer): array
    {
        // Estrutura base para pagamento anual no Asaas
        return [
            'customer' => $customer['asaas_customer_id'], // ID do cliente no Asaas
            'billingType' => 'CREDIT_CARD', // ou 'BOLETO', 'PIX', etc.
            'value' => (float) $plan['annual_total_amount'], // Valor total anual
            'dueDate' => date('Y-m-d', strtotime('+1 year')), // Data de vencimento
            'description' => $plan['name'] . ' - Anual',
            // Para parcelamento (se necessário):
            // 'installmentCount' => 12,
            // 'installmentValue' => (float) $plan['annual_monthly_amount'],
            // Outros campos conforme documentação do Asaas
        ];
    }

    /**
     * Verifica se um plano tem opção anual habilitada
     * 
     * @param array $plan Dados do plano
     * @return bool
     */
    public static function hasAnnualOption(array $plan): bool
    {
        return !empty($plan['annual_enabled']) && 
               !empty($plan['annual_total_amount']) && 
               !empty($plan['annual_monthly_amount']);
    }

    /**
     * Retorna o valor mensal equivalente para exibição
     * 
     * @param array $plan Dados do plano
     * @return float|null
     */
    public static function getMonthlyEquivalent(array $plan): ?float
    {
        if (self::hasAnnualOption($plan)) {
            return (float) $plan['annual_monthly_amount'];
        }
        return null;
    }
}

