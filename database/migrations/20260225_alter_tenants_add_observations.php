<?php

use PixelHub\Core\DB;

return new class {
    public function up(): void
    {
        $db = DB::getConnection();
        
        // Adiciona coluna observations à tabela tenants
        $db->exec("
            ALTER TABLE tenants 
            ADD COLUMN observations TEXT NULL AFTER internal_notes
        ");
        
        echo "✅ Coluna 'observations' adicionada à tabela tenants\n";
    }

    public function down(): void
    {
        $db = DB::getConnection();
        
        $db->exec("
            ALTER TABLE tenants 
            DROP COLUMN observations
        ");
        
        echo "✅ Coluna 'observations' removida da tabela tenants\n";
    }
};
