<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

$tenantId = $_GET['tenant_id'] ?? 146;

echo "<h2>📊 Notificações de Cobrança - Tenant {$tenantId}</h2>";

// Busca tenant
$stmt = $db->prepare("SELECT name FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tenant) {
    echo "<h3>Cliente: {$tenant['name']}</h3>";
}

// Busca notificações
$stmt = $db->prepare("
    SELECT 
        bn.id,
        bn.channel,
        bn.status,
        bn.template,
        bn.created_at,
        bn.sent_at,
        bn.last_error,
        bn.gateway_message_id,
        bi.amount,
        bi.due_date,
        bi.asaas_status
    FROM billing_notifications bn
    LEFT JOIN billing_invoices bi ON bn.invoice_id = bi.id
    WHERE bn.tenant_id = ?
    ORDER BY bn.created_at DESC
    LIMIT 50
");
$stmt->execute([$tenantId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($notifications)) {
    echo "<p>❌ Nenhuma notificação encontrada para este tenant.</p>";
} else {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<thead style='background: #f0f0f0;'>";
    echo "<tr>";
    echo "<th>ID</th>";
    echo "<th>Canal</th>";
    echo "<th>Status</th>";
    echo "<th>Template</th>";
    echo "<th>Fatura</th>";
    echo "<th>Criada em</th>";
    echo "<th>Enviada em</th>";
    echo "<th>Gateway ID</th>";
    echo "<th>Erro</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($notifications as $n) {
        $statusColor = match($n['status']) {
            'sent' => '#28a745',
            'failed' => '#dc3545',
            'prepared' => '#ffc107',
            default => '#6c757d'
        };
        
        $statusIcon = match($n['status']) {
            'sent' => '✅',
            'failed' => '❌',
            'prepared' => '⏳',
            default => '❓'
        };
        
        $channelIcon = match($n['channel']) {
            'whatsapp' => '💬',
            'email' => '📧',
            default => '📨'
        };
        
        echo "<tr>";
        echo "<td>{$n['id']}</td>";
        echo "<td>{$channelIcon} {$n['channel']}</td>";
        echo "<td style='color: {$statusColor}; font-weight: bold;'>{$statusIcon} {$n['status']}</td>";
        echo "<td>{$n['template']}</td>";
        
        if ($n['amount']) {
            $amount = 'R$ ' . number_format($n['amount'], 2, ',', '.');
            $dueDate = date('d/m/Y', strtotime($n['due_date']));
            echo "<td>{$amount}<br><small>{$dueDate}</small></td>";
        } else {
            echo "<td>-</td>";
        }
        
        echo "<td>" . date('d/m/Y H:i:s', strtotime($n['created_at'])) . "</td>";
        echo "<td>" . ($n['sent_at'] ? date('d/m/Y H:i:s', strtotime($n['sent_at'])) : '-') . "</td>";
        echo "<td>" . ($n['gateway_message_id'] ?? '-') . "</td>";
        echo "<td style='color: #dc3545; font-size: 12px;'>" . ($n['last_error'] ?? '-') . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
}

echo "<br><br>";
echo "<p><strong>Legenda de Status:</strong></p>";
echo "<ul>";
echo "<li>✅ <strong style='color: #28a745;'>sent</strong> = Enviado com sucesso</li>";
echo "<li>❌ <strong style='color: #dc3545;'>failed</strong> = Falhou (veja coluna 'Erro')</li>";
echo "<li>⏳ <strong style='color: #ffc107;'>prepared</strong> = Preparado mas não enviado ainda</li>";
echo "</ul>";

echo "<p><strong>Legenda de Canais:</strong></p>";
echo "<ul>";
echo "<li>💬 <strong>whatsapp</strong> = WhatsApp</li>";
echo "<li>📧 <strong>email</strong> = Email</li>";
echo "</ul>";
