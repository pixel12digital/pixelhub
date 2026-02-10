<?php
spl_autoload_register(function($c){
    if(strncmp('PixelHub\\', $c, 9) === 0){
        $f = __DIR__ . '/src/' . str_replace('\\', '/', substr($c, 9)) . '.php';
        if(file_exists($f)) require $f;
    }
});
PixelHub\Core\Env::load();

$db = PixelHub\Core\DB::getConnection();

// Teste do UPDATE
$stmt = $db->prepare('
    UPDATE smtp_settings SET 
        smtp_enabled = ?, 
        smtp_host = ?, 
        smtp_port = ?, 
        smtp_username = ?, 
        smtp_password = ?, 
        smtp_encryption = ?, 
        smtp_from_name = ?, 
        smtp_from_email = ?, 
        updated_at = NOW() 
    WHERE id = (SELECT id FROM (SELECT id FROM smtp_settings LIMIT 1) AS sub)
');

$result = $stmt->execute([
    1,
    'mail.pixel12digital.com.br',
    465,
    'contato@pixel12digital.com.br',
    'senha123',
    'ssl',
    'Pixel12 Digital',
    'contato@pixel12digital.com.br'
]);

echo $result ? 'UPDATE OK' : 'UPDATE FAIL';
echo ' - rowCount: ' . $stmt->rowCount();
?>
