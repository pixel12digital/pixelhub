<?php
/**
 * Script de diagnóstico para verificar se áudios outbound estão sendo salvos
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

echo "=== DIAGNÓSTICO DE ÁUDIOS OUTBOUND ===\n\n";

try {
    $db = DB::getConnection();
    echo "✅ Conexão com banco OK\n\n";
    
    // 1. Busca eventos de áudio outbound recentes
    echo "1. EVENTOS DE ÁUDIO OUTBOUND (últimas 24h):\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->query("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.tenant_id,
            JSON_EXTRACT(ce.payload, '$.type') as msg_type,
            JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by_name
        FROM communication_events ce
        WHERE ce.event_type = 'whatsapp.outbound.message'
        AND (
            JSON_EXTRACT(ce.payload, '$.type') = '\"audio\"'
            OR JSON_EXTRACT(ce.payload, '$.message.type') = '\"audio\"'
        )
        AND ce.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $events = $stmt->fetchAll();
    
    if (empty($events)) {
        echo "❌ Nenhum evento de áudio outbound encontrado nas últimas 24h\n";
    } else {
        echo "Encontrados " . count($events) . " evento(s):\n\n";
        
        foreach ($events as $event) {
            echo "  Event ID: {$event['event_id']}\n";
            echo "  Created:  {$event['created_at']}\n";
            echo "  Tenant:   {$event['tenant_id']}\n";
            echo "  Type:     {$event['msg_type']}\n";
            echo "  Sent by:  {$event['sent_by_name']}\n";
            
            // Verifica se tem mídia associada
            $mediaStmt = $db->prepare("
                SELECT * FROM communication_media 
                WHERE event_id = ?
            ");
            $mediaStmt->execute([$event['event_id']]);
            $media = $mediaStmt->fetch();
            
            if ($media) {
                echo "  📁 MÍDIA ENCONTRADA:\n";
                echo "     - media_type: {$media['media_type']}\n";
                echo "     - mime_type:  {$media['mime_type']}\n";
                echo "     - stored_path: {$media['stored_path']}\n";
                echo "     - file_size:  {$media['file_size']} bytes\n";
                
                // Verifica se arquivo existe
                $fullPath = __DIR__ . '/../storage/' . $media['stored_path'];
                if (file_exists($fullPath)) {
                    $actualSize = filesize($fullPath);
                    echo "     ✅ Arquivo existe! Tamanho: {$actualSize} bytes\n";
                } else {
                    echo "     ❌ Arquivo NÃO existe em: {$fullPath}\n";
                }
            } else {
                echo "  ❌ SEM MÍDIA ASSOCIADA na tabela communication_media\n";
            }
            
            echo "\n";
        }
    }
    
    // 2. Estatísticas gerais
    echo "\n2. ESTATÍSTICAS GERAIS:\n";
    echo str_repeat("-", 80) . "\n";
    
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total_eventos,
            SUM(CASE WHEN cm.id IS NOT NULL THEN 1 ELSE 0 END) as com_midia,
            SUM(CASE WHEN cm.id IS NULL THEN 1 ELSE 0 END) as sem_midia
        FROM communication_events ce
        LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
        WHERE ce.event_type = 'whatsapp.outbound.message'
        AND (
            JSON_EXTRACT(ce.payload, '$.type') = '\"audio\"'
            OR JSON_EXTRACT(ce.payload, '$.message.type') = '\"audio\"'
        )
    ");
    $stats = $statsStmt->fetch();
    
    echo "Total eventos de áudio outbound: {$stats['total_eventos']}\n";
    echo "  - Com mídia salva: {$stats['com_midia']}\n";
    echo "  - Sem mídia:       {$stats['sem_midia']}\n";
    
    // 3. Verificar diretório storage
    echo "\n3. DIRETÓRIO STORAGE:\n";
    echo str_repeat("-", 80) . "\n";
    
    $storageDir = __DIR__ . '/../storage/whatsapp-media';
    echo "Caminho: {$storageDir}\n";
    echo "Existe: " . (is_dir($storageDir) ? '✅ Sim' : '❌ Não') . "\n";
    echo "Gravável: " . (is_writable($storageDir) ? '✅ Sim' : '❌ Não') . "\n";
    
    // Conta arquivos
    if (is_dir($storageDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($storageDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $fileCount = 0;
        $totalSize = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'ogg') {
                $fileCount++;
                $totalSize += $file->getSize();
            }
        }
        echo "Arquivos .ogg: {$fileCount}\n";
        echo "Tamanho total: " . number_format($totalSize / 1024, 2) . " KB\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
