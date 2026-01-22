<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\Storage;
use PDO;

/**
 * Service para geração de cartão de visita
 * 
 * Inicialmente o pipeline é manual (operacional), mas mantém estrutura pronta
 * para integração futura com Canva ou outro sistema de geração.
 */
class BusinessCardGeneratorService
{
    /**
     * Inicia processo de geração do cartão
     * 
     * @param int $orderId ID do pedido
     * @return int ID do intake usado para gerar
     */
    public static function startGeneration(int $orderId): int
    {
        $db = DB::getConnection();
        
        // Busca intake
        $intake = BusinessCardIntakeService::findIntakeByOrder($orderId);
        if (!$intake) {
            throw new \RuntimeException('Intake não encontrado para o pedido');
        }
        
        if (!$intake['is_valid']) {
            throw new \RuntimeException('Intake não está válido para geração');
        }
        
        // Busca pedido
        $order = ServiceOrderService::findOrder($orderId);
        if (!$order) {
            throw new \RuntimeException('Pedido não encontrado');
        }
        
        // Atualiza status do pedido para "in_progress"
        $db->prepare("
            UPDATE service_orders 
            SET status = 'in_progress', updated_at = NOW() 
            WHERE id = ?
        ")->execute([$orderId]);
        
        // Log
        error_log(sprintf(
            '[BusinessCardGenerator] generation_started: order_id=%d, intake_id=%d',
            $orderId,
            $intake['id']
        ));
        
        return (int) $intake['id'];
    }
    
    /**
     * Seleciona template baseado nos dados do intake
     * 
     * @param array $intakeData Dados do intake (data_json)
     * @return array Template selecionado com fallback
     */
    public static function selectTemplate(array $intakeData): array
    {
        $style = $intakeData['style'] ?? [];
        $mood = $style['mood'] ?? 'corporativo_moderno';
        $background = $style['background'] ?? 'claro';
        
        // Template principal
        $primaryTemplate = self::findTemplateByStyle($mood, $background);
        
        // Template fallback
        $fallbackTemplate = self::findTemplateByStyle('corporativo_moderno', 'claro');
        
        // Log
        error_log(sprintf(
            '[BusinessCardGenerator] template_selected: primary=%s, fallback=%s, mood=%s, background=%s',
            $primaryTemplate['id'],
            $fallbackTemplate['id'],
            $mood,
            $background
        ));
        
        return [
            'primary' => $primaryTemplate,
            'fallback' => $fallbackTemplate
        ];
    }
    
    /**
     * Encontra template por estilo
     * 
     * Por enquanto retorna template padrão (hardcoded).
     * No futuro, pode buscar de um catálogo no banco ou config.
     */
    private static function findTemplateByStyle(string $mood, string $background): array
    {
        // Por enquanto, retorna template padrão
        // No futuro, pode buscar de tabela template_catalog ou config JSON
        
        $templates = [
            'corporativo_moderno_claro' => [
                'id' => 'bc_corp_modern_light_001',
                'name' => 'Corporativo Moderno - Claro',
                'mood' => 'corporativo_moderno',
                'background' => 'claro'
            ],
            'corporativo_moderno_escuro' => [
                'id' => 'bc_corp_modern_dark_001',
                'name' => 'Corporativo Moderno - Escuro',
                'mood' => 'corporativo_moderno',
                'background' => 'escuro'
            ],
            'criativo_moderno_claro' => [
                'id' => 'bc_creative_modern_light_001',
                'name' => 'Criativo Moderno - Claro',
                'mood' => 'criativo_moderno',
                'background' => 'claro'
            ],
            'elegante_sofisticado_claro' => [
                'id' => 'bc_elegant_light_001',
                'name' => 'Elegante - Claro',
                'mood' => 'elegante_sofisticado',
                'background' => 'claro'
            ]
        ];
        
        $key = $mood . '_' . $background;
        if (isset($templates[$key])) {
            return $templates[$key];
        }
        
        // Fallback padrão
        return $templates['corporativo_moderno_claro'];
    }
    
    /**
     * Gera QR Code e retorna path/URL
     * 
     * @param string $url URL para o QR Code (ex: WhatsApp)
     * @param string $orderId ID do pedido (para nome do arquivo)
     * @return array ['url' => string, 'path' => string]
     */
    public static function generateQRCode(string $url, int $orderId): array
    {
        // Por enquanto, retorna estrutura pronta
        // No futuro, pode usar lib como SimpleSoftwareIO/simple-qrcode ou Endroid/QrCode
        
        // Gera nome do arquivo
        $filename = 'qr_' . $orderId . '_' . time() . '.png';
        
        // Path onde será salvo
        $storagePath = 'service_orders/' . $orderId . '/qr';
        $fullPath = Storage::ensureDirectory($storagePath);
        $filePath = $fullPath . '/' . $filename;
        
        // Por enquanto, apenas prepara a estrutura
        // TODO: Implementar geração real do QR Code
        // Exemplo com lib:
        // $qrCode = QrCode::create($url)->setSize(300)->setMargin(2);
        // file_put_contents($filePath, $qrCode->writeString());
        
        // Por enquanto, cria arquivo placeholder (para não quebrar o fluxo)
        // No futuro, substituir por geração real
        if (!file_exists($filePath)) {
            // Cria um placeholder PNG básico (1x1px transparente)
            $placeholder = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            file_put_contents($filePath, $placeholder);
        }
        
        $publicUrl = Storage::getPublicUrl($filePath);
        
        return [
            'url' => $publicUrl,
            'path' => $filePath
        ];
    }
    
    /**
     * Salva deliverable (arquivo gerado)
     * 
     * @param int $orderId ID do pedido
     * @param string $kind Tipo: pdf_print | png_digital | qr_asset | source_internal
     * @param string $filePath Path do arquivo
     * @param array|null $metadata Metadados adicionais
     * @return int ID do deliverable criado
     */
    public static function saveDeliverable(int $orderId, string $kind, string $filePath, ?array $metadata = null): int
    {
        $db = DB::getConnection();
        
        $allowedKinds = ['pdf_print', 'png_digital', 'qr_asset', 'source_internal'];
        if (!in_array($kind, $allowedKinds)) {
            throw new \InvalidArgumentException('Kind inválido: ' . $kind);
        }
        
        // Verifica se arquivo existe
        if (!file_exists($filePath)) {
            throw new \RuntimeException('Arquivo não encontrado: ' . $filePath);
        }
        
        $publicUrl = Storage::getPublicUrl($filePath);
        
        $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $db->prepare("
            INSERT INTO service_deliverables 
            (order_id, kind, file_url, file_path, metadata, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$orderId, $kind, $publicUrl, $filePath, $metadataJson]);
        
        $deliverableId = (int) $db->lastInsertId();
        
        // Log
        error_log(sprintf(
            '[BusinessCardGenerator] deliverables_saved: deliverable_id=%d, order_id=%d, kind=%s',
            $deliverableId,
            $orderId,
            $kind
        ));
        
        return $deliverableId;
    }
    
    /**
     * Gera cartão completo (pipeline principal)
     * 
     * Por enquanto, apenas prepara a estrutura e marca como "awaiting_generation".
     * No futuro, pode integrar com Canva ou outro sistema.
     * 
     * @param int $orderId ID do pedido
     * @return array Informações sobre a geração iniciada
     */
    public static function generateBusinessCard(int $orderId): array
    {
        // Inicia geração
        $intakeId = self::startGeneration($orderId);
        
        // Busca dados do intake
        $intake = BusinessCardIntakeService::findIntakeByOrder($orderId);
        $intakeData = json_decode($intake['data_json'], true);
        
        // Seleciona template
        $templates = self::selectTemplate($intakeData);
        
        // Por enquanto, apenas prepara estrutura
        // TODO: Implementar geração real via Canva API ou similar
        // Exemplo futuro:
        // 1. Duplica template do catálogo interno
        // 2. Aplica campos (nome, cargo, contatos)
        // 3. Gera QR Code se necessário
        // 4. Exporta PDF e PNG
        // 5. Salva deliverables
        
        // Se tiver QR habilitado, gera QR
        if (!empty($intakeData['qr']['enabled']) && !empty($intakeData['qr']['value'])) {
            $qrData = self::generateQRCode($intakeData['qr']['value'], $orderId);
            
            // Salva QR como deliverable
            self::saveDeliverable($orderId, 'qr_asset', $qrData['path'], [
                'target' => $intakeData['qr']['target'] ?? 'whatsapp',
                'url' => $intakeData['qr']['value']
            ]);
        }
        
        // Log
        error_log(sprintf(
            '[BusinessCardGenerator] generation_finished: order_id=%d, template=%s, has_qr=%s',
            $orderId,
            $templates['primary']['id'],
            !empty($intakeData['qr']['enabled']) ? 'YES' : 'NO'
        ));
        
        // Por enquanto, retorna estrutura preparada
        // No futuro, quando integrado com Canva, retornará dados reais dos arquivos gerados
        return [
            'status' => 'awaiting_generation', // Status temporário até integração real
            'intake_id' => $intakeId,
            'template' => $templates['primary'],
            'has_qr' => !empty($intakeData['qr']['enabled']),
            'message' => 'Geração iniciada. Os arquivos serão gerados em breve.'
        ];
    }
    
    /**
     * Marca geração como concluída (quando arquivos foram gerados manualmente)
     * 
     * @param int $orderId ID do pedido
     * @param array $files Array com paths dos arquivos gerados ['pdf' => path, 'png' => path]
     * @return bool Sucesso
     */
    public static function markGenerationComplete(int $orderId, array $files): bool
    {
        $db = DB::getConnection();
        
        // Salva PDF se houver
        if (!empty($files['pdf']) && file_exists($files['pdf'])) {
            self::saveDeliverable($orderId, 'pdf_print', $files['pdf'], [
                'type' => 'print',
                'size' => 'A4',
                'bleed' => true
            ]);
        }
        
        // Salva PNG se houver
        if (!empty($files['png']) && file_exists($files['png'])) {
            self::saveDeliverable($orderId, 'png_digital', $files['png'], [
                'type' => 'digital',
                'resolution' => '300dpi'
            ]);
        }
        
        // Atualiza status do pedido para "delivered"
        $db->prepare("
            UPDATE service_orders 
            SET status = 'delivered', updated_at = NOW() 
            WHERE id = ?
        ")->execute([$orderId]);
        
        // Log
        error_log(sprintf(
            '[BusinessCardGenerator] generation_completed: order_id=%d, has_pdf=%s, has_png=%s',
            $orderId,
            !empty($files['pdf']) ? 'YES' : 'NO',
            !empty($files['png']) ? 'YES' : 'NO'
        ));
        
        return true;
    }
    
    /**
     * Lista deliverables de um pedido
     * 
     * @param int $orderId ID do pedido
     * @return array Lista de deliverables
     */
    public static function getDeliverables(int $orderId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM service_deliverables 
            WHERE order_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll() ?: [];
    }
}

