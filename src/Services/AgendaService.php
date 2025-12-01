<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar agenda e blocos de tempo
 */
class AgendaService
{
    /**
     * Cache estático para verificação da existência da coluna deleted_at
     * @var bool|null
     */
    private static $hasDeletedAtColumn = null;
    
    /**
     * Verifica se a coluna deleted_at existe na tabela tasks (com cache)
     * 
     * @param \PDO $db Conexão com o banco
     * @return bool True se a coluna existe, false caso contrário
     */
    private static function hasDeletedAtColumn(\PDO $db): bool
    {
        if (self::$hasDeletedAtColumn !== null) {
            return self::$hasDeletedAtColumn;
        }
        
        try {
            $stmt = $db->query("SHOW COLUMNS FROM tasks LIKE 'deleted_at'");
            self::$hasDeletedAtColumn = $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            self::$hasDeletedAtColumn = false;
        }
        
        return self::$hasDeletedAtColumn;
    }
    /**
     * Gera blocos do dia a partir do template
     * 
     * @param \DateTime $data Data para gerar os blocos
     * @return int Número de blocos criados
     */
    public static function generateDailyBlocks(\DateTime $data): int
    {
        $db = DB::getConnection();
        
        // Timezone America/Sao_Paulo
        $data->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
        $dataStr = $data->format('Y-m-d');
        
        // Verifica se já existem blocos para essa data
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM agenda_blocks WHERE data = ?");
        $stmt->execute([$dataStr]);
        $result = $stmt->fetch();
        
        if ($result && (int)$result['count'] > 0) {
            // Já existem blocos para essa data
            return 0;
        }
        
        // Obtém o dia da semana (1=Segunda, 7=Domingo)
        $diaSemana = (int)$data->format('N');
        // Obtém também o formato w (0=Domingo, 6=Sábado) para verificar fim de semana
        $weekday = (int)$data->format('w'); // 0 = domingo, 6 = sábado
        
        // Busca templates para esse dia da semana
        $stmt = $db->prepare("
            SELECT t.*, bt.nome as tipo_nome, bt.codigo as tipo_codigo
            FROM agenda_block_templates t
            INNER JOIN agenda_block_types bt ON t.tipo_id = bt.id
            WHERE t.dia_semana = ? AND t.ativo = 1
            ORDER BY t.hora_inicio ASC
        ");
        $stmt->execute([$diaSemana]);
        $templates = $stmt->fetchAll();
        
        if (empty($templates)) {
            // Verifica se é fim de semana (sábado ou domingo)
            if ($weekday === 0 || $weekday === 6) {
                // Fim de semana sem template = dia livre, não é erro
                return 0; // Retorna 0 blocos criados, mas sem erro
            } else {
                // Dia útil sem template = erro de configuração
                throw new \RuntimeException(
                    'Não há um modelo de agenda configurado para este dia da semana. ' .
                    'Ajuste os modelos em Configurações → Agenda → Modelos de Blocos.'
                );
            }
        }
        
        $created = 0;
        $stmt = $db->prepare("
            INSERT INTO agenda_blocks 
            (data, hora_inicio, hora_fim, tipo_id, status, duracao_planejada, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'planned', ?, NOW(), NOW())
        ");
        
        foreach ($templates as $template) {
            // Calcula duração em minutos
            $inicio = new \DateTime($dataStr . ' ' . $template['hora_inicio']);
            $fim = new \DateTime($dataStr . ' ' . $template['hora_fim']);
            $diff = $inicio->diff($fim);
            $duracaoMinutos = ($diff->h * 60) + $diff->i;
            
            try {
                $stmt->execute([
                    $dataStr,
                    $template['hora_inicio'],
                    $template['hora_fim'],
                    $template['tipo_id'],
                    $duracaoMinutos,
                ]);
                $created++;
            } catch (\PDOException $e) {
                // Ignora erro de duplicidade (unique constraint)
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
        
        return $created;
    }
    
    /**
     * Garante que todos os blocos da semana existam baseados nos templates ativos
     * 
     * OTIMIZADO: Busca todos os blocos da semana de uma vez e compara em memória,
     * evitando múltiplas queries por template.
     * 
     * Para cada dia da semana entre $weekStart e $weekEnd, verifica se existem blocos
     * para cada template ativo correspondente. Cria apenas os blocos faltantes.
     * 
     * Não altera blocos já existentes e não recria blocos deletados manualmente.
     * 
     * @param \DateTimeInterface $weekStart Data de início da semana (segunda-feira)
     * @param \DateTimeInterface $weekEnd Data de fim da semana (domingo)
     * @return int Número total de blocos criados
     */
    public static function ensureBlocksForWeek(\DateTimeInterface $weekStart, \DateTimeInterface $weekEnd): int
    {
        $db = DB::getConnection();
        $timezone = new \DateTimeZone('America/Sao_Paulo');
        
        // Garante timezone
        if ($weekStart instanceof \DateTimeImmutable) {
            $weekStart = $weekStart->setTimezone($timezone);
        } else {
            $weekStart = clone $weekStart;
            $weekStart->setTimezone($timezone);
        }
        
        if ($weekEnd instanceof \DateTimeImmutable) {
            $weekEnd = $weekEnd->setTimezone($timezone);
        } else {
            $weekEnd = clone $weekEnd;
            $weekEnd->setTimezone($timezone);
        }
        
        $weekStartStr = $weekStart->format('Y-m-d');
        $weekEndStr = $weekEnd->format('Y-m-d');
        
        // Busca todos os templates ativos
        $stmt = $db->query("
            SELECT t.*, bt.nome as tipo_nome, bt.codigo as tipo_codigo
            FROM agenda_block_templates t
            INNER JOIN agenda_block_types bt ON t.tipo_id = bt.id
            WHERE t.ativo = 1
            ORDER BY t.dia_semana ASC, t.hora_inicio ASC
        ");
        $templates = $stmt->fetchAll();
        
        if (empty($templates)) {
            // Sem templates ativos, não há nada para criar
            return 0;
        }
        
        // OTIMIZAÇÃO: Busca todos os blocos da semana de uma vez
        $stmt = $db->prepare("
            SELECT data, tipo_id, hora_inicio, hora_fim
            FROM agenda_blocks
            WHERE data >= ? AND data <= ?
        ");
        $stmt->execute([$weekStartStr, $weekEndStr]);
        $blocosExistentes = $stmt->fetchAll();
        
        // Cria um índice dos blocos existentes para busca rápida
        $blocosIndex = [];
        foreach ($blocosExistentes as $bloco) {
            $chave = $bloco['data'] . '|' . $bloco['tipo_id'] . '|' . $bloco['hora_inicio'] . '|' . $bloco['hora_fim'];
            $blocosIndex[$chave] = true;
        }
        
        // Agrupa templates por dia da semana para facilitar busca
        $templatesPorDia = [];
        foreach ($templates as $template) {
            $diaSemana = (int)$template['dia_semana'];
            if (!isset($templatesPorDia[$diaSemana])) {
                $templatesPorDia[$diaSemana] = [];
            }
            $templatesPorDia[$diaSemana][] = $template;
        }
        
        // Prepara statement para inserção (reutilizável)
        $insertStmt = $db->prepare("
            INSERT INTO agenda_blocks 
            (data, hora_inicio, hora_fim, tipo_id, status, duracao_planejada, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'planned', ?, NOW(), NOW())
        ");
        
        $totalCriados = 0;
        $blocosParaInserir = [];
        
        // Cria uma cópia mutável para iteração
        if ($weekStart instanceof \DateTimeImmutable) {
            $currentDate = \DateTime::createFromImmutable($weekStart);
        } else {
            $currentDate = clone $weekStart;
        }
        
        // Converte weekEnd para DateTime se necessário para comparação
        if ($weekEnd instanceof \DateTimeImmutable) {
            $weekEndComparacao = \DateTime::createFromImmutable($weekEnd);
        } else {
            $weekEndComparacao = clone $weekEnd;
        }
        
        // Itera sobre cada dia da semana
        while ($currentDate <= $weekEndComparacao) {
            $dataStr = $currentDate->format('Y-m-d');
            
            // Obtém o dia da semana (1=Segunda, 7=Domingo)
            $diaSemana = (int)$currentDate->format('N');
            
            // Busca templates para esse dia da semana
            $templatesDoDia = $templatesPorDia[$diaSemana] ?? [];
            
            // Se não há templates para esse dia, pula
            if (empty($templatesDoDia)) {
                $currentDate->modify('+1 day');
                continue;
            }
            
            // Para cada template, verifica se o bloco já existe (em memória)
            foreach ($templatesDoDia as $template) {
                $chave = $dataStr . '|' . $template['tipo_id'] . '|' . $template['hora_inicio'] . '|' . $template['hora_fim'];
                
                if (isset($blocosIndex[$chave])) {
                    // Bloco já existe, não cria
                    continue;
                }
                
                // VALIDAÇÃO: Verifica se já existe um bloco com horário sobreposto para o mesmo tipo
                // Isso evita criar blocos duplicados quando um bloco foi editado manualmente
                $horaInicioTemplate = strtotime($template['hora_inicio']);
                $horaFimTemplate = strtotime($template['hora_fim']);
                $blocoSobreposto = false;
                
                foreach ($blocosExistentes as $blocoExistente) {
                    if ($blocoExistente['data'] === $dataStr && $blocoExistente['tipo_id'] == $template['tipo_id']) {
                        $horaInicioExistente = strtotime($blocoExistente['hora_inicio']);
                        $horaFimExistente = strtotime($blocoExistente['hora_fim']);
                        
                        // Verifica sobreposição: se os horários se sobrepõem (mesmo que parcialmente)
                        // Considera sobreposição se a diferença entre os horários é menor que 10 minutos
                        $diffInicio = abs($horaInicioTemplate - $horaInicioExistente) / 60; // diferença em minutos
                        $diffFim = abs($horaFimTemplate - $horaFimExistente) / 60;
                        
                        // Se os horários são muito próximos (menos de 10 minutos) e o fim é igual, considera sobreposto
                        if ($diffInicio < 10 && $blocoExistente['hora_fim'] === $template['hora_fim']) {
                            $blocoSobreposto = true;
                            break;
                        }
                    }
                }
                
                if ($blocoSobreposto) {
                    // Já existe um bloco com horário sobreposto, não cria duplicado
                    continue;
                }
                
                // Calcula duração em minutos
                $inicio = new \DateTime($dataStr . ' ' . $template['hora_inicio'], $timezone);
                $fim = new \DateTime($dataStr . ' ' . $template['hora_fim'], $timezone);
                $diff = $inicio->diff($fim);
                $duracaoMinutos = ($diff->h * 60) + $diff->i;
                
                // Adiciona à lista de blocos para inserir
                $blocosParaInserir[] = [
                    'data' => $dataStr,
                    'hora_inicio' => $template['hora_inicio'],
                    'hora_fim' => $template['hora_fim'],
                    'tipo_id' => $template['tipo_id'],
                    'duracao_planejada' => $duracaoMinutos,
                ];
                
                // Marca como existente no índice para evitar duplicatas na mesma execução
                $blocosIndex[$chave] = true;
            }
            
            // Avança para o próximo dia
            $currentDate->modify('+1 day');
        }
        
        // Se não há blocos para criar, retorna imediatamente
        if (empty($blocosParaInserir)) {
            return 0;
        }
        
        // Insere todos os blocos faltantes (bulk insert)
        foreach ($blocosParaInserir as $bloco) {
            try {
                $insertStmt->execute([
                    $bloco['data'],
                    $bloco['hora_inicio'],
                    $bloco['hora_fim'],
                    $bloco['tipo_id'],
                    $bloco['duracao_planejada'],
                ]);
                $totalCriados++;
            } catch (\PDOException $e) {
                // Ignora erro de duplicidade (pode ter sido criado entre a verificação e a inserção)
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    // Outro tipo de erro, loga mas continua
                    error_log("Erro ao criar bloco recorrente: " . $e->getMessage());
                }
            }
        }
        
        return $totalCriados;
    }
    
    
    /**
     * Busca um bloco por ID com informações completas
     * 
     * @param int $id ID do bloco
     * @return array|null Dados do bloco ou null se não encontrado
     */
    public static function getBlockById(int $id): ?array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                b.*,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo,
                bt.cor_hex as tipo_cor,
                p.name as projeto_foco_nome,
                t_focus.title as focus_task_title,
                t_focus.status as focus_task_status
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            LEFT JOIN projects p ON b.projeto_foco_id = p.id
            LEFT JOIN tasks t_focus ON b.focus_task_id = t_focus.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Atualiza um bloco existente
     * 
     * @param int $id ID do bloco
     * @param array $dados Dados para atualizar (hora_inicio, hora_fim, tipo_id)
     * @return void
     * @throws \RuntimeException Se houver erro de validação
     */
    public static function updateBlock(int $id, array $dados): void
    {
        $db = DB::getConnection();
        
        // Busca o bloco atual
        $bloco = self::getBlockById($id);
        if (!$bloco) {
            throw new \RuntimeException('Bloco não encontrado');
        }
        
        // Validações
        $horaInicio = isset($dados['hora_inicio']) ? trim($dados['hora_inicio']) : $bloco['hora_inicio'];
        $horaFim = isset($dados['hora_fim']) ? trim($dados['hora_fim']) : $bloco['hora_fim'];
        $tipoId = isset($dados['tipo_id']) ? (int)$dados['tipo_id'] : $bloco['tipo_id'];
        
        // Valida horário de início < horário de fim
        if ($horaInicio >= $horaFim) {
            throw new \RuntimeException('Horário de início deve ser menor que o horário de fim.');
        }
        
        // Verifica se há conflito com outro bloco na mesma data (exceto o próprio bloco)
        // Dois intervalos [a1, b1] e [a2, b2] se sobrepõem se: a1 < b2 AND a2 < b1
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM agenda_blocks
            WHERE data = ?
            AND id != ?
            AND hora_inicio < ? 
            AND hora_fim > ?
        ");
        $stmt->execute([
            $bloco['data'],
            $id,
            $horaFim,    // Outro bloco termina depois do novo início
            $horaInicio // Outro bloco começa antes do novo fim
        ]);
        $conflito = $stmt->fetch();
        
        if ($conflito && (int)$conflito['count'] > 0) {
            throw new \RuntimeException('Já existe um bloco nesse horário para este dia.');
        }
        
        // Calcula nova duração
        $inicio = new \DateTime($bloco['data'] . ' ' . $horaInicio);
        $fim = new \DateTime($bloco['data'] . ' ' . $horaFim);
        $diff = $inicio->diff($fim);
        $duracaoMinutos = ($diff->h * 60) + $diff->i;
        
        // Horários reais (opcionais)
        $horaInicioReal = isset($dados['hora_inicio_real']) && $dados['hora_inicio_real'] !== '' ? trim($dados['hora_inicio_real']) : null;
        $horaFimReal = isset($dados['hora_fim_real']) && $dados['hora_fim_real'] !== '' ? trim($dados['hora_fim_real']) : null;
        
        // Monta query dinamicamente para incluir horários reais se fornecidos
        $fields = ['hora_inicio = ?', 'hora_fim = ?', 'tipo_id = ?', 'duracao_planejada = ?', 'updated_at = NOW()'];
        $values = [$horaInicio, $horaFim, $tipoId, $duracaoMinutos];
        
        if ($horaInicioReal !== null) {
            $fields[] = 'hora_inicio_real = ?';
            $values[] = $horaInicioReal;
        }
        
        if ($horaFimReal !== null) {
            $fields[] = 'hora_fim_real = ?';
            $values[] = $horaFimReal;
        }
        
        $values[] = $id;
        
        // Atualiza o bloco
        $stmt = $db->prepare("
            UPDATE agenda_blocks 
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $stmt->execute($values);
    }
    
    /**
     * Cria um bloco manual extra (não baseado em template)
     * 
     * @param \DateTime $data Data do bloco
     * @param array $dados Dados do bloco (hora_inicio, hora_fim, tipo_id)
     * @return int ID do bloco criado
     * @throws \RuntimeException Se houver erro de validação
     */
    public static function createManualBlock(\DateTime $data, array $dados): int
    {
        $db = DB::getConnection();
        
        // Timezone
        $data->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
        $dataStr = $data->format('Y-m-d');
        
        // Validações
        $horaInicio = trim($dados['hora_inicio'] ?? '');
        $horaFim = trim($dados['hora_fim'] ?? '');
        $tipoId = isset($dados['tipo_id']) ? (int)$dados['tipo_id'] : 0;
        
        if (empty($horaInicio) || empty($horaFim)) {
            throw new \RuntimeException('Horário de início e fim são obrigatórios.');
        }
        
        if ($tipoId <= 0) {
            throw new \RuntimeException('Tipo de bloco é obrigatório.');
        }
        
        // Valida horário de início < horário de fim
        if ($horaInicio >= $horaFim) {
            throw new \RuntimeException('Horário de início deve ser menor que o horário de fim.');
        }
        
        // Verifica se há conflito com outro bloco na mesma data
        // Dois intervalos [a1, b1] e [a2, b2] se sobrepõem se: a1 < b2 AND a2 < b1
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM agenda_blocks
            WHERE data = ?
            AND hora_inicio < ? 
            AND hora_fim > ?
        ");
        $stmt->execute([
            $dataStr,
            $horaFim,    // Outro bloco termina depois do novo início
            $horaInicio  // Outro bloco começa antes do novo fim
        ]);
        $conflito = $stmt->fetch();
        
        if ($conflito && (int)$conflito['count'] > 0) {
            throw new \RuntimeException('Já existe um bloco nesse horário para este dia.');
        }
        
        // Calcula duração
        $inicio = new \DateTime($dataStr . ' ' . $horaInicio);
        $fim = new \DateTime($dataStr . ' ' . $horaFim);
        $diff = $inicio->diff($fim);
        $duracaoMinutos = ($diff->h * 60) + $diff->i;
        
        // Insere o bloco
        $stmt = $db->prepare("
            INSERT INTO agenda_blocks 
            (data, hora_inicio, hora_fim, tipo_id, status, duracao_planejada, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'planned', ?, NOW(), NOW())
        ");
        $stmt->execute([
            $dataStr,
            $horaInicio,
            $horaFim,
            $tipoId,
            $duracaoMinutos,
        ]);
        
        return (int)$db->lastInsertId();
    }
    
    /**
     * Retorna os blocos agrupados por data no intervalo [dataInicio, dataFim]
     * 
     * @param \DateTimeInterface $dataInicio
     * @param \DateTimeInterface $dataFim
     * @return array<string, array> Ex.: ['2025-12-01' => [blocos...], '2025-12-02' => [blocos...], ...]
     */
    public static function getBlocksForPeriod(\DateTimeInterface $dataInicio, \DateTimeInterface $dataFim): array
    {
        $db = DB::getConnection();
        
        // Garante timezone (cria novas instâncias se necessário para não modificar as originais)
        $timezone = new \DateTimeZone('America/Sao_Paulo');
        
        if ($dataInicio instanceof \DateTimeImmutable) {
            $dataInicio = $dataInicio->setTimezone($timezone);
        } elseif ($dataInicio instanceof \DateTime) {
            $dataInicio = clone $dataInicio;
            $dataInicio->setTimezone($timezone);
        }
        
        if ($dataFim instanceof \DateTimeImmutable) {
            $dataFim = $dataFim->setTimezone($timezone);
        } elseif ($dataFim instanceof \DateTime) {
            $dataFim = clone $dataFim;
            $dataFim->setTimezone($timezone);
        }
        
        $dataInicioStr = $dataInicio->format('Y-m-d');
        $dataFimStr = $dataFim->format('Y-m-d');
        
        // Verifica se a coluna deleted_at existe na tabela tasks (com cache)
        $hasDeletedAt = self::hasDeletedAtColumn($db);
        
        // Subquery de contagem: usa os mesmos filtros de getTasksByBlock()
        // COUNT(DISTINCT) evita duplicidades na pivot
        // Filtra tarefas soft-deletadas se a coluna existir
        if ($hasDeletedAt) {
            $tasksCountSubquery = "
                (SELECT COUNT(DISTINCT abt.task_id)
                 FROM agenda_block_tasks abt
                 INNER JOIN tasks t ON abt.task_id = t.id
                 WHERE abt.bloco_id = b.id
                 AND t.deleted_at IS NULL
                )";
        } else {
            $tasksCountSubquery = "
                (SELECT COUNT(DISTINCT abt.task_id)
                 FROM agenda_block_tasks abt
                 INNER JOIN tasks t ON abt.task_id = t.id
                 WHERE abt.bloco_id = b.id
                )";
        }
        
        // Constrói a query completa antes de preparar
        $sql = "
            SELECT 
                b.*,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo,
                bt.cor_hex as tipo_cor_hex,
                p.name as projeto_foco_nome,
                t_focus.title as focus_task_title,
                t_focus.status as focus_task_status,
                " . $tasksCountSubquery . " as total_tarefas
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            LEFT JOIN projects p ON b.projeto_foco_id = p.id
            LEFT JOIN tasks t_focus ON b.focus_task_id = t_focus.id
            WHERE b.data >= ? AND b.data <= ?
            ORDER BY b.data ASC, b.hora_inicio ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$dataInicioStr, $dataFimStr]);
        
        $blocos = $stmt->fetchAll();
        
        // Agrupa por data
        $resultado = [];
        foreach ($blocos as $bloco) {
            $dataIso = $bloco['data'];
            if (!isset($resultado[$dataIso])) {
                $resultado[$dataIso] = [];
            }
            $resultado[$dataIso][] = $bloco;
        }
        
        return $resultado;
    }
    
    /**
     * Retorna blocos disponíveis para agendamento de uma tarefa
     * 
     * @param int|null $tipoBlocoId ID do tipo de bloco (opcional)
     * @param \DateTimeInterface|null $dataInicio Data de início do período (padrão: hoje)
     * @param \DateTimeInterface|null $dataFim Data de fim do período (padrão: hoje + 30 dias)
     * @param int|null $taskId ID da tarefa (para verificar se já está vinculada)
     * @return array Lista de blocos disponíveis
     */
    public static function getAvailableBlocks(
        ?int $tipoBlocoId = null,
        ?\DateTimeInterface $dataInicio = null,
        ?\DateTimeInterface $dataFim = null,
        ?int $taskId = null
    ): array {
        $db = DB::getConnection();
        $timezone = new \DateTimeZone('America/Sao_Paulo');
        
        // Define data de início (padrão: hoje)
        if ($dataInicio === null) {
            $dataInicio = new \DateTimeImmutable('today', $timezone);
        } else {
            if ($dataInicio instanceof \DateTimeImmutable) {
                $dataInicio = $dataInicio->setTimezone($timezone);
            } else {
                $dataInicio = clone $dataInicio;
                $dataInicio->setTimezone($timezone);
            }
        }
        
        // Define data de fim (padrão: hoje + 30 dias)
        if ($dataFim === null) {
            $dataFim = $dataInicio->modify('+30 days');
        } else {
            if ($dataFim instanceof \DateTimeImmutable) {
                $dataFim = $dataFim->setTimezone($timezone);
            } else {
                $dataFim = clone $dataFim;
                $dataFim->setTimezone($timezone);
            }
        }
        
        $dataInicioStr = $dataInicio->format('Y-m-d');
        $dataFimStr = $dataFim->format('Y-m-d');
        
        // Obtém hora atual para verificar se bloco está em andamento (apenas para exibição)
        $now = new \DateTimeImmutable('now', $timezone);
        
        // Verifica se a coluna deleted_at existe na tabela tasks (com cache)
        $hasDeletedAt = self::hasDeletedAtColumn($db);
        
        // Subquery de contagem: usa os mesmos filtros de getTasksByBlock()
        // COUNT(DISTINCT) evita duplicidades na pivot
        // Filtra tarefas soft-deletadas se a coluna existir
        if ($hasDeletedAt) {
            $tasksCountSubquery = "
                (SELECT COUNT(DISTINCT abt.task_id)
                 FROM agenda_block_tasks abt
                 INNER JOIN tasks t ON abt.task_id = t.id
                 WHERE abt.bloco_id = b.id
                 AND t.deleted_at IS NULL
                )";
        } else {
            $tasksCountSubquery = "
                (SELECT COUNT(DISTINCT abt.task_id)
                 FROM agenda_block_tasks abt
                 INNER JOIN tasks t ON abt.task_id = t.id
                 WHERE abt.bloco_id = b.id
                )";
        }
        
        // Monta query
        $sql = "
            SELECT 
                b.id,
                b.data,
                b.hora_inicio,
                b.hora_fim,
                b.status,
                b.hora_fim_real,
                bt.id as tipo_id,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo,
                bt.cor_hex as tipo_cor_hex,
                " . $tasksCountSubquery . " as tasks_count
        ";
        
        // Verifica se a tarefa já está vinculada a este bloco
        if ($taskId !== null) {
            $sql .= ",
                CASE WHEN EXISTS (
                    SELECT 1 FROM agenda_block_tasks 
                    WHERE bloco_id = b.id AND task_id = ?
                ) THEN 1 ELSE 0 END as already_linked
            ";
        } else {
            $sql .= ", 0 as already_linked";
        }
        
        $sql .= "
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE b.data >= ? AND b.data <= ?
            AND b.status IN ('planned', 'ongoing')
            AND (b.hora_fim_real IS NULL)
        ";
        
        $params = [];
        if ($taskId !== null) {
            $params[] = $taskId;
        }
        $params[] = $dataInicioStr;
        $params[] = $dataFimStr;
        
        // Filtro por tipo de bloco
        if ($tipoBlocoId !== null && $tipoBlocoId > 0) {
            $sql .= " AND b.tipo_id = ?";
            $params[] = $tipoBlocoId;
        }
        
        $sql .= " ORDER BY b.data ASC, b.hora_inicio ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $blocos = $stmt->fetchAll();
        
        // Formata os blocos para retorno
        $resultado = [];
        foreach ($blocos as $bloco) {
            // Formata data
            $dataObj = new \DateTimeImmutable($bloco['data'], $timezone);
            $nomesDias = [
                0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 
                4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'
            ];
            $diaSemana = (int)$dataObj->format('w');
            $dataFormatada = $nomesDias[$diaSemana] . ' ' . $dataObj->format('d/m/Y');
            
            // Verifica se o bloco está em andamento
            $horaInicioCompleta = $bloco['data'] . ' ' . $bloco['hora_inicio'];
            $horaFimCompleta = $bloco['data'] . ' ' . $bloco['hora_fim'];
            $inicioObj = new \DateTimeImmutable($horaInicioCompleta, $timezone);
            $fimObj = new \DateTimeImmutable($horaFimCompleta, $timezone);
            
            $isCurrent = ($inicioObj <= $now && $fimObj >= $now);
            
            // Busca tarefas de exemplo (até 2) para este bloco
            $sampleTasks = [];
            $tasksCount = (int)$bloco['tasks_count'];
            if ($tasksCount > 0) {
                // Tenta primeiro com deleted_at (se a coluna existir)
                try {
                    $stmtTasks = $db->prepare("
                        SELECT t.title
                        FROM agenda_block_tasks abt
                        INNER JOIN tasks t ON abt.task_id = t.id
                        WHERE abt.bloco_id = ? AND t.deleted_at IS NULL
                        ORDER BY t.id
                        LIMIT 2
                    ");
                    $stmtTasks->execute([$bloco['id']]);
                } catch (\PDOException $e) {
                    // Se deu erro, tenta sem a condição deleted_at
                    $stmtTasks = $db->prepare("
                        SELECT t.title
                        FROM agenda_block_tasks abt
                        INNER JOIN tasks t ON abt.task_id = t.id
                        WHERE abt.bloco_id = ?
                        ORDER BY t.id
                        LIMIT 2
                    ");
                    $stmtTasks->execute([$bloco['id']]);
                }
                $tasks = $stmtTasks->fetchAll();
                foreach ($tasks as $task) {
                    $sampleTasks[] = $task['title'];
                }
            }
            
            $resultado[] = [
                'id' => (int)$bloco['id'],
                'data' => $bloco['data'],
                'data_formatada' => $dataFormatada,
                'hora_inicio' => $bloco['hora_inicio'],
                'hora_fim' => $bloco['hora_fim'],
                'status' => $bloco['status'],
                'tipo_id' => (int)$bloco['tipo_id'],
                'tipo_nome' => $bloco['tipo_nome'],
                'tipo_codigo' => $bloco['tipo_codigo'],
                'tipo_cor_hex' => $bloco['tipo_cor_hex'],
                'tasks_count' => $tasksCount,
                'sample_tasks' => $sampleTasks,
                'is_current' => $isCurrent,
                'already_linked' => isset($bloco['already_linked']) ? (int)$bloco['already_linked'] : 0,
            ];
        }
        
        return $resultado;
    }
    
    /**
     * Retorna estatísticas semanais por tipo de bloco
     * 
     * OTIMIZADO: Usa queries vetorizadas em vez de subqueries EXISTS para melhor performance.
     * 
     * Calcula horas totais, ocupadas e livres para cada tipo de bloco na semana especificada.
     * Semana = Segunda a Domingo (formato brasileiro).
     * 
     * @param \DateTimeInterface $weekStart Data de início da semana (segunda-feira)
     * @param \DateTimeInterface $weekEnd Data de fim da semana (domingo)
     * @return array Estatísticas por tipo de bloco + resumo geral
     */
    public static function getWeeklyStats(\DateTimeInterface $weekStart, \DateTimeInterface $weekEnd): array
    {
        $startTime = microtime(true);
        
        $db = DB::getConnection();
        $timezone = new \DateTimeZone('America/Sao_Paulo');
        
        // Garante timezone
        if ($weekStart instanceof \DateTimeImmutable) {
            $weekStart = $weekStart->setTimezone($timezone);
        } else {
            $weekStart = clone $weekStart;
            $weekStart->setTimezone($timezone);
        }
        
        if ($weekEnd instanceof \DateTimeImmutable) {
            $weekEnd = $weekEnd->setTimezone($timezone);
        } else {
            $weekEnd = clone $weekEnd;
            $weekEnd->setTimezone($timezone);
        }
        
        $weekStartStr = $weekStart->format('Y-m-d');
        $weekEndStr = $weekEnd->format('Y-m-d');
        
        // ============================================
        // QUERY 1: Busca todos os blocos da semana
        // ============================================
        $query1Start = microtime(true);
        $sql = "
            SELECT 
                b.id,
                b.data,
                b.tipo_id,
                b.duracao_planejada,
                b.status,
                TIME_TO_SEC(TIMEDIFF(b.hora_fim, b.hora_inicio)) / 3600.0 as horas
            FROM agenda_blocks b
            WHERE b.data BETWEEN ? AND ?
            AND b.status NOT IN ('CANCELADO', 'cancelled', 'cancelado')
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$weekStartStr, $weekEndStr]);
        $blocos = $stmt->fetchAll();
        $query1Time = microtime(true) - $query1Start;
        
        // Se não há blocos, retorna vazio
        if (empty($blocos)) {
            return [
                'stats_by_type' => [],
                'summary_totals' => [
                    'total_blocks' => 0,
                    'total_hours' => 0.0,
                    'occupied_blocks' => 0,
                    'occupied_hours' => 0.0,
                    'free_blocks' => 0,
                    'free_hours' => 0.0,
                    'occupancy_percent' => 0.0,
                ],
            ];
        }
        
        // ============================================
        // QUERY 2: Busca blocos com tarefas (query simples e rápida)
        // ============================================
        $query2Start = microtime(true);
        // Busca apenas blocos da semana que têm tarefas
        $sql = "
            SELECT DISTINCT abt.bloco_id
            FROM agenda_block_tasks abt
            INNER JOIN agenda_blocks b ON abt.bloco_id = b.id
            WHERE b.data BETWEEN ? AND ?
            AND b.status NOT IN ('CANCELADO', 'cancelled', 'cancelado')
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$weekStartStr, $weekEndStr]);
        $blocosComTarefas = $stmt->fetchAll();
        $query2Time = microtime(true) - $query2Start;
        
        // Cria um set (array associativo) para busca rápida O(1)
        $blocosComTarefasSet = [];
        foreach ($blocosComTarefas as $row) {
            $blocosComTarefasSet[(int)$row['bloco_id']] = true;
        }
        
        // ============================================
        // QUERY 3: Busca tipos de blocos ativos
        // ============================================
        $query3Start = microtime(true);
        $sql = "
            SELECT id, nome, codigo, cor_hex
            FROM agenda_block_types
            WHERE ativo = 1
            ORDER BY nome ASC
        ";
        $stmt = $db->query($sql);
        $tipos = $stmt->fetchAll();
        $query3Time = microtime(true) - $query3Start;
        
        // ============================================
        // PROCESSAMENTO EM PHP
        // ============================================
        $processStart = microtime(true);
        
        // Inicializa buckets por tipo
        $statsByType = [];
        $tiposMap = [];
        foreach ($tipos as $tipo) {
            $tipoId = (int)$tipo['id'];
            $tiposMap[$tipoId] = $tipo;
            $statsByType[$tipoId] = [
                'type_id' => $tipoId,
                'type_name' => $tipo['nome'],
                'type_code' => $tipo['codigo'],
                'type_color' => $tipo['cor_hex'] ?? '#cccccc',
                'total_blocks' => 0,
                'total_hours' => 0.0,
                'occupied_blocks' => 0,
                'occupied_hours' => 0.0,
                'free_blocks' => 0,
                'free_hours' => 0.0,
                'occupancy_percent' => 0.0,
            ];
        }
        
        // Processa cada bloco da semana
        foreach ($blocos as $bloco) {
            $tipoId = (int)$bloco['tipo_id'];
            $blocoId = (int)$bloco['id'];
            $horas = (float)$bloco['horas'];
            
            // Se o tipo não existe mais, pula (pode acontecer se tipo foi desativado)
            if (!isset($statsByType[$tipoId])) {
                continue;
            }
            
            // Incrementa horas totais e blocos totais
            $statsByType[$tipoId]['total_blocks']++;
            $statsByType[$tipoId]['total_hours'] += $horas;
            
            // Verifica se o bloco tem tarefas (busca O(1) no set)
            if (isset($blocosComTarefasSet[$blocoId])) {
                $statsByType[$tipoId]['occupied_blocks']++;
                $statsByType[$tipoId]['occupied_hours'] += $horas;
            }
        }
        
        // Calcula horas livres e percentuais para cada tipo
        foreach ($statsByType as $tipoId => &$stat) {
            $stat['free_blocks'] = $stat['total_blocks'] - $stat['occupied_blocks'];
            $stat['free_hours'] = $stat['total_hours'] - $stat['occupied_hours'];
            
            if ($stat['total_hours'] > 0) {
                $stat['occupancy_percent'] = round(($stat['occupied_hours'] / $stat['total_hours']) * 100, 1);
            }
            
            // Arredonda valores
            $stat['total_hours'] = round($stat['total_hours'], 2);
            $stat['occupied_hours'] = round($stat['occupied_hours'], 2);
            $stat['free_hours'] = round($stat['free_hours'], 2);
        }
        unset($stat);
        
        // Remove tipos sem blocos
        $statsByType = array_filter($statsByType, function($stat) {
            return $stat['total_blocks'] > 0;
        });
        
        // Reindexa array (remove chaves numéricas)
        $statsByType = array_values($statsByType);
        
        // Calcula totais gerais
        $summaryTotals = [
            'total_blocks' => 0,
            'total_hours' => 0.0,
            'occupied_blocks' => 0,
            'occupied_hours' => 0.0,
            'free_blocks' => 0,
            'free_hours' => 0.0,
            'occupancy_percent' => 0.0,
        ];
        
        foreach ($statsByType as $stat) {
            $summaryTotals['total_blocks'] += $stat['total_blocks'];
            $summaryTotals['total_hours'] += $stat['total_hours'];
            $summaryTotals['occupied_blocks'] += $stat['occupied_blocks'];
            $summaryTotals['occupied_hours'] += $stat['occupied_hours'];
            $summaryTotals['free_blocks'] += $stat['free_blocks'];
            $summaryTotals['free_hours'] += $stat['free_hours'];
        }
        
        if ($summaryTotals['total_hours'] > 0) {
            $summaryTotals['occupancy_percent'] = round(($summaryTotals['occupied_hours'] / $summaryTotals['total_hours']) * 100, 1);
        }
        
        $summaryTotals['total_hours'] = round($summaryTotals['total_hours'], 2);
        $summaryTotals['occupied_hours'] = round($summaryTotals['occupied_hours'], 2);
        $summaryTotals['free_hours'] = round($summaryTotals['free_hours'], 2);
        
        $processTime = microtime(true) - $processStart;
        $totalTime = microtime(true) - $startTime;
        
        // DEBUG TEMPORÁRIO: Log de tempos (remover na versão final)
        error_log(sprintf(
            "[getWeeklyStats] Query1: %.3fms | Query2: %.3fms | Query3: %.3fms | Process: %.3fms | Total: %.3fms",
            $query1Time * 1000,
            $query2Time * 1000,
            $query3Time * 1000,
            $processTime * 1000,
            $totalTime * 1000
        ));
        
        return [
            'stats_by_type' => $statsByType,
            'summary_totals' => $summaryTotals,
        ];
    }
    
    /**
     * Busca blocos de uma data específica
     * 
     * @param \DateTime|string $data Data para buscar blocos
     * @return array Lista de blocos com informações completas
     */
    public static function getBlocksByDate($data): array
    {
        $db = DB::getConnection();
        
        if ($data instanceof \DateTime) {
            $data->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
            $dataStr = $data->format('Y-m-d');
        } else {
            $dataStr = $data;
        }
        
        // Verifica se a coluna deleted_at existe na tabela tasks (com cache)
        $hasDeletedAt = self::hasDeletedAtColumn($db);
        
        // Subquery de contagem: usa os mesmos filtros de getTasksByBlock()
        // COUNT(DISTINCT) evita duplicidades na pivot
        // Filtra tarefas soft-deletadas se a coluna existir
        if ($hasDeletedAt) {
            $tasksCountSubquery = "
                (SELECT COUNT(DISTINCT abt.task_id)
                 FROM agenda_block_tasks abt
                 INNER JOIN tasks t ON abt.task_id = t.id
                 WHERE abt.bloco_id = b.id
                 AND t.deleted_at IS NULL
                )";
        } else {
            $tasksCountSubquery = "
                (SELECT COUNT(DISTINCT abt.task_id)
                 FROM agenda_block_tasks abt
                 INNER JOIN tasks t ON abt.task_id = t.id
                 WHERE abt.bloco_id = b.id
                )";
        }
        
        // Constrói a query completa antes de preparar
        $sql = "
            SELECT 
                b.*,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo,
                bt.cor_hex as tipo_cor,
                p.name as projeto_foco_nome,
                t_focus.title as focus_task_title,
                t_focus.status as focus_task_status,
                " . $tasksCountSubquery . " as tarefas_count
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            LEFT JOIN projects p ON b.projeto_foco_id = p.id
            LEFT JOIN tasks t_focus ON b.focus_task_id = t_focus.id
            WHERE b.data = ?
            ORDER BY b.hora_inicio ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$dataStr]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Busca um bloco por ID (alias para getBlockById para manter compatibilidade)
     * 
     * @param int $id ID do bloco
     * @return array|null Dados do bloco ou null se não encontrado
     */
    public static function findBlock(int $id): ?array
    {
        return self::getBlockById($id);
    }
    
    /**
     * Busca tarefas vinculadas a um bloco
     * 
     * @param int $blocoId ID do bloco
     * @return array Lista de tarefas
     */
    public static function getTasksByBlock(int $blocoId): array
    {
        $db = DB::getConnection();
        
        // Tenta primeiro com deleted_at (se a coluna existir)
        // Se der erro, tenta sem a condição (compatibilidade com banco antigo)
        try {
            $stmt = $db->prepare("
                SELECT 
                    t.*,
                    p.name as project_name,
                    p.tenant_id as project_tenant_id,
                    tn.name as tenant_name
                FROM tasks t
                INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
                INNER JOIN projects p ON t.project_id = p.id
                LEFT JOIN tenants tn ON p.tenant_id = tn.id
                WHERE abt.bloco_id = ? AND t.deleted_at IS NULL
                ORDER BY t.status ASC, t.`order` ASC
            ");
            $stmt->execute([$blocoId]);
        } catch (\PDOException $e) {
            // Se deu erro (provavelmente coluna deleted_at não existe), tenta sem a condição
            $stmt = $db->prepare("
                SELECT 
                    t.*,
                    p.name as project_name,
                    p.tenant_id as project_tenant_id,
                    tn.name as tenant_name
                FROM tasks t
                INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
                INNER JOIN projects p ON t.project_id = p.id
                LEFT JOIN tenants tn ON p.tenant_id = tn.id
                WHERE abt.bloco_id = ?
                ORDER BY t.status ASC, t.`order` ASC
            ");
            $stmt->execute([$blocoId]);
        }
        
        $tarefas = $stmt->fetchAll();
        
        // Adiciona status_label para cada tarefa
        foreach ($tarefas as &$tarefa) {
            $statusLabels = [
                'backlog' => 'Backlog',
                'em_andamento' => 'Em Andamento',
                'aguardando_cliente' => 'Aguardando Cliente',
                'concluida' => 'Concluída',
            ];
            $tarefa['status_label'] = $statusLabels[$tarefa['status']] ?? $tarefa['status'];
            $tarefa['projeto_nome'] = $tarefa['project_name'] ?? '';
        }
        unset($tarefa);
        
        return $tarefas;
    }
    
    /**
     * Retorna as tarefas vinculadas a um bloco de agenda (alias para getTasksByBlock)
     * 
     * @param int $blockId ID do bloco
     * @return array Lista de tarefas (id, title, status, projeto, etc.)
     */
    public static function getTasksForBlock(int $blockId): array
    {
        return self::getTasksByBlock($blockId);
    }
    
    /**
     * Vincula uma tarefa existente a um bloco
     * 
     * @param int $blockId ID do bloco
     * @param int $taskId ID da tarefa
     * @param bool $removeOldLinks Se true, remove vínculos antigos antes de adicionar o novo (para reagendamento)
     * @return void
     * @throws \RuntimeException Se houver erro
     */
    public static function attachTaskToBlock(int $blockId, int $taskId, bool $removeOldLinks = false): void
    {
        $db = DB::getConnection();
        
        // Se removeOldLinks é true, remove todos os vínculos antigos da tarefa antes de adicionar o novo
        // Isso garante que ao reagendar, a tarefa fique vinculada apenas ao novo bloco
        if ($removeOldLinks) {
            $stmt = $db->prepare("DELETE FROM agenda_block_tasks WHERE task_id = ?");
            $stmt->execute([$taskId]);
        }
        
        // Verifica se o vínculo já existe (após remover antigos, se necessário)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM agenda_block_tasks WHERE bloco_id = ? AND task_id = ?");
        $stmt->execute([$blockId, $taskId]);
        $result = $stmt->fetch();
        
        if ($result && (int)$result['count'] > 0) {
            // Já existe vínculo, não faz nada
            return;
        }
        
        // Insere o vínculo
        $stmt = $db->prepare("INSERT INTO agenda_block_tasks (bloco_id, task_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$blockId, $taskId]);
    }
    
    /**
     * Move uma tarefa de um bloco para outro (reagendamento)
     * Remove o vínculo antigo e adiciona o novo
     * 
     * @param int $newBlockId ID do novo bloco
     * @param int $taskId ID da tarefa
     * @param int|null $oldBlockId ID do bloco antigo (opcional, se não fornecido remove todos os vínculos)
     * @return void
     * @throws \RuntimeException Se houver erro
     */
    public static function moveTaskToBlock(int $newBlockId, int $taskId, ?int $oldBlockId = null): void
    {
        $db = DB::getConnection();
        
        // Remove vínculo antigo (se especificado) ou todos os vínculos da tarefa
        if ($oldBlockId !== null) {
            $stmt = $db->prepare("DELETE FROM agenda_block_tasks WHERE bloco_id = ? AND task_id = ?");
            $stmt->execute([$oldBlockId, $taskId]);
        } else {
            // Remove todos os vínculos da tarefa (reagendamento completo)
            $stmt = $db->prepare("DELETE FROM agenda_block_tasks WHERE task_id = ?");
            $stmt->execute([$taskId]);
        }
        
        // Verifica se o vínculo com o novo bloco já existe
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM agenda_block_tasks WHERE bloco_id = ? AND task_id = ?");
        $stmt->execute([$newBlockId, $taskId]);
        $result = $stmt->fetch();
        
        if ($result && (int)$result['count'] > 0) {
            // Já existe vínculo, não faz nada
            return;
        }
        
        // Insere o novo vínculo
        $stmt = $db->prepare("INSERT INTO agenda_block_tasks (bloco_id, task_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$newBlockId, $taskId]);
    }
    
    /**
     * Remove vínculo de uma tarefa com um bloco
     * 
     * @param int $blockId ID do bloco
     * @param int $taskId ID da tarefa
     * @return void
     */
    public static function detachTaskFromBlock(int $blockId, int $taskId): void
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("DELETE FROM agenda_block_tasks WHERE bloco_id = ? AND task_id = ?");
        $stmt->execute([$blockId, $taskId]);
    }
    
    /**
     * Define a tarefa foco de um bloco
     * 
     * @param int $blockId ID do bloco
     * @param int|null $taskId ID da tarefa (null para remover foco)
     * @return void
     * @throws \RuntimeException Se a tarefa não estiver vinculada ao bloco
     */
    public static function setFocusTaskForBlock(int $blockId, ?int $taskId): void
    {
        $db = DB::getConnection();
        
        if ($taskId !== null) {
            // Verifica se a tarefa está vinculada ao bloco
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM agenda_block_tasks WHERE bloco_id = ? AND task_id = ?");
            $stmt->execute([$blockId, $taskId]);
            $result = $stmt->fetch();
            
            if (!$result || (int)$result['count'] === 0) {
                throw new \RuntimeException('A tarefa deve estar vinculada ao bloco antes de ser definida como foco.');
            }
        }
        
        // Atualiza o bloco
        $stmt = $db->prepare("UPDATE agenda_blocks SET focus_task_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$taskId, $blockId]);
    }
    
    /**
     * Retorna a tarefa foco de um bloco (ou null)
     * 
     * @param int $blockId ID do bloco
     * @return array|null Dados da tarefa foco
     */
    public static function getFocusTaskForBlock(int $blockId): ?array
    {
        $db = DB::getConnection();
        
        // Tenta primeiro com deleted_at (se a coluna existir)
        // Se der erro, tenta sem a condição (compatibilidade com banco antigo)
        try {
            $stmt = $db->prepare("
                SELECT 
                    t.*,
                    p.name as projeto_nome
                FROM agenda_blocks b
                INNER JOIN tasks t ON b.focus_task_id = t.id
                INNER JOIN projects p ON t.project_id = p.id
                WHERE b.id = ? AND t.deleted_at IS NULL
            ");
            $stmt->execute([$blockId]);
        } catch (\PDOException $e) {
            // Se deu erro (provavelmente coluna deleted_at não existe), tenta sem a condição
            $stmt = $db->prepare("
                SELECT 
                    t.*,
                    p.name as projeto_nome
                FROM agenda_blocks b
                INNER JOIN tasks t ON b.focus_task_id = t.id
                INNER JOIN projects p ON t.project_id = p.id
                WHERE b.id = ?
            ");
            $stmt->execute([$blockId]);
        }
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca blocos relacionados a uma tarefa
     * 
     * @param int $taskId ID da tarefa
     * @return array Lista de blocos com informações completas
     */
    public static function getBlocksForTask(int $taskId): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                b.*,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo,
                bt.cor_hex as tipo_cor_hex,
                DATE_FORMAT(b.data, '%d/%m/%Y') as data_formatada
            FROM agenda_blocks b
            INNER JOIN agenda_block_tasks abt ON b.id = abt.bloco_id
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE abt.task_id = ?
            ORDER BY b.data DESC, b.hora_inicio ASC
        ");
        $stmt->execute([$taskId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Vincula uma tarefa a um bloco (método legado, mantido para compatibilidade)
     * 
     * @param int $blocoId ID do bloco
     * @param int $taskId ID da tarefa
     * @return bool Sucesso da operação
     */
    public static function linkTaskToBlock(int $blocoId, int $taskId): bool
    {
        $db = DB::getConnection();
        
        try {
            $stmt = $db->prepare("
                INSERT IGNORE INTO agenda_block_tasks (bloco_id, task_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$blocoId, $taskId]);
            return true;
        } catch (\PDOException $e) {
            // Ignora erro de duplicidade
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return true;
            }
            throw $e;
        }
    }
    
    /**
     * Remove vínculo de uma tarefa com um bloco
     * 
     * @param int $blocoId ID do bloco
     * @param int $taskId ID da tarefa
     * @return bool Sucesso da operação
     */
    public static function unlinkTaskFromBlock(int $blocoId, int $taskId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("DELETE FROM agenda_block_tasks WHERE bloco_id = ? AND task_id = ?");
        $stmt->execute([$blocoId, $taskId]);
        
        return true;
    }
    
    /**
     * Atualiza status de um bloco
     * 
     * @param int $id ID do bloco
     * @param string $status Novo status
     * @param array $data Dados adicionais (resumo, duracao_real, motivo_cancelamento)
     * @return bool Sucesso da operação
     */
    public static function updateBlockStatus(int $id, string $status, array $data = []): bool
    {
        $db = DB::getConnection();
        
        $allowedStatuses = ['planned', 'ongoing', 'completed', 'partial', 'canceled'];
        if (!in_array($status, $allowedStatuses)) {
            throw new \InvalidArgumentException('Status inválido');
        }
        
        $resumo = isset($data['resumo']) ? trim($data['resumo']) : null;
        $duracaoReal = isset($data['duracao_real']) ? (int)$data['duracao_real'] : null;
        $horaInicioReal = isset($data['hora_inicio_real']) ? trim($data['hora_inicio_real']) : null;
        $horaFimReal = isset($data['hora_fim_real']) ? trim($data['hora_fim_real']) : null;
        $motivoCancelamento = isset($data['motivo_cancelamento']) ? trim($data['motivo_cancelamento']) : null;
        
        // Monta query dinamicamente para incluir horários reais se fornecidos
        $fields = ['status = ?', 'resumo = ?', 'duracao_real = ?', 'motivo_cancelamento = ?', 'updated_at = NOW()'];
        $values = [$status, $resumo, $duracaoReal, $motivoCancelamento];
        
        if ($horaInicioReal !== null) {
            $fields[] = 'hora_inicio_real = ?';
            $values[] = $horaInicioReal;
        }
        
        if ($horaFimReal !== null) {
            $fields[] = 'hora_fim_real = ?';
            $values[] = $horaFimReal;
        }
        
        $values[] = $id;
        
        $stmt = $db->prepare("
            UPDATE agenda_blocks 
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $stmt->execute($values);
        
        return true;
    }
    
    /**
     * Inicia um bloco (registra horário real de início e muda status para ongoing)
     * 
     * VALIDAÇÃO: Não permite iniciar um novo bloco se já existe um bloco em andamento (status = 'ongoing')
     * 
     * @param int $blockId ID do bloco
     * @return void
     * @throws \RuntimeException Se o bloco não for encontrado ou se já existe um bloco em andamento
     */
    public static function startBlock(int $blockId): void
    {
        $db = DB::getConnection();
        
        // Busca o bloco
        $bloco = self::getBlockById($blockId);
        if (!$bloco) {
            throw new \RuntimeException('Bloco não encontrado');
        }
        
        // VALIDAÇÃO: Verifica se já existe algum bloco em andamento
        $stmt = $db->prepare("
            SELECT b.id, b.data, b.hora_inicio, b.hora_fim, bt.nome as tipo_nome, bt.codigo as tipo_codigo
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE b.status = 'ongoing'
            AND b.id != ?
            LIMIT 1
        ");
        $stmt->execute([$blockId]);
        $blocoEmAndamento = $stmt->fetch();
        
        if ($blocoEmAndamento) {
            // Formata data e hora para mensagem amigável
            $dataFormatada = date('d/m/Y', strtotime($blocoEmAndamento['data']));
            $horaInicio = date('H:i', strtotime($blocoEmAndamento['hora_inicio']));
            $horaFim = date('H:i', strtotime($blocoEmAndamento['hora_fim']));
            
            throw new \RuntimeException(
                "Você já tem um bloco em andamento. Finalize o bloco de {$dataFormatada} " .
                "({$horaInicio}-{$horaFim} - {$blocoEmAndamento['tipo_nome']}) antes de iniciar um novo."
            );
        }
        
        // Se hora_inicio_real ainda não estiver preenchida, preenche com hora atual
        $horaInicioReal = $bloco['hora_inicio_real'];
        if (empty($horaInicioReal)) {
            $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
            $horaInicioReal = $now->format('H:i:s');
        }
        
        // Atualiza status e horário real
        self::updateBlockStatus($blockId, 'ongoing', [
            'hora_inicio_real' => $horaInicioReal,
        ]);
    }
    
    /**
     * Verifica se existe algum bloco em andamento
     * 
     * Útil para o frontend verificar se há um bloco aberto antes de permitir iniciar outro
     * 
     * @return array|null Retorna informações do bloco em andamento ou null se não houver
     */
    public static function getOngoingBlock(): ?array
    {
        $db = DB::getConnection();
        
        $stmt = $db->query("
            SELECT 
                b.id,
                b.data,
                b.hora_inicio,
                b.hora_fim,
                b.hora_inicio_real,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo,
                bt.cor_hex as tipo_cor_hex
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE b.status = 'ongoing'
            LIMIT 1
        ");
        
        $bloco = $stmt->fetch();
        return $bloco ?: null;
    }
    
    /**
     * Retorna tarefas pendentes (não concluídas) vinculadas a um bloco
     * 
     * IMPORTANTE: Busca APENAS tarefas vinculadas ao bloco específico (abt.bloco_id = ?)
     * que NÃO estão reagendadas para outro bloco ativo (futuro ou em andamento).
     * 
     * Uma tarefa é considerada pendente deste bloco apenas se:
     * 1. Está vinculada a este bloco específico
     * 2. Não está concluída
     * 3. NÃO possui outro vínculo ativo em outro bloco (futuro ou em andamento)
     * 
     * Um bloco é considerado "ativo" se:
     * - status IN ('planned', 'ongoing') E
     * - (data > hoje OU (data = hoje E hora_fim >= hora_atual) OU status = 'ongoing')
     * 
     * @param int $blockId ID do bloco
     * @return array Lista de tarefas pendentes (id, title, status, status_label, project_name)
     */
    public static function getPendingTasksForBlock(int $blockId): array
    {
        $db = DB::getConnection();
        
        // Query corrigida: busca tarefas vinculadas a este bloco que:
        // 1. Não estão concluídas
        // 2. NÃO têm outro vínculo ativo em outro bloco (futuro ou em andamento)
        //
        // A subquery verifica se existe outro vínculo da mesma tarefa em outro bloco ativo
        $hasDeletedAt = self::hasDeletedAtColumn($db);
        
        // Tenta primeiro com deleted_at (se a coluna existir)
        try {
            $sql = "
                SELECT 
                    t.id,
                    t.title,
                    t.status,
                    p.name as project_name
                FROM tasks t
                INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
                INNER JOIN projects p ON t.project_id = p.id
                WHERE abt.bloco_id = ? 
                AND t.status != 'concluida'
                AND t.deleted_at IS NULL
                AND NOT EXISTS (
                    -- Verifica se a tarefa tem outro vínculo ativo em outro bloco
                    SELECT 1
                    FROM agenda_block_tasks abt2
                    INNER JOIN agenda_blocks b2 ON abt2.bloco_id = b2.id
                    WHERE abt2.task_id = t.id
                    AND abt2.bloco_id != ?
                    AND b2.status IN ('planned', 'ongoing')
                    AND (
                        -- Bloco futuro (data > hoje)
                        b2.data > CURDATE()
                        OR
                        -- Bloco hoje que ainda não terminou (data = hoje E hora_fim >= hora_atual)
                        (b2.data = CURDATE() AND b2.hora_fim >= TIME(NOW()))
                        OR
                        -- Bloco em andamento (status = 'ongoing', independente da data)
                        b2.status = 'ongoing'
                    )
                )
                ORDER BY t.title ASC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$blockId, $blockId]);
        } catch (\PDOException $e) {
            // Se deu erro (provavelmente coluna deleted_at não existe), tenta sem a condição
            $sql = "
                SELECT 
                    t.id,
                    t.title,
                    t.status,
                    p.name as project_name
                FROM tasks t
                INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
                INNER JOIN projects p ON t.project_id = p.id
                WHERE abt.bloco_id = ? 
                AND t.status != 'concluida'
                AND NOT EXISTS (
                    -- Verifica se a tarefa tem outro vínculo ativo em outro bloco
                    SELECT 1
                    FROM agenda_block_tasks abt2
                    INNER JOIN agenda_blocks b2 ON abt2.bloco_id = b2.id
                    WHERE abt2.task_id = t.id
                    AND abt2.bloco_id != ?
                    AND b2.status IN ('planned', 'ongoing')
                    AND (
                        -- Bloco futuro (data > hoje)
                        b2.data > CURDATE()
                        OR
                        -- Bloco hoje que ainda não terminou (data = hoje E hora_fim >= hora_atual)
                        (b2.data = CURDATE() AND b2.hora_fim >= TIME(NOW()))
                        OR
                        -- Bloco em andamento (status = 'ongoing', independente da data)
                        b2.status = 'ongoing'
                    )
                )
                ORDER BY t.title ASC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$blockId, $blockId]);
        }
        
        $tarefas = $stmt->fetchAll();
        
        // Adiciona status_label para cada tarefa
        foreach ($tarefas as &$tarefa) {
            $statusLabels = [
                'backlog' => 'Backlog',
                'em_andamento' => 'Em Andamento',
                'aguardando_cliente' => 'Aguardando Cliente',
                'concluida' => 'Concluída',
            ];
            $tarefa['status_label'] = $statusLabels[$tarefa['status']] ?? $tarefa['status'];
        }
        unset($tarefa);
        
        return $tarefas;
    }
    
    /**
     * Encerra um bloco (registra horário real de fim e muda status para completed)
     * 
     * @param int $blockId ID do bloco
     * @param \DateTimeInterface|null $horaFimReal Horário real de fim (null = usa hora atual)
     * @param string|null $resumo Resumo do bloco (obrigatório)
     * @return void
     * @throws \RuntimeException Se o bloco não for encontrado ou resumo não fornecido
     */
    public static function finishBlock(int $blockId, ?\DateTimeInterface $horaFimReal = null, ?string $resumo = null): void
    {
        $db = DB::getConnection();
        
        // Valida resumo obrigatório
        if (empty($resumo) || trim($resumo) === '') {
            throw new \RuntimeException('Resumo é obrigatório para encerrar o bloco');
        }
        
        // Busca o bloco
        $bloco = self::getBlockById($blockId);
        if (!$bloco) {
            throw new \RuntimeException('Bloco não encontrado');
        }
        
        // Se hora_inicio_real ainda não estiver preenchida, preenche com hora atual
        $horaInicioReal = $bloco['hora_inicio_real'];
        if (empty($horaInicioReal)) {
            $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
            $horaInicioReal = $now->format('H:i:s');
        }
        
        // Se hora_fim_real não foi fornecida, usa hora atual
        if ($horaFimReal === null) {
            $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
            $horaFimRealStr = $now->format('H:i:s');
        } else {
            // Converte DateTimeInterface para string H:i:s
            if ($horaFimReal instanceof \DateTimeImmutable) {
                $horaFimRealStr = $horaFimReal->format('H:i:s');
            } else {
                $horaFimRealStr = $horaFimReal->format('H:i:s');
            }
        }
        
        // Atualiza status, horários reais e resumo
        self::updateBlockStatus($blockId, 'completed', [
            'hora_inicio_real' => $horaInicioReal,
            'hora_fim_real' => $horaFimRealStr,
            'resumo' => trim($resumo),
        ]);
    }
    
    /**
     * Reabre um bloco concluído, voltando o status para planned e resetando horários reais
     * 
     * @param int $blockId ID do bloco
     * @return void
     * @throws \RuntimeException Se o bloco não for encontrado ou não estiver concluído
     */
    public static function reopenBlock(int $blockId): void
    {
        $db = DB::getConnection();
        
        // Busca o bloco
        $bloco = self::getBlockById($blockId);
        if (!$bloco) {
            throw new \RuntimeException('Bloco não encontrado');
        }
        
        // Valida que o bloco está concluído
        if ($bloco['status'] !== 'completed') {
            throw new \RuntimeException('Apenas blocos concluídos podem ser reabertos. Status atual: ' . $bloco['status']);
        }
        
        // Atualiza status para planned e reseta horários reais
        $stmt = $db->prepare("
            UPDATE agenda_blocks 
            SET status = 'planned',
                hora_inicio_real = NULL,
                hora_fim_real = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$blockId]);
        
        // Verifica se a atualização foi bem-sucedida
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Erro ao reabrir o bloco');
        }
    }
    
    /**
     * Exclui um bloco e seus vínculos
     * 
     * @param int $blockId ID do bloco
     * @return void
     * @throws \RuntimeException Se o bloco não for encontrado
     */
    public static function deleteBlock(int $blockId): void
    {
        $db = DB::getConnection();
        
        // Verifica se o bloco existe
        $bloco = self::getBlockById($blockId);
        if (!$bloco) {
            throw new \RuntimeException('Bloco não encontrado');
        }
        
        // Remove vínculos de tarefas (CASCADE já faz isso, mas vamos fazer explicitamente)
        $stmt = $db->prepare('DELETE FROM agenda_block_tasks WHERE bloco_id = ?');
        $stmt->execute([$blockId]);
        
        // Remove o bloco
        $stmt = $db->prepare('DELETE FROM agenda_blocks WHERE id = ?');
        $stmt->execute([$blockId]);
    }
    
    /**
     * Busca o próximo bloco disponível de um tipo específico
     * 
     * @param string $tipoCodigo Código do tipo de bloco (FUTURE, CLIENTES, etc.)
     * @param \DateTime|null $dataMin Data mínima (padrão: hoje)
     * @return array|null Dados do bloco ou null se não encontrado
     */
    public static function findNextAvailableBlock(string $tipoCodigo, ?\DateTime $dataMin = null): ?array
    {
        $db = DB::getConnection();
        
        if ($dataMin === null) {
            $dataMin = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        }
        $dataMin->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
        $dataMinStr = $dataMin->format('Y-m-d');
        
        // Busca o tipo de bloco
        $stmt = $db->prepare("SELECT id FROM agenda_block_types WHERE codigo = ? AND ativo = 1");
        $stmt->execute([$tipoCodigo]);
        $tipo = $stmt->fetch();
        
        if (!$tipo) {
            return null;
        }
        
        // Busca próximo bloco disponível
        $stmt = $db->prepare("
            SELECT b.*, bt.nome as tipo_nome, bt.codigo as tipo_codigo, bt.cor_hex as tipo_cor
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE b.tipo_id = ? 
            AND b.data >= ?
            AND b.status = 'planned'
            ORDER BY b.data ASC, b.hora_inicio ASC
            LIMIT 1
        ");
        $stmt->execute([$tipo['id'], $dataMinStr]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Determina o tipo de bloco adequado para uma tarefa
     * 
     * @param array $task Dados da tarefa
     * @param array|null $project Dados do projeto (se não fornecido, busca do banco)
     * @return string Código do tipo de bloco
     */
    public static function determineBlockTypeForTask(array $task, ?array $project = null): string
    {
        if ($project === null) {
            $project = ProjectService::findProject($task['project_id']);
        }
        
        $taskType = $task['task_type'] ?? 'internal';
        $projectType = $project['type'] ?? 'interno';
        
        // Tarefa de ticket de cliente
        if ($taskType === 'client_ticket') {
            // Se prioridade alta/crítica, vai para CLIENTES
            // Se prioridade baixa/média, vai para SUPORTE
            // Nota: tickets não têm prioridade direta, mas podem ter via projeto
            $priority = $project['priority'] ?? 'media';
            if (in_array($priority, ['alta', 'critica'])) {
                return 'CLIENTES';
            }
            return 'SUPORTE';
        }
        
        // Tarefa de projeto interno (FUTURE)
        if ($projectType === 'interno') {
            return 'FUTURE';
        }
        
        // Tarefa de projeto de cliente (CLIENTES)
        if ($projectType === 'cliente') {
            return 'CLIENTES';
        }
        
        // Tarefa financeira/admin (verificar por tags ou categoria futura)
        // Por enquanto, padrão é CLIENTES
        return 'CLIENTES';
    }
    
    /**
     * Relatório semanal de produtividade
     * 
     * @param \DateTime $dataInicio Data de início da semana
     * @return array Dados do relatório
     */
    public static function getWeeklyReport(\DateTime $dataInicio): array
    {
        $db = DB::getConnection();
        
        $dataInicio->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
        $dataFim = clone $dataInicio;
        $dataFim->modify('+6 days');
        
        $dataInicioStr = $dataInicio->format('Y-m-d');
        $dataFimStr = $dataFim->format('Y-m-d');
        
        // Horas por tipo de bloco
        $stmt = $db->prepare("
            SELECT 
                bt.codigo,
                bt.nome,
                COUNT(*) as blocos_total,
                SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as blocos_concluidos,
                SUM(CASE WHEN b.status = 'partial' THEN 1 ELSE 0 END) as blocos_parciais,
                SUM(CASE WHEN b.status = 'canceled' THEN 1 ELSE 0 END) as blocos_cancelados,
                SUM(COALESCE(b.duracao_real, b.duracao_planejada)) as minutos_total
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE b.data BETWEEN ? AND ?
            GROUP BY bt.id, bt.codigo, bt.nome
            ORDER BY bt.nome ASC
        ");
        $stmt->execute([$dataInicioStr, $dataFimStr]);
        $horasPorTipo = $stmt->fetchAll();
        
        // Tarefas concluídas por tipo de bloco
        $stmt = $db->prepare("
            SELECT 
                bt.codigo,
                bt.nome,
                COUNT(DISTINCT t.id) as tarefas_concluidas
            FROM tasks t
            INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
            INNER JOIN agenda_blocks b ON abt.bloco_id = b.id
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE b.data BETWEEN ? AND ?
            AND t.status = 'concluida'
            AND t.deleted_at IS NULL
            GROUP BY bt.id, bt.codigo, bt.nome
        ");
        $stmt->execute([$dataInicioStr, $dataFimStr]);
        $tarefasPorTipo = $stmt->fetchAll();
        
        // Horas por projeto
        $stmt = $db->prepare("
            SELECT 
                p.id,
                p.name as projeto_nome,
                SUM(COALESCE(b.duracao_real, b.duracao_planejada)) as minutos_total
            FROM agenda_blocks b
            INNER JOIN projects p ON b.projeto_foco_id = p.id
            WHERE b.data BETWEEN ? AND ?
            AND b.projeto_foco_id IS NOT NULL
            GROUP BY p.id, p.name
            ORDER BY minutos_total DESC
        ");
        $stmt->execute([$dataInicioStr, $dataFimStr]);
        $horasPorProjeto = $stmt->fetchAll();
        
        // Blocos cancelados com motivos
        $stmt = $db->prepare("
            SELECT 
                b.data,
                b.motivo_cancelamento,
                bt.nome as tipo_nome
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE b.data BETWEEN ? AND ?
            AND b.status = 'canceled'
            ORDER BY b.data ASC
        ");
        $stmt->execute([$dataInicioStr, $dataFimStr]);
        $blocosCancelados = $stmt->fetchAll();
        
        return [
            'periodo' => [
                'inicio' => $dataInicioStr,
                'fim' => $dataFimStr,
            ],
            'horas_por_tipo' => $horasPorTipo,
            'tarefas_por_tipo' => $tarefasPorTipo,
            'horas_por_projeto' => $horasPorProjeto,
            'blocos_cancelados' => $blocosCancelados,
        ];
    }
    
    /**
     * Relatório mensal de produtividade
     * 
     * @param int $ano Ano
     * @param int $mes Mês (1-12)
     * @return array Dados do relatório
     */
    public static function getMonthlyReport(int $ano, int $mes): array
    {
        $dataInicio = new \DateTime("{$ano}-{$mes}-01", new \DateTimeZone('America/Sao_Paulo'));
        $dataFim = clone $dataInicio;
        $dataFim->modify('last day of this month');
        
        return self::getWeeklyReport($dataInicio);
    }
    
    /**
     * Calcula disponibilidade para novos projetos
     * 
     * @param int $blocosNecessarios Número estimado de blocos FUTURE/CLIENTES necessários
     * @return array Informações de disponibilidade
     */
    public static function getAvailabilityForNewProject(int $blocosNecessarios = 10): array
    {
        $db = DB::getConnection();
        
        // Busca próximos blocos disponíveis de CLIENTES
        $stmt = $db->prepare("
            SELECT b.*, bt.codigo
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE bt.codigo IN ('CLIENTES', 'FLEX')
            AND b.data >= CURDATE()
            AND b.status = 'planned'
            ORDER BY b.data ASC, b.hora_inicio ASC
            LIMIT ?
        ");
        $stmt->execute([$blocosNecessarios]);
        $blocosDisponiveis = $stmt->fetchAll();
        
        $proximaJanela = null;
        if (!empty($blocosDisponiveis)) {
            $proximaJanela = [
                'data' => $blocosDisponiveis[0]['data'],
                'hora' => $blocosDisponiveis[0]['hora_inicio'],
            ];
        }
        
        // Calcula ritmo atual (blocos por semana)
        $stmt = $db->query("
            SELECT COUNT(*) as blocos_semana
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE bt.codigo IN ('CLIENTES', 'FLEX')
            AND b.data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND b.data < CURDATE()
            AND b.status IN ('completed', 'partial')
        ");
        $ritmo = $stmt->fetch();
        $blocosPorSemana = (int)($ritmo['blocos_semana'] ?? 0);
        
        $semanasEstimadas = $blocosPorSemana > 0 ? ceil($blocosNecessarios / $blocosPorSemana) : null;
        
        return [
            'proxima_janela' => $proximaJanela,
            'blocos_disponiveis' => count($blocosDisponiveis),
            'ritmo_atual' => $blocosPorSemana,
            'semanas_estimadas' => $semanasEstimadas,
        ];
    }
    
    /**
     * Calcula disponibilidade para suporte
     * 
     * @return array Informações de disponibilidade de blocos SUPORTE
     */
    public static function getAvailabilityForSupport(): array
    {
        $db = DB::getConnection();
        
        // Busca próximo bloco de SUPORTE disponível hoje
        $stmt = $db->prepare("
            SELECT b.*, bt.codigo
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            WHERE bt.codigo = 'SUPORTE'
            AND b.data = CURDATE()
            AND b.status = 'planned'
            AND b.hora_inicio >= TIME(NOW())
            ORDER BY b.hora_inicio ASC
            LIMIT 1
        ");
        $stmt->execute();
        $blocoHoje = $stmt->fetch();
        
        // Se não tem hoje, busca o próximo
        if (!$blocoHoje) {
            $stmt = $db->prepare("
                SELECT b.*, bt.codigo
                FROM agenda_blocks b
                INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
                WHERE bt.codigo = 'SUPORTE'
                AND b.data > CURDATE()
                AND b.status = 'planned'
                ORDER BY b.data ASC, b.hora_inicio ASC
                LIMIT 1
            ");
            $stmt->execute();
            $blocoHoje = $stmt->fetch();
        }
        
        return [
            'proximo_bloco' => $blocoHoje ? [
                'data' => $blocoHoje['data'],
                'hora' => $blocoHoje['hora_inicio'],
            ] : null,
        ];
    }
}

