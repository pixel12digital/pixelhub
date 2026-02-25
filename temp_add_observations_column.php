<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Adicionando coluna 'observations' à tabela tenants...\n";

try {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN observations TEXT NULL AFTER internal_notes");
    echo "✅ Coluna 'observations' adicionada com sucesso!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "ℹ️  Coluna 'observations' já existe.\n";
    } else {
        echo "❌ Erro: " . $e->getMessage() . "\n";
    }
}
