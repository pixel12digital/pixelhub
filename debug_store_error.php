<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\DB\DB;
use PixelHub\Services\TrackingCodesService;

echo "<h2>Debug do Erro 500 - Tracking Codes Store</h2>";

try {
    // 1. Testar conexão com DB
    echo "<h3>1. Testando conexão com DB</h3>";
    $db = DB::getConnection();
    echo "✅ Conexão com DB OK<br>";
    
    // 2. Verificar estrutura da tabela
    echo "<h3>2. Verificando estrutura da tabela tracking_codes</h3>";
    $stmt = $db->query("DESCRIBE tracking_codes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredColumns = ['code', 'source', 'description', 'is_active', 'created_by', 'created_at', 'updated_at', 'channel', 'origin_page', 'cta_position', 'campaign_name', 'campaign_id', 'ad_group', 'ad_name', 'context_metadata'];
    
    foreach ($requiredColumns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $col) {
                $found = true;
                echo "✅ Coluna $col encontrada<br>";
                break;
            }
        }
        if (!$found) {
            echo "❌ Coluna $col NÃO encontrada<br>";
        }
    }
    
    // 3. Testar método getSourceFromChannel
    echo "<h3>3. Testando getSourceFromChannel</h3>";
    $reflection = new ReflectionClass('PixelHub\Services\TrackingCodesService');
    $method = $reflection->getMethod('getSourceFromChannel');
    $method->setAccessible(true);
    
    $testChannels = ['google_organic', 'google_ads', 'meta_ads', 'direct', 'referral'];
    foreach ($testChannels as $channel) {
        try {
            $source = $method->invoke(null, $channel);
            echo "✅ $channel → $source<br>";
        } catch (Exception $e) {
            echo "❌ Erro em $channel: " . $e->getMessage() . "<br>";
        }
    }
    
    // 4. Testar criação com dados mock
    echo "<h3>4. Testando criação com dados mock</h3>";
    
    $mockData = [
        'code' => 'TEST-' . time(),
        'channel' => 'google_organic',
        'origin_page' => '/test',
        'cta_position' => 'header',
        'description' => 'Teste de criação',
        'campaign_name' => '',
        'campaign_id' => '',
        'ad_group' => '',
        'ad_name' => ''
    ];
    
    echo "Dados mock: " . json_encode($mockData) . "<br>";
    
    try {
        // Verificar se código já existe
        $stmt = $db->prepare("SELECT id FROM tracking_codes WHERE code = ?");
        $stmt->execute([$mockData['code']]);
        if ($stmt->fetch()) {
            echo "⚠️ Código já existe, tentando com outro código<br>";
            $mockData['code'] = 'TEST-' . time() . rand(100, 999);
        }
        
        $id = TrackingCodesService::create($mockData, 2);
        echo "✅ Código criado com ID: $id<br>";
        
        // Limpar código de teste
        $stmt = $db->prepare("DELETE FROM tracking_codes WHERE id = ?");
        $stmt->execute([$id]);
        echo "✅ Código de teste removido<br>";
        
    } catch (Exception $e) {
        echo "❌ Erro na criação: " . $e->getMessage() . "<br>";
        echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
    }
    
    // 5. Verificar logs de erro do PHP
    echo "<h3>5. Últimos erros do PHP</h3>";
    $errorLog = ini_get('error_log');
    if ($errorLog && file_exists($errorLog)) {
        $logs = file_get_contents($errorLog);
        $recentLogs = substr($logs, -2000); // Últimos 2000 caracteres
        echo "<pre>$recentLogs</pre>";
    } else {
        echo "❌ Arquivo de log não encontrado em: $errorLog<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h3>6. Teste completo do endpoint</h3>";
?>
<form method="post">
    <input type="hidden" name="test_data" value='{"code":"TEST-<?=time()?>","channel":"google_organic","description":"Teste endpoint"}'>
    <button type="submit">Testar Endpoint Store</button>
</form>

<?php
if ($_POST && isset($_POST['test_data'])) {
    echo "<h4>Testando endpoint com dados reais...</h4>";
    
    try {
        $data = json_decode($_POST['test_data'], true);
        echo "Dados: " . json_encode($data) . "<br>";
        
        $id = TrackingCodesService::create($data, 2);
        echo "✅ Sucesso! ID criado: $id<br>";
        
        // Limpar
        $db = DB::getConnection();
        $stmt = $db->prepare("DELETE FROM tracking_codes WHERE id = ?");
        $stmt->execute([$id]);
        echo "✅ Limpeza concluída<br>";
        
    } catch (Exception $e) {
        echo "❌ Falha: " . $e->getMessage() . "<br>";
        echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
    }
}
?>
