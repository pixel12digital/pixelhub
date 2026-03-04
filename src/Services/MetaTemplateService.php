<?php

namespace PixelHub\Services;

use PDO;
use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsApp\MetaOfficialProvider;

/**
 * Service para gerenciar templates do WhatsApp Business API (Meta)
 * 
 * Responsável por:
 * - CRUD de templates
 * - Submissão de templates para aprovação no Meta
 * - Verificação de status de aprovação
 * - Envio de mensagens usando templates aprovados
 * 
 * Data: 2026-03-04
 */
class MetaTemplateService
{
    /**
     * Lista todos os templates, opcionalmente filtrados
     * 
     * @param int|null $tenantId Filtrar por tenant (NULL = todos)
     * @param string|null $status Filtrar por status (draft, pending, approved, rejected)
     * @param string|null $category Filtrar por categoria (marketing, utility, authentication)
     * @return array Lista de templates
     */
    public static function listTemplates(?int $tenantId = null, ?string $status = null, ?string $category = null): array
    {
        $db = DB::getConnection();
        
        $where = [];
        $params = [];
        
        if ($tenantId !== null) {
            $where[] = "tenant_id = ?";
            $params[] = $tenantId;
        }
        
        if ($status !== null) {
            $where[] = "status = ?";
            $params[] = $status;
        }
        
        if ($category !== null) {
            $where[] = "category = ?";
            $params[] = $category;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $stmt = $db->prepare("
            SELECT 
                t.*,
                ten.name as tenant_name
            FROM whatsapp_message_templates t
            LEFT JOIN tenants ten ON t.tenant_id = ten.id
            {$whereClause}
            ORDER BY t.created_at DESC
        ");
        
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
        
        $stmt = $db->prepare("
            SELECT 
                t.*,
                ten.name as tenant_name
            FROM whatsapp_message_templates t
            LEFT JOIN tenants ten ON t.tenant_id = ten.id
            WHERE t.id = ?
        ");
        
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Cria um novo template
     * 
     * @param array $data Dados do template
     * @return int ID do template criado
     */
    public static function create(array $data): int
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO whatsapp_message_templates (
                tenant_id,
                template_name,
                category,
                language,
                status,
                content,
                header_type,
                header_content,
                footer_text,
                buttons,
                variables
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['tenant_id'] ?? null,
            $data['template_name'],
            $data['category'] ?? 'marketing',
            $data['language'] ?? 'pt_BR',
            $data['status'] ?? 'draft',
            $data['content'],
            $data['header_type'] ?? 'none',
            $data['header_content'] ?? null,
            $data['footer_text'] ?? null,
            isset($data['buttons']) ? json_encode($data['buttons']) : null,
            isset($data['variables']) ? json_encode($data['variables']) : null
        ]);
        
        return (int) $db->lastInsertId();
    }
    
    /**
     * Atualiza um template existente
     * 
     * @param int $id ID do template
     * @param array $data Dados para atualizar
     * @return bool Sucesso
     */
    public static function update(int $id, array $data): bool
    {
        $db = DB::getConnection();
        
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'template_name', 'category', 'language', 'status', 'content',
            'header_type', 'header_content', 'footer_text', 'buttons',
            'variables', 'rejection_reason', 'meta_template_id'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                
                // JSON encode para campos JSON
                if (in_array($field, ['buttons', 'variables']) && is_array($data[$field])) {
                    $params[] = json_encode($data[$field]);
                } else {
                    $params[] = $data[$field];
                }
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        
        $stmt = $db->prepare("
            UPDATE whatsapp_message_templates 
            SET " . implode(", ", $fields) . "
            WHERE id = ?
        ");
        
        return $stmt->execute($params);
    }
    
    /**
     * Submete template para aprovação no Meta via API
     * 
     * @param int $templateId ID do template
     * @return array Resultado da submissão
     */
    public static function submitToMeta(int $templateId): array
    {
        $template = self::getById($templateId);
        
        if (!$template) {
            return [
                'success' => false,
                'message' => 'Template não encontrado'
            ];
        }
        
        if ($template['status'] === 'approved') {
            return [
                'success' => false,
                'message' => 'Template já está aprovado'
            ];
        }
        
        // Busca configuração Meta (global ou por tenant)
        $db = DB::getConnection();
        
        // Tenta buscar config global primeiro (is_global=TRUE, tenant_id=NULL)
        $stmt = $db->prepare("
            SELECT meta_business_account_id, meta_access_token 
            FROM whatsapp_provider_configs 
            WHERE provider_type = 'meta_official' 
            AND is_active = 1
            AND is_global = 1
            LIMIT 1
        ");
        $stmt->execute();
        $config = $stmt->fetch();
        
        // Se não encontrou global, tenta buscar por tenant específico
        if (!$config) {
            $stmt = $db->prepare("
                SELECT meta_business_account_id, meta_access_token 
                FROM whatsapp_provider_configs 
                WHERE tenant_id = ? 
                AND provider_type = 'meta_official' 
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$template['tenant_id']]);
            $config = $stmt->fetch();
        }
        
        if (!$config || empty($config['meta_business_account_id']) || empty($config['meta_access_token'])) {
            return [
                'success' => false,
                'message' => 'Configuração Meta Official API não encontrada ou incompleta. Configure em Providers WhatsApp.'
            ];
        }
        
        // Descriptografa token se necessário
        $accessToken = $config['meta_access_token'];
        if (strpos($accessToken, 'encrypted:') === 0) {
            $accessToken = \PixelHub\Core\CryptoHelper::decrypt(substr($accessToken, 10));
        }
        
        // Monta payload para API Meta
        $payload = self::buildMetaTemplatePayload($template);
        
        // Faz POST para Meta Graph API
        $wabaId = $config['meta_business_account_id'];
        $url = "https://graph.facebook.com/v18.0/{$wabaId}/message_templates";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'message' => 'Erro ao conectar com Meta API: ' . $error
            ];
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
            // Sucesso - atualiza template com ID do Meta
            $stmt = $db->prepare("
                UPDATE whatsapp_message_templates 
                SET status = 'pending', 
                    meta_template_id = ?,
                    submitted_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['id'], $templateId]);
            
            return [
                'success' => true,
                'message' => 'Template submetido com sucesso! Aguarde aprovação do Meta (24-48h).',
                'meta_template_id' => $result['id']
            ];
        } else {
            // Erro da API Meta
            $errorMessage = $result['error']['message'] ?? 'Erro desconhecido';
            $errorCode = $result['error']['code'] ?? $httpCode;
            
            return [
                'success' => false,
                'message' => "Erro ao submeter template (código {$errorCode}): {$errorMessage}",
                'meta_response' => $result
            ];
        }
    }
    
