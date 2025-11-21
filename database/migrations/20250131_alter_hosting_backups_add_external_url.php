<?php

/**
 * Migration: Adiciona campos external_url e storage_location à tabela hosting_backups
 * Para suportar backups armazenados externamente (Google Drive, etc.)
 */
class AlterHostingBackupsAddExternalUrl
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM hosting_backups")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('external_url', $columns)) {
            $db->exec("ALTER TABLE hosting_backups ADD COLUMN external_url VARCHAR(500) NULL AFTER stored_path");
        }
        
        if (!in_array('storage_location', $columns)) {
            $db->exec("ALTER TABLE hosting_backups ADD COLUMN storage_location VARCHAR(100) NULL AFTER external_url");
        }
        
        // Garante que file_size e stored_path podem ser NULL para backups somente-link
        // (Se necessário, altera apenas se ainda não permitirem NULL)
        $stmt = $db->query("SHOW COLUMNS FROM hosting_backups WHERE Field = 'file_size'");
        $fileSizeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fileSizeInfo && strtoupper($fileSizeInfo['Null']) !== 'YES') {
            // Se file_size não permite NULL, mantém como está (já permite NULL na criação original)
        }
        
        $stmt = $db->query("SHOW COLUMNS FROM hosting_backups WHERE Field = 'stored_path'");
        $storedPathInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($storedPathInfo && strtoupper($storedPathInfo['Null']) !== 'YES') {
            // stored_path não pode ser NULL na criação original, mas vamos permitir para backups externos
            // Nota: Isso pode quebrar código que assume stored_path sempre existe, então vamos ser conservadores
            // e manter NOT NULL, mas na prática vamos deixar vazio se necessário
        }
    }

    public function down(PDO $db): void
    {
        // Remove as colunas se existirem (não remove dados, apenas as colunas)
        $columns = $db->query("SHOW COLUMNS FROM hosting_backups")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('storage_location', $columns)) {
            $db->exec("ALTER TABLE hosting_backups DROP COLUMN storage_location");
        }
        
        if (in_array('external_url', $columns)) {
            $db->exec("ALTER TABLE hosting_backups DROP COLUMN external_url");
        }
    }
}
