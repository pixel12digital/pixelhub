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
}