    /**
     * Monta payload no formato esperado pela Meta Graph API
     * 
     * @param array $template Template do banco
     * @return array Payload para API Meta
     */
    private static function buildMetaTemplatePayload(array $template): array
    {
        $payload = [
            'name' => $template['template_name'],
            'language' => $template['language'],
            'category' => strtoupper($template['category']),
            'components' => []
        ];
        
        // Header
        if (!empty($template['header_type']) && $template['header_type'] !== 'none') {
            $headerComponent = [
                'type' => 'HEADER'
            ];
            
            if ($template['header_type'] === 'text') {
                $headerComponent['format'] = 'TEXT';
                $headerComponent['text'] = $template['header_content'];
            } else {
                $headerComponent['format'] = strtoupper($template['header_type']);
                $headerComponent['example'] = [
                    'header_handle' => [$template['header_content']]
                ];
            }
            
            $payload['components'][] = $headerComponent;
        }
        
        // Body (obrigatório)
        $payload['components'][] = [
            'type' => 'BODY',
            'text' => $template['content']
        ];
        
        // Footer
        if (!empty($template['footer_text'])) {
            $payload['components'][] = [
                'type' => 'FOOTER',
                'text' => $template['footer_text']
            ];
        }
        
        // Buttons
        if (!empty($template['buttons'])) {
            $buttons = is_string($template['buttons']) ? json_decode($template['buttons'], true) : $template['buttons'];
            
            if (!empty($buttons)) {
                $buttonComponent = [
                    'type' => 'BUTTONS',
                    'buttons' => []
                ];
                
                foreach ($buttons as $button) {
                    $metaButton = [];
                    
                    // Valida comprimento do texto (máx 20 caracteres)
                    $buttonText = $button['text'] ?? '';
                    if (mb_strlen($buttonText, 'UTF-8') > 20) {
                        $buttonText = mb_substr($buttonText, 0, 20, 'UTF-8');
                    }
                    
                    if ($button['type'] === 'quick_reply') {
                        $metaButton = [
                            'type' => 'QUICK_REPLY',
                            'text' => $buttonText
                        ];
                    } elseif ($button['type'] === 'url') {
                        $metaButton = [
                            'type' => 'URL',
                            'text' => $buttonText,
                            'url' => $button['id'] ?? ''
                        ];
                    } elseif ($button['type'] === 'phone') {
                        $metaButton = [
                            'type' => 'PHONE_NUMBER',
                            'text' => $buttonText,
                            'phone_number' => $button['id'] ?? ''
                        ];
                    }
                    
                    if (!empty($metaButton)) {
                        $buttonComponent['buttons'][] = $metaButton;
                    }
                }
                
                if (!empty($buttonComponent['buttons'])) {
                    $payload['components'][] = $buttonComponent;
                }
            }
        }
        
        return $payload;
    }
    
