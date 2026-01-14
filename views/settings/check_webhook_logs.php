<?php
/**
 * Página de verificação de logs do webhook
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verificação de Logs - Webhook</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #023A8D; margin-top: 0; }
        .section { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid #023A8D; }
        .section h2 { color: #023A8D; margin-top: 0; font-size: 18px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; line-height: 1.5; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-error { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; }
        .form-group input { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; text-decoration: none; display: inline-block; }
        .btn-primary { background: #023A8D; color: white; }
        .btn-primary:hover { background: #022a6b; }
        .timestamp { color: #666; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table th, table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f8f9fa; font-weight: 600; color: #333; }
        .highlight { background: #fff3cd; padding: 2px 4px; border-radius: 2px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificação de Logs - Webhook Teste</h1>
        <div class="timestamp">Executado em: <?php echo date('Y-m-d H:i:s'); ?></div>
        <div class="info" style="margin-top: 10px; padding: 10px; background: #e7f3ff; border-left: 4px solid #17a2b8; border-radius: 4px;">
            <strong>ℹ️ Importante:</strong> Esta página busca logs do <strong>Pixel Hub</strong> (servidor que recebe o webhook do gateway). 
            Os logs mostram como o Hub processou o webhook recebido.
        </div>
        
        <div class="section">
            <h2>📋 Parâmetros da Busca</h2>
            <form method="GET" action="<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic/check-logs') ?>">
                <div class="form-group">
                    <label>correlation_id:</label>
                    <input type="text" name="correlation_id" value="<?= htmlspecialchars($correlationId) ?>" placeholder="9858a507-cc4c-4632-8f92-462535eab504">
                </div>
                <div class="form-group">
                    <label>Horário do teste:</label>
                    <input type="text" name="test_time" value="<?= htmlspecialchars($testTime) ?>" placeholder="21:35">
                </div>
                <div class="form-group">
                    <label>Container:</label>
                    <input type="text" name="container" value="<?= htmlspecialchars($containerName) ?>" placeholder="gateway-hub">
                </div>
                <button type="submit" class="btn btn-primary">🔄 Atualizar Busca</button>
            </form>
        </div>

        <!-- Status do Docker -->
        <div class="section">
            <h2>1. Status do Docker (Pixel Hub)</h2>
            <div class="info" style="margin-bottom: 10px;">
                <strong>Nota:</strong> Estamos buscando logs do <strong>Pixel Hub</strong> (onde o webhook é recebido), não do gateway.
            </div>
            <?php if ($dockerAvailable): ?>
                <div class="success">✅ Docker disponível - Buscando logs do container do Hub</div>
            <?php else: ?>
                <div class="warning">⚠️ Docker não disponível - Buscando em arquivos de log do Hub</div>
                <?php if (isset($logFile) && $logFile): ?>
                    <div class="info">📄 Arquivo de log encontrado: <strong><?= htmlspecialchars($logFile) ?></strong></div>
                <?php else: ?>
                    <div class="error">❌ Arquivo de log não encontrado. Verifique se os logs estão sendo gerados no Hub.</div>
                    <div class="info" style="margin-top: 10px;">
                        <strong>Locais verificados:</strong><br>
                        • logs/pixelhub.log<br>
                        • storage/logs/pixelhub.log<br>
                        • error_log do PHP<br>
                        • /var/log/php/error.log<br>
                        • /var/log/apache2/error.log
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Containers -->
        <?php if ($dockerAvailable && !empty($containers)): ?>
        <div class="section">
            <h2>2. Containers Disponíveis</h2>
            <pre><?php
            foreach ($containers as $line) {
                if (!empty(trim($line))) {
                    // Destaca containers com "hub" no nome
                    if (stripos($line, 'hub') !== false) {
                        echo '<span class="highlight">' . htmlspecialchars($line) . '</span>' . "\n";
                    } else {
                        echo htmlspecialchars($line) . "\n";
                    }
                }
            }
            ?></pre>
            <div class="info">Usando container: <strong><?= htmlspecialchars($containerName) ?></strong></div>
        </div>
        <?php endif; ?>

        <!-- Correlation ID -->
        <div class="section">
            <h2>3. Buscando correlation_id nos Logs</h2>
            <div class="info">correlation_id: <strong><?= htmlspecialchars($correlationId) ?></strong></div>
            <div class="info" style="font-size: 12px; margin-top: 5px;">
                <strong>Nota:</strong> Buscando logs com padrão HUB_* que contenham o correlation_id. 
                Linhas de roteamento/URL são ignoradas.
            </div>
            <?php if (!empty($logs['correlation_id'])): ?>
                <div class="success">✅ Encontradas <?= count($logs['correlation_id']) ?> linhas:</div>
                <pre><?php
                foreach ($logs['correlation_id'] as $line) {
                    // Destaca linhas importantes
                    if (stripos($line, 'HUB_WEBHOOK_IN') !== false) {
                        echo '<span class="success">' . htmlspecialchars($line) . '</span>' . "\n";
                    } elseif (stripos($line, 'HUB_MSG_SAVE') !== false) {
                        echo '<span class="success">' . htmlspecialchars($line) . '</span>' . "\n";
                    } elseif (stripos($line, 'HUB_MSG_DROP') !== false) {
                        echo '<span class="warning">' . htmlspecialchars($line) . '</span>' . "\n";
                    } elseif (stripos($line, 'HUB_') !== false) {
                        echo '<span class="info">' . htmlspecialchars($line) . '</span>' . "\n";
                    } else {
                        echo htmlspecialchars($line) . "\n";
                    }
                }
                ?></pre>
            <?php else: ?>
                <div class="error">❌ Nenhuma linha encontrada com correlation_id nos logs HUB_*</div>
                <div class="info" style="margin-top: 10px;">
                    <strong>Possíveis causas:</strong><br>
                    • O webhook não chegou ao Hub no horário especificado<br>
                    • Os logs estão em outro arquivo/local<br>
                    • O horário está em UTC (tente 19:35 ao invés de 21:35)
                </div>
            <?php endif; ?>
        </div>

        <!-- HUB_WEBHOOK_IN -->
        <div class="section">
            <h2>4. Buscando HUB_WEBHOOK_IN (Entrada do Webhook)</h2>
            <?php if (!empty($logs['webhook_in'])): ?>
                <div class="success">✅ Encontradas <?= count($logs['webhook_in']) ?> linhas:</div>
                <pre><?php
                foreach ($logs['webhook_in'] as $line) {
                    // Destaca se contém o horário do teste
                    if (strpos($line, $testTime) !== false || strpos($line, $correlationId) !== false) {
                        echo '<span class="highlight">' . htmlspecialchars($line) . '</span>' . "\n";
                    } else {
                        echo htmlspecialchars($line) . "\n";
                    }
                }
                ?></pre>
            <?php else: ?>
                <div class="error">❌ Nenhuma linha encontrada</div>
            <?php endif; ?>
        </div>

        <!-- HUB_MSG_SAVE -->
        <div class="section">
            <h2>5. Buscando HUB_MSG_SAVE (Persistência de Mensagem)</h2>
            <?php if (!empty($logs['msg_save'])): ?>
                <div class="success">✅ Encontradas <?= count($logs['msg_save']) ?> linhas:</div>
                <pre><?php
                foreach ($logs['msg_save'] as $line) {
                    if (strpos($line, $testTime) !== false || strpos($line, $correlationId) !== false) {
                        echo '<span class="highlight">' . htmlspecialchars($line) . '</span>' . "\n";
                    } else {
                        echo htmlspecialchars($line) . "\n";
                    }
                }
                ?></pre>
            <?php else: ?>
                <div class="error">❌ Nenhuma linha encontrada</div>
            <?php endif; ?>
        </div>

        <!-- HUB_MSG_DROP -->
        <div class="section">
            <h2>6. Buscando HUB_MSG_DROP (Mensagens Descartadas)</h2>
            <?php if (!empty($logs['msg_drop'])): ?>
                <div class="warning">⚠️ Encontradas <?= count($logs['msg_drop']) ?> linhas (eventos descartados):</div>
                <pre><?php
                foreach ($logs['msg_drop'] as $line) {
                    if (strpos($line, $testTime) !== false || strpos($line, $correlationId) !== false) {
                        echo '<span class="highlight">' . htmlspecialchars($line) . '</span>' . "\n";
                    } else {
                        echo htmlspecialchars($line) . "\n";
                    }
                }
                ?></pre>
            <?php else: ?>
                <div class="success">✅ Nenhuma linha encontrada (nenhum evento descartado)</div>
            <?php endif; ?>
        </div>

        <!-- Erros -->
        <div class="section">
            <h2>7. Buscando Erros/Exceções</h2>
            <?php if (!empty($logs['errors'])): ?>
                <div class="error">❌ Encontradas <?= count($logs['errors']) ?> linhas de erro:</div>
                <pre><?php
                foreach ($logs['errors'] as $line) {
                    if (strpos($line, $testTime) !== false || strpos($line, $correlationId) !== false) {
                        echo '<span class="highlight">' . htmlspecialchars($line) . '</span>' . "\n";
                    } else {
                        echo htmlspecialchars($line) . "\n";
                    }
                }
                ?></pre>
            <?php else: ?>
                <div class="success">✅ Nenhum erro encontrado</div>
            <?php endif; ?>
        </div>

        <!-- Banco de Dados -->
        <div class="section">
            <h2>8. Verificação no Banco de Dados</h2>
            <?php if (!empty($events)): ?>
                <div class="success">✅ Encontrados <?= count($events) ?> eventos no banco:</div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>event_id</th>
                            <th>event_type</th>
                            <th>status</th>
                            <th>created_at</th>
                            <th>message_id</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?= htmlspecialchars($event['id']) ?></td>
                            <td><?= htmlspecialchars(substr($event['event_id'], 0, 20)) ?>...</td>
                            <td><?= htmlspecialchars($event['event_type']) ?></td>
                            <td><span class="badge badge-<?= $event['status'] === 'queued' ? 'info' : ($event['status'] === 'failed' ? 'error' : 'success') ?>"><?= htmlspecialchars($event['status']) ?></span></td>
                            <td><?= htmlspecialchars($event['created_at']) ?></td>
                            <td><?= htmlspecialchars($event['message_id'] ?: 'NULL') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="error">❌ Nenhum evento encontrado no banco com correlation_id: <?= htmlspecialchars($correlationId) ?></div>
            <?php endif; ?>
        </div>

        <!-- Logs HUB_* Recentes (Diagnóstico) -->
        <?php if (!empty($logs['recent_hub_logs'])): ?>
        <div class="section">
            <h2>9. Logs HUB_* Recentes (Últimas 2 horas) - Diagnóstico</h2>
            <div class="info" style="margin-bottom: 10px;">
                Mostrando logs HUB_* recentes para verificar se os logs estão sendo gerados corretamente.
            </div>
            <div class="success">✅ Encontrados <?= count($logs['recent_hub_logs']) ?> logs HUB_* recentes:</div>
            <pre style="max-height: 400px; overflow-y: auto;"><?php
            foreach ($logs['recent_hub_logs'] as $line) {
                // Destaca por tipo
                if (stripos($line, 'HUB_WEBHOOK_IN') !== false) {
                    echo '<span class="success">' . htmlspecialchars($line) . '</span>' . "\n";
                } elseif (stripos($line, 'HUB_MSG_SAVE') !== false) {
                    echo '<span class="success">' . htmlspecialchars($line) . '</span>' . "\n";
                } elseif (stripos($line, 'HUB_MSG_DROP') !== false) {
                    echo '<span class="warning">' . htmlspecialchars($line) . '</span>' . "\n";
                } else {
                    echo htmlspecialchars($line) . "\n";
                }
            }
            ?></pre>
        </div>
        <?php else: ?>
        <div class="section">
            <h2>9. Logs HUB_* Recentes (Últimas 2 horas) - Diagnóstico</h2>
            <div class="error">❌ Nenhum log HUB_* encontrado nas últimas 2 horas</div>
            <div class="warning" style="margin-top: 10px;">
                <strong>⚠️ Problema identificado:</strong> Os logs HUB_* não estão sendo gerados ou não estão sendo escritos no arquivo de log.<br><br>
                <strong>Possíveis causas:</strong><br>
                • Os logs não estão sendo gerados pelo código (error_log() não está sendo chamado)<br>
                • O arquivo de log não está configurado corretamente no PHP<br>
                • Os logs estão sendo escritos em outro local (verificar ini_get('error_log'))<br>
                • O error_log do PHP não está apontando para o arquivo correto<br>
                • Os logs podem estar em outro formato (sem prefixo [HUB_*])
            </div>
            
            <?php if (!empty($logs['recent_any_logs'])): ?>
            <div style="margin-top: 15px;">
                <div class="info">📋 Últimos logs relacionados a webhook/whatsapp (últimas 50 linhas do arquivo):</div>
                <pre style="max-height: 300px; overflow-y: auto; font-size: 11px;"><?php
                foreach ($logs['recent_any_logs'] as $line) {
                    echo htmlspecialchars($line) . "\n";
                }
                ?></pre>
                <div class="info" style="margin-top: 10px; font-size: 12px;">
                    <strong>Nota:</strong> Estes são logs gerais. Se não aparecerem logs HUB_*, significa que os logs instrumentados não estão sendo gerados.
                </div>
            </div>
            <?php else: ?>
            <div class="warning" style="margin-top: 15px;">
                ❌ Também não foram encontrados logs gerais relacionados a webhook/whatsapp nas últimas 50 linhas do arquivo.
            </div>
            <?php endif; ?>
            <?php if (isset($logFileInfo) && $logFileInfo): ?>
            <div class="info" style="margin-top: 10px;">
                <strong>Informações do arquivo de log:</strong><br>
                • Caminho: <?= htmlspecialchars($logFileInfo['path']) ?><br>
                • Existe: <?= $logFileInfo['exists'] ? '✅ Sim' : '❌ Não' ?><br>
                • Legível: <?= $logFileInfo['readable'] ? '✅ Sim' : '❌ Não' ?><br>
                • Tamanho: <?= $logFileInfo['size'] ? number_format($logFileInfo['size'] / 1024, 2) . ' KB' : '0 KB' ?><br>
                • Modificado: <?= $logFileInfo['modified'] ?: 'N/A' ?><br>
                • Linhas totais: <?= $logFileInfo['lines'] ?: 'N/A' ?><br><br>
                <strong>Configuração PHP error_log:</strong><br>
                • ini_get('error_log'): <?= htmlspecialchars(ini_get('error_log') ?: 'NÃO CONFIGURADO (usa padrão do sistema)') ?><br>
                • log_errors: <?= ini_get('log_errors') ? '✅ Habilitado' : '❌ Desabilitado' ?><br>
                • error_log está configurado: <?= ini_get('error_log') ? '✅ Sim' : '⚠️ Não (usa padrão)' ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Resumo -->
        <div class="section">
            <h2>📊 Resumo</h2>
            <div class="info">
                <strong>correlation_id:</strong> <?= htmlspecialchars($correlationId) ?><br>
                <strong>Horário do teste:</strong> ~<?= htmlspecialchars($testTime) ?> (tente também 19:35 para UTC)<br>
                <strong>Container:</strong> <?= htmlspecialchars($containerName) ?><br>
                <strong>Docker:</strong> <?= $dockerAvailable ? '✅ Disponível' : '❌ Não disponível' ?><br>
                <?php if (isset($logFileInfo) && $logFileInfo): ?>
                <strong>Arquivo de log:</strong> <?= htmlspecialchars($logFileInfo['path']) ?><br>
                <strong>Tamanho do log:</strong> <?= number_format($logFileInfo['size'] / 1024, 2) ?> KB<br>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="<?= pixelhub_url('/settings/whatsapp-gateway/diagnostic') ?>" class="btn btn-primary">← Voltar para Diagnóstico</a>
        </div>
    </div>
</body>
</html>

