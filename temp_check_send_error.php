<?php
// Script para diagnosticar erro 400 no envio de mensagens

echo "=== DIAGNÓSTICO DE ERRO 400 NO ENVIO ===\n\n";

// Lê últimas linhas do log
$logFile = __DIR__ . '/logs/pixelhub.log';

if (file_exists($logFile)) {
    echo "1. Últimas 50 linhas do log relacionadas a CommunicationHub::send:\n";
    echo str_repeat('-', 80) . "\n";
    
    $lines = file($logFile);
    $relevantLines = [];
    
    foreach ($lines as $line) {
        if (stripos($line, 'CommunicationHub::send') !== false || 
            stripos($line, 'STAGE=') !== false ||
            stripos($line, 'error') !== false ||
            stripos($line, 'POST DATA:') !== false) {
            $relevantLines[] = $line;
        }
    }
    
    // Pega últimas 50 linhas relevantes
    $relevantLines = array_slice($relevantLines, -50);
    
    foreach ($relevantLines as $line) {
        echo $line;
    }
    
    echo "\n" . str_repeat('-', 80) . "\n\n";
} else {
    echo "⚠️ Arquivo de log não encontrado: {$logFile}\n\n";
}

// Verifica estrutura da requisição esperada
echo "2. Estrutura esperada da requisição:\n";
echo "   - channel: 'whatsapp'\n";
echo "   - to: telefone (ex: 5547999291994)\n";
echo "   - message: texto da mensagem\n";
echo "   - tenant_id: ID do cliente (opcional se thread_id existe)\n";
echo "   - channel_id: ID do canal WhatsApp (opcional)\n";
echo "   - thread_id: ID da conversa (opcional para nova conversa)\n\n";

// Verifica se há validação que pode estar falhando
echo "3. Possíveis causas de erro 400:\n";
echo "   - Campo obrigatório faltando (channel, to, message)\n";
echo "   - Formato de telefone inválido\n";
echo "   - tenant_id ou channel_id inválido\n";
echo "   - Sessão WhatsApp não encontrada\n";
echo "   - Validação de autenticação falhou\n\n";

echo "=== FIM DO DIAGNÓSTICO ===\n";