    /**
     * Marca template como aprovado (usado após confirmação do Meta)
     * 
     * @param int $templateId ID do template
     * @param string $metaTemplateId ID do template no Meta
     * @return bool Sucesso
     */
    public static function markAsApproved(int $templateId, string $metaTemplateId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE whatsapp_message_templates 
            SET status = 'approved', 
                meta_template_id = ?,
                approved_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$metaTemplateId, $templateId]);
    }
    
    /**
     * Marca template como rejeitado
     * 
     * @param int $templateId ID do template
     * @param string $reason Motivo da rejeição
     * @return bool Sucesso
     */
    public static function markAsRejected(int $templateId, string $reason): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE whatsapp_message_templates 
            SET status = 'rejected', 
                rejection_reason = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$reason, $templateId]);
    }
    
    /**
     * Deleta um template
     * 
     * @param int $id ID do template
     * @return bool Sucesso
     */
    public static function delete(int $id): bool
    {
        $db = DB::getConnection();
        
        // Verifica se template está sendo usado em campanhas ativas
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM template_campaigns 
            WHERE template_id = ? 
            AND status IN ('scheduled', 'running')
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result && $result['count'] > 0) {
            return false; // Não pode deletar template em uso
        }
        
        $stmt = $db->prepare("DELETE FROM whatsapp_message_templates WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Renderiza template substituindo variáveis
     * 
     * @param array $template Template
     * @param array $variables Variáveis para substituir: ['1' => 'João', '2' => 'R$ 100,00']
     * @return string Conteúdo renderizado
     */
    public static function renderTemplate(array $template, array $variables = []): string
    {
        $content = $template['content'] ?? '';
        
        // Meta usa formato {{1}}, {{2}}, etc
        foreach ($variables as $index => $value) {
            $content = str_replace("{{" . $index . "}}", $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Extrai variáveis do template (formato Meta: {{1}}, {{2}})
     * 
     * @param string $content Conteúdo do template
     * @return array Lista de índices de variáveis encontradas
     */
    public static function extractVariables(string $content): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }
    
    /**
     * Valida estrutura de template antes de submeter ao Meta
     * 
     * @param array $data Dados do template
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validateTemplate(array $data): array
    {
        $errors = [];
        
        // Nome do template
        if (empty($data['template_name'])) {
            $errors[] = 'Nome do template é obrigatório';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $data['template_name'])) {
            $errors[] = 'Nome do template deve conter apenas letras minúsculas, números e underscore';
        }
        
        // Conteúdo
        if (empty($data['content'])) {
            $errors[] = 'Conteúdo do template é obrigatório';
        } elseif (strlen($data['content']) > 1024) {
            $errors[] = 'Conteúdo do template não pode exceder 1024 caracteres';
        }
        
        // Footer
        if (!empty($data['footer_text']) && strlen($data['footer_text']) > 60) {
            $errors[] = 'Rodapé não pode exceder 60 caracteres';
        }
        
        // Botões
        if (!empty($data['buttons'])) {
            $buttons = is_string($data['buttons']) ? json_decode($data['buttons'], true) : $data['buttons'];
            
            if (count($buttons) > 3) {
                $errors[] = 'Máximo de 3 botões permitidos';
            }
            
            foreach ($buttons as $button) {
                if (empty($button['text'])) {
                    $errors[] = 'Texto do botão é obrigatório';
                } elseif (strlen($button['text']) > 25) {
                    $errors[] = 'Texto do botão não pode exceder 25 caracteres';
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
