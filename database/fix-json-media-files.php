<?php
/**
 * Script para corrigir arquivos de mídia que foram salvos como JSON
 * ao invés do binário real (bug do wppconnectAdapter)
 */
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = PixelHub\Core\DB::getConnection();

$storageBase = __DIR__ . '/../storage/';

echo "=== CORRIGINDO ARQUIVOS DE MÍDIA COM JSON ===\n\n";

// Busca mídias com mime_type incorreto ou arquivos .bin
$stmt = $db->query("
    SELECT id, media_type, mime_type, stored_path, file_size
    FROM communication_media 
    WHERE stored_path IS NOT NULL 
      AND stored_path != ''
      AND (mime_type LIKE '%json%' OR stored_path LIKE '%.bin')
    ORDER BY id DESC
    LIMIT 50
");

$fixed = 0;
$failed = 0;

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $fullPath = $storageBase . $m['stored_path'];
    
    if (!file_exists($fullPath)) {
        echo "[{$m['id']}] SKIP - arquivo nao existe: {$m['stored_path']}\n";
        continue;
    }
    
    $content = file_get_contents($fullPath);
    
    // Verifica se começa com { (JSON)
    if (strlen($content) > 0 && $content[0] === '{') {
        echo "[{$m['id']}] Detectado JSON em {$m['stored_path']}\n";
        
        $json = json_decode($content, true);
        if ($json && json_last_error() === JSON_ERROR_NONE) {
            // Extrai base64 do JSON
            $base64 = $json['base64'] ?? $json['data'] ?? $json['body'] ?? null;
            $mimeType = $json['mimetype'] ?? $json['mimeType'] ?? $json['mime_type'] ?? null;
            
            if ($base64) {
                $binaryData = base64_decode($base64);
                
                // Verifica se é OGG válido
                $isOgg = (strlen($binaryData) >= 4 && substr($binaryData, 0, 4) === 'OggS');
                
                if ($isOgg || strlen($binaryData) > 100) {
                    // Determina nova extensão
                    $newExt = 'bin';
                    if ($isOgg) {
                        $newExt = 'ogg';
                        $mimeType = 'audio/ogg';
                    } elseif ($mimeType && strpos($mimeType, 'video') !== false) {
                        $newExt = 'mp4';
                    } elseif ($mimeType && strpos($mimeType, 'image') !== false) {
                        $newExt = 'jpg';
                    }
                    
                    // Novo caminho com extensão correta
                    $newPath = preg_replace('/\.[^.]+$/', '.' . $newExt, $m['stored_path']);
                    $newFullPath = $storageBase . $newPath;
                    
                    // Salva arquivo corrigido
                    if (file_put_contents($newFullPath, $binaryData)) {
                        // Atualiza banco
                        $updateStmt = $db->prepare("
                            UPDATE communication_media 
                            SET stored_path = ?, 
                                mime_type = ?,
                                file_size = ?
                            WHERE id = ?
                        ");
                        $updateStmt->execute([
                            $newPath,
                            $mimeType ?: $m['mime_type'],
                            strlen($binaryData),
                            $m['id']
                        ]);
                        
                        // Remove arquivo antigo se diferente
                        if ($fullPath !== $newFullPath && file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                        
                        echo "    CORRIGIDO! {$newPath} ({$mimeType}, " . strlen($binaryData) . " bytes)\n";
                        $fixed++;
                    } else {
                        echo "    ERRO ao salvar arquivo corrigido\n";
                        $failed++;
                    }
                } else {
                    echo "    ERRO - dados decodificados parecem invalidos (size=" . strlen($binaryData) . ")\n";
                    $failed++;
                }
            } else {
                echo "    ERRO - JSON nao contem campo base64/data/body\n";
                echo "    Campos disponiveis: " . implode(', ', array_keys($json)) . "\n";
                $failed++;
            }
        } else {
            echo "    ERRO - nao e JSON valido\n";
            $failed++;
        }
    } else {
        // Arquivo já é binário - verifica se precisa apenas atualizar mime_type
        $isOgg = (strlen($content) >= 4 && substr($content, 0, 4) === 'OggS');
        if ($isOgg && strpos($m['mime_type'], 'json') !== false) {
            $updateStmt = $db->prepare("UPDATE communication_media SET mime_type = 'audio/ogg' WHERE id = ?");
            $updateStmt->execute([$m['id']]);
            echo "[{$m['id']}] Atualizado mime_type para audio/ogg\n";
            $fixed++;
        } else {
            echo "[{$m['id']}] OK - ja e binario\n";
        }
    }
}

echo "\n=== RESULTADO ===\n";
echo "Corrigidos: {$fixed}\n";
echo "Falhas: {$failed}\n";
