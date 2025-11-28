<?php

namespace PixelHub\Core;

/**
 * Classe helper para gerenciar armazenamento de arquivos
 */
class Storage
{
    /**
     * Obtém o diretório de backups de um tenant e hosting account
     */
    public static function getTenantBackupDir(int $tenantId, int $hostingAccountId): string
    {
        $baseDir = __DIR__ . '/../../storage/tenants/' . $tenantId . '/backups/' . $hostingAccountId;
        return $baseDir;
    }

    /**
     * Obtém o diretório de documentos gerais de um tenant
     */
    public static function getTenantDocsDir(int $tenantId): string
    {
        $baseDir = __DIR__ . '/../../storage/tenants/' . $tenantId . '/docs';
        return $baseDir;
    }

    /**
     * Obtém o diretório de anexos de uma tarefa
     */
    public static function getTaskAttachmentsDir(int $taskId): string
    {
        $baseDir = __DIR__ . '/../../storage/tasks/' . $taskId;
        return $baseDir;
    }

    /**
     * Obtém o diretório de gravações de tela da biblioteca geral
     * Organiza por data (Y/m/d)
     * Salva em public/screen-recordings para acesso público
     */
    public static function getScreenRecordingsDir(string $subDir = ''): string
    {
        $baseDir = __DIR__ . '/../../public/screen-recordings';
        if ($subDir) {
            $baseDir .= '/' . trim($subDir, '/');
        }
        return $baseDir;
    }

    /**
     * Garante que um diretório existe (cria se necessário)
     */
    public static function ensureDirExists(string $path): void
    {
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Gera um nome de arquivo seguro
     */
    public static function generateSafeFileName(string $originalName): string
    {
        // Remove caracteres perigosos
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        
        // Limita tamanho
        if (strlen($safeName) > 200) {
            $ext = pathinfo($safeName, PATHINFO_EXTENSION);
            $name = substr(pathinfo($safeName, PATHINFO_FILENAME), 0, 200 - strlen($ext) - 1);
            $safeName = $name . '.' . $ext;
        }
        
        return $safeName;
    }

    /**
     * Formata tamanho de arquivo em formato legível
     */
    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Verifica se um arquivo existe no caminho armazenado
     * 
     * @param string $storedPath Caminho relativo salvo no banco (ex: /storage/tenants/1/backups/2/file.wpress)
     * @return bool
     */
    public static function fileExists(string $storedPath): bool
    {
        $absolutePath = __DIR__ . '/../../' . ltrim($storedPath, '/');
        return file_exists($absolutePath) && is_file($absolutePath);
    }
}

