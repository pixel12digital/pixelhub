<?php

namespace PixelHub\Services;

use PDO;
use PixelHub\Core\DB;

/**
 * Service genérico para gerenciar templates de WhatsApp
 * 
 * Separado do WhatsAppBillingService - focado em templates genéricos
 * para campanhas, avisos, relacionamento comercial, etc.
 */
class WhatsAppTemplateService
{
    /**
     * Busca templates ativos, opcionalmente filtrados por categoria
     * 
     * @param string|null $category Categoria (comercial, campanha, geral)
     * @return array Lista de templates
     */
    public static function getActiveTemplates(?string $category = null): array
    {
        $db = DB::getConnection();
        
        $sql = "SELECT * FROM whatsapp_templates WHERE is_active = 1";
        $params = [];
        
        if ($category !== null) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Busca template por ID
     * 
     * @param int $id ID do template
     * @return array|null Template ou null se não encontrado
     */
    public static function getById(int $id): ?array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("SELECT * FROM whatsapp_templates WHERE id = ?");
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Renderiza o conteúdo do template substituindo variáveis
     * 
     * @param array $template Template (deve ter campo 'content')
     * @param array $vars Array associativo de variáveis: ['nome' => 'João', 'valor' => 'R$ 100,00']
     * @return string Conteúdo renderizado
     */
    public static function renderContent(array $template, array $vars): string
    {
        $content = $template['content'] ?? '';
        
        // Substitui variáveis no formato {variavel}
        foreach ($vars as $key => $value) {
            // Suporta tanto {variavel} quanto {variavel} (sem diferença de case)
            $content = str_ireplace('{' . $key . '}', $value ?? '', $content);
        }
        
        return $content;
    }

    /**
     * Extrai variáveis do conteúdo do template
     * 
     * @param string $content Conteúdo do template
     * @return array Lista de nomes de variáveis encontradas
     */
    public static function extractVariables(string $content): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Normaliza telefone (reutiliza lógica do WhatsAppBillingService)
     * 
     * @param string|null $rawPhone Telefone original
     * @return string|null Telefone normalizado
     */
    public static function normalizePhone(?string $rawPhone): ?string
    {
        return WhatsAppBillingService::normalizePhone($rawPhone);
    }

    /**
     * Gera link do WhatsApp Web com mensagem
     * 
     * @param string $phone Telefone normalizado
     * @param string $message Mensagem (será URL encoded)
     * @return string Link completo do WhatsApp Web
     */
    public static function buildWhatsAppLink(string $phone, string $message): string
    {
        $encodedMessage = urlencode($message);
        return "https://web.whatsapp.com/send?phone={$phone}&text={$encodedMessage}";
    }

    /**
     * Prepara variáveis padrão para um tenant
     * 
     * @param array $tenant Dados do tenant
     * @param array $hostingAccounts Lista de contas de hospedagem (opcional)
     * @return array Array de variáveis prontas para substituição
     */
    public static function prepareDefaultVariables(array $tenant, array $hostingAccounts = []): array
    {
        // Nome do cliente
        $nome = $tenant['name'] ?? 'Cliente';
        if (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['nome_fantasia'])) {
            $nome = $tenant['nome_fantasia'];
        } elseif (($tenant['person_type'] ?? 'pf') === 'pj' && !empty($tenant['razao_social'])) {
            $nome = $tenant['razao_social'];
        }

        // Domínio principal (primeiro da lista ou vazio)
        $dominio = '';
        if (!empty($hostingAccounts) && isset($hostingAccounts[0]['domain'])) {
            $dominio = $hostingAccounts[0]['domain'];
        }

        // Valor do plano (primeira hospedagem com valor)
        $valor = '';
        foreach ($hostingAccounts as $account) {
            if (!empty($account['amount']) && $account['amount'] > 0) {
                $valor = 'R$ ' . number_format((float)$account['amount'], 2, ',', '.');
                break;
            }
        }

        // Link de afiliado (pode vir de config ou constante)
        $linkAfiliado = defined('WHATSAPP_DEFAULT_AFFILIATE_LINK') 
            ? WHATSAPP_DEFAULT_AFFILIATE_LINK 
            : '';

        // Email e telefone
        $email = $tenant['email'] ?? '';
        $telefone = $tenant['phone'] ?? '';

        return [
            'nome' => $nome,
            'clientName' => $nome, // Alias
            'dominio' => $dominio,
            'domain' => $dominio, // Alias
            'valor' => $valor,
            'amount' => $valor, // Alias
            'linkAfiliado' => $linkAfiliado,
            'affiliateLink' => $linkAfiliado, // Alias
            'email' => $email,
            'telefone' => $telefone,
            'phone' => $telefone, // Alias
        ];
    }
}

