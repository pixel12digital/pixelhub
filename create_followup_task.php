<?php
/**
 * Script para criar tarefa de follow-up de cobrança
 * Uso: php create_followup_task.php
 */

require 'vendor/autoload.php';
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load(__DIR__);
$db = \PixelHub\Core\DB::getConnection();

// Dados da tarefa
$tenantId = 71; // Beleza ZonaSul
$titulo = "Verificar retorno de cobrança crítica - Beleza ZonaSul";
$descricao = "Verificar se o cliente Luiz Antônio (Beleza ZonaSul) retornou sobre as 9 faturas vencidas (R$ 329,00).\n\n";
$descricao .= "Ações:\n";
$descricao .= "1. Se regularizou: confirmar pagamento e reativar serviços\n";
$descricao .= "2. Se não retornou: remover site da hospedagem conforme avisado\n";
$descricao .= "3. Se pediu negociação: avaliar proposta e definir próximos passos\n\n";
$descricao .= "Prazo dado ao cliente: 48 horas (até sexta-feira 14h)";

// Data de vencimento: sexta-feira 14h (27/02/2026 14:00)
$dataVencimento = '2026-02-27 14:00:00';

// Prioridade: alta (cobrança crítica)
$prioridade = 'alta';

// Status: pendente
$status = 'pendente';

// Usuário responsável (você - JP Traslados)
$userId = 1; // Ajuste se necessário

try {
    $stmt = $db->prepare("
        INSERT INTO tasks (
            tenant_id,
            titulo,
            descricao,
            data_vencimento,
            prioridade,
            status,
            assigned_to,
            created_by,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $tenantId,
        $titulo,
        $descricao,
        $dataVencimento,
        $prioridade,
        $status,
        $userId,
        $userId
    ]);
    
    $taskId = $db->lastInsertId();
    
    echo "✅ Tarefa criada com sucesso!\n\n";
    echo "ID da tarefa: {$taskId}\n";
    echo "Título: {$titulo}\n";
    echo "Vencimento: {$dataVencimento}\n";
    echo "Prioridade: {$prioridade}\n";
    echo "Tenant: Beleza ZonaSul (ID: {$tenantId})\n\n";
    echo "A tarefa aparecerá na sua lista de tarefas e você receberá notificação no prazo.\n";
    
} catch (\Exception $e) {
    echo "❌ Erro ao criar tarefa:\n";
    echo $e->getMessage() . "\n";
}
