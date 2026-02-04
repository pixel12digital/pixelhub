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

    /** @var bool|null */
    private static $hasActivityTypesSupport = null;

    /**
     * Verifica se activity_types existe e agenda_blocks tem activity_type_id (com cache)
     */
    private static function hasActivityTypesSupport(\PDO $db): bool
    {
        if (self::$hasActivityTypesSupport !== null) {
            return self::$hasActivityTypesSupport;
        }
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'activity_types'");
            $rows = $stmt->fetchAll();
            if (count($rows) === 0) {
                self::$hasActivityTypesSupport = false;
                return false;
            }
            $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'activity_type_id'");
            $rows = $stmt->fetchAll();
            self::$hasActivityTypesSupport = count($rows) > 0;
        } catch (\Throwable $e) {
            self::$hasActivityTypesSupport = false;
        }
        return self::$hasActivityTypesSupport;
    }

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
                $nomesDia = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
                $nomeDia = $nomesDia[$diaSemana] ?? 'dia ' . $diaSemana;
                throw new \RuntimeException(
                    'Não há modelo de agenda para ' . $nomeDia . '-feira (' . $data->format('d/m/Y') . '). ' .
                    'Os modelos são por dia da semana (Segunda, Terça, etc.), não por data. ' .
                    'Crie um modelo para ' . $nomeDia . ' em Configurações → Agenda → Modelos de Blocos.'
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
        $tipoId = isset($dados['tipo_id']) && (int)$dados['tipo_id'] > 0 ? (int)$dados['tipo_id'] : (int)$bloco['tipo_id'];
        $projetoFocoId = isset($dados['projeto_foco_id']) ? ($dados['projeto_foco_id'] ? (int)$dados['projeto_foco_id'] : null) : $bloco['projeto_foco_id'];
        
        // Verifica se o tipo existe (evita erro de FK)
        $stmt = $db->prepare("SELECT id FROM agenda_block_types WHERE id = ?");
        $stmt->execute([$tipoId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Tipo de bloco inválido. O bloco está vinculado a um tipo que não existe mais. Edite o bloco e selecione um tipo válido em Configurações → Agenda → Tipos de Blocos.');
        }
        
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
        
        // Monta query dinamicamente para incluir horários reais e projeto_foco se fornecidos
        $fields = ['hora_inicio = ?', 'hora_fim = ?', 'tipo_id = ?', 'projeto_foco_id = ?', 'duracao_planejada = ?', 'updated_at = NOW()'];
        $values = [$horaInicio, $horaFim, $tipoId, $projetoFocoId, $duracaoMinutos];
        
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
            throw new \RuntimeException('Tipo de bloco é obrigatório. Selecione um tipo (ex.: PRODUÇÃO, COMERCIAL) no campo Bloco.');
        }
        
        // Verifica se o tipo existe (evita erro de FK)
        $stmt = $db->prepare("SELECT id FROM agenda_block_types WHERE id = ? AND ativo = 1");
        $stmt->execute([$tipoId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Tipo de bloco inválido ou inativo. Selecione um tipo válido em Configurações → Agenda → Tipos de Blocos.');
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
        
        // Projeto foco (opcional)
        $projetoFocoId = isset($dados['projeto_foco_id']) && (int)$dados['projeto_foco_id'] > 0 ? (int)$dados['projeto_foco_id'] : null;
        // Cliente (opcional, para atividades avulsas comerciais)
        $tenantId = isset($dados['tenant_id']) && (int)$dados['tenant_id'] > 0 ? (int)$dados['tenant_id'] : null;
        // Observação/resumo (opcional, pode ser preenchido na criação)
        $resumo = isset($dados['resumo']) && trim($dados['resumo']) !== '' ? trim($dados['resumo']) : null;
        // Tipo de atividade (opcional, para atividades avulsas)
        $activityTypeId = isset($dados['activity_type_id']) && (int)$dados['activity_type_id'] > 0 ? (int)$dados['activity_type_id'] : null;
        
        // Insere o bloco (tenant_id, resumo e activity_type_id opcionais)
        $stmt = $db->prepare("
            INSERT INTO agenda_blocks 
            (data, hora_inicio, hora_fim, tipo_id, projeto_foco_id, activity_type_id, tenant_id, resumo, status, duracao_planejada, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'planned', ?, NOW(), NOW())
        ");
        $stmt->execute([
            $dataStr,
            $horaInicio,
            $horaFim,
            $tipoId,
            $projetoFocoId,
            $activityTypeId,
            $tenantId,
            $resumo,
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

        // activity_types: só inclui se tabela/coluna existirem (evita erro 500 em ambientes sem migration)
        $selectActivityType = 'NULL as activity_type_name,';
        $joinActivityTypes = '';
        try {
            if (self::hasActivityTypesSupport($db)) {
                $selectActivityType = 'at.name as activity_type_name,';
                $joinActivityTypes = 'LEFT JOIN activity_types at ON b.activity_type_id = at.id';
            }
        } catch (\Throwable $e) { /* ignora */ }

        // Constrói a query completa antes de preparar
        $sql = "
            SELECT 
                b.*,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo,
                bt.cor_hex as tipo_cor_hex,
                p.name as projeto_foco_nome,
                " . $selectActivityType . "
                t_focus.title as focus_task_title,
                t_focus.status as focus_task_status,
                " . $tasksCountSubquery . " as total_tarefas
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            LEFT JOIN projects p ON b.projeto_foco_id = p.id
            " . $joinActivityTypes . "
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

        // activity_types: só inclui se tabela/coluna existirem
        $selectActivityType = 'NULL as activity_type_name,';
        $joinActivityTypes = '';
        try {
            if (self::hasActivityTypesSupport($db)) {
                $selectActivityType = 'at.name as activity_type_name,';
                $joinActivityTypes = 'LEFT JOIN activity_types at ON b.activity_type_id = at.id';
            }
        } catch (\Throwable $e) { /* ignora */ }

        // Constrói a query completa antes de preparar
        // tn_block: cliente vinculado diretamente ao bloco (atividades avulsas)
        // tn_projeto: cliente do projeto (quando item vem de projeto/tarefa)
        $sql = "
            SELECT 
                b.*,
                bt.nome as tipo_nome,
                bt.codigo as tipo_codigo,
                bt.cor_hex as tipo_cor,
                p.name as projeto_foco_nome,
                " . $selectActivityType . "
                COALESCE(NULLIF(tn_block.nome_fantasia, ''), tn_block.name) as block_tenant_name,
                COALESCE(NULLIF(tn_projeto.nome_fantasia, ''), tn_projeto.name) as project_tenant_name,
                t_focus.title as focus_task_title,
                t_focus.status as focus_task_status,
                t_focus.project_id as focus_task_project_id,
                " . $tasksCountSubquery . " as tarefas_count
            FROM agenda_blocks b
            INNER JOIN agenda_block_types bt ON b.tipo_id = bt.id
            LEFT JOIN projects p ON b.projeto_foco_id = p.id
            " . $joinActivityTypes . "
            LEFT JOIN tenants tn_block ON b.tenant_id = tn_block.id
            LEFT JOIN tenants tn_projeto ON p.tenant_id = tn_projeto.id
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
                    abt.hora_inicio as task_hora_inicio,
                    abt.hora_fim as task_hora_fim,
                    p.name as project_name,
                    p.tenant_id as project_tenant_id,
                    tn.name as tenant_name
                FROM tasks t
                INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
                INNER JOIN projects p ON t.project_id = p.id
                LEFT JOIN tenants tn ON p.tenant_id = tn.id
                WHERE abt.bloco_id = ? AND t.deleted_at IS NULL
                ORDER BY (abt.hora_inicio IS NULL) ASC, abt.hora_inicio ASC, abt.hora_fim ASC
            ");
            $stmt->execute([$blocoId]);
        } catch (\PDOException $e) {
            // Se deu erro (provavelmente coluna deleted_at não existe), tenta sem a condição
            $stmt = $db->prepare("
                SELECT 
                    t.*,
                    abt.hora_inicio as task_hora_inicio,
                    abt.hora_fim as task_hora_fim,
                    p.name as project_name,
                    p.tenant_id as project_tenant_id,
                    tn.name as tenant_name
                FROM tasks t
                INNER JOIN agenda_block_tasks abt ON t.id = abt.task_id
                INNER JOIN projects p ON t.project_id = p.id
                LEFT JOIN tenants tn ON p.tenant_id = tn.id
                WHERE abt.bloco_id = ?
                ORDER BY (abt.hora_inicio IS NULL) ASC, abt.hora_inicio ASC, abt.hora_fim ASC
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
     * Atualiza horário de uma tarefa dentro do bloco (com validação)
     * Regras: tarefa_inicio >= bloco_inicio, tarefa_fim <= bloco_fim, tarefa_fim > tarefa_inicio
     * Soma das durações das tarefas <= duração do bloco
     *
     * @param int $blockId ID do bloco
     * @param int $taskId ID da tarefa
     * @param string $horaInicio HH:MM ou HH:MM:SS
     * @param string $horaFim HH:MM ou HH:MM:SS
     * @return void
     * @throws \InvalidArgumentException Se validação falhar
     */
    public static function updateTaskTimeInBlock(int $blockId, int $taskId, string $horaInicio, string $horaFim): void
    {
        $db = DB::getConnection();

        $bloco = self::getBlockById($blockId);
        if (!$bloco) {
            throw new \InvalidArgumentException('Bloco não encontrado.');
        }

        $blocoInicio = $bloco['hora_inicio'] ?? '';
        $blocoFim = $bloco['hora_fim'] ?? '';
        if (!$blocoInicio || !$blocoFim) {
            throw new \InvalidArgumentException('Bloco sem horário definido.');
        }

        $ti = substr($horaInicio, 0, 5);
        $tf = substr($horaFim, 0, 5);
        $bi = substr($blocoInicio, 0, 5);
        $bf = substr($blocoFim, 0, 5);

        if ($ti < $bi) {
            throw new \InvalidArgumentException('Horário de início da tarefa não pode ser anterior ao início do bloco.');
        }
        if ($tf > $bf) {
            throw new \InvalidArgumentException('Horário de fim da tarefa não pode ser posterior ao fim do bloco.');
        }
        if ($tf <= $ti) {
            throw new \InvalidArgumentException('Horário de fim deve ser posterior ao de início.');
        }

        $toMins = function ($h) {
            $parts = explode(':', $h);
            return ((int)($parts[0] ?? 0)) * 60 + (int)($parts[1] ?? 0);
        };

        $tasks = self::getTasksByBlock($blockId);
        $blockDurationMins = $toMins($bf) - $toMins($bi);

        $totalMins = 0;
        foreach ($tasks as $t) {
            $thIni = $t['task_hora_inicio'] ?? null;
            $thFim = $t['task_hora_fim'] ?? null;
            if ((int)$t['id'] === $taskId) {
                $thIni = $ti;
                $thFim = $tf;
            }
            if ($thIni && $thFim) {
                $totalMins += $toMins($thFim) - $toMins($thIni);
            }
        }

        if ($totalMins > $blockDurationMins) {
            throw new \InvalidArgumentException('A soma das durações das tarefas excede o total do bloco.');
        }

        $hi = strlen($horaInicio) === 5 ? $horaInicio . ':00' : $horaInicio;
        $hf = strlen($horaFim) === 5 ? $horaFim . ':00' : $horaFim;
        $stmt = $db->prepare("UPDATE agenda_block_tasks SET hora_inicio = ?, hora_fim = ? WHERE bloco_id = ? AND task_id = ?");
        $stmt->execute([$hi, $hf, $blockId, $taskId]);

        if ($stmt->rowCount() === 0) {
            throw new \InvalidArgumentException('Tarefa não está vinculada a este bloco.');
        }
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
     * Verifica se a tabela agenda_block_projects existe
     */
    private static function hasBlockProjectsTable(\PDO $db): bool
    {
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'agenda_block_projects'");
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }
    
    /**
     * Retorna projetos vinculados ao bloco (Projeto Foco + adicionados).
     * Permite pré-vincular projetos mesmo com bloco planejado.
     */
    public static function getProjectsForBlock(int $blockId): array
    {
        $db = DB::getConnection();
        $bloco = self::getBlockById($blockId);
        if (!$bloco) {
            return [];
        }
        
        $result = [];
        $seen = [];
        
        // 1. Projeto Foco sempre primeiro (se definido)
        if (!empty($bloco['projeto_foco_id'])) {
            $pid = (int) $bloco['projeto_foco_id'];
            $stmt = $db->prepare("SELECT id, name FROM projects WHERE id = ?");
            $stmt->execute([$pid]);
            $p = $stmt->fetch();
            if ($p) {
                $result[] = [
                    'id' => (int) $p['id'],
                    'name' => $p['name'],
                    'is_foco' => true,
                ];
                $seen[$pid] = true;
            }
        }
        
        // 2. Projetos adicionados via agenda_block_projects
        if (self::hasBlockProjectsTable($db)) {
            $stmt = $db->prepare("
                SELECT p.id, p.name
                FROM agenda_block_projects abp
                INNER JOIN projects p ON abp.project_id = p.id
                WHERE abp.block_id = ?
                ORDER BY abp.created_at ASC
            ");
            $stmt->execute([$blockId]);
            foreach ($stmt->fetchAll() as $p) {
                $pid = (int) $p['id'];
                if (!isset($seen[$pid])) {
                    $result[] = [
                        'id' => $pid,
                        'name' => $p['name'],
                        'is_foco' => ($bloco['projeto_foco_id'] ?? null) == $pid,
                    ];
                    $seen[$pid] = true;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Adiciona projeto ao bloco (pré-vínculo).
     */
    public static function addProjectToBlock(int $blockId, int $projectId): void
    {
        $db = DB::getConnection();
        if (!self::hasBlockProjectsTable($db)) {
            throw new \RuntimeException('Funcionalidade não disponível. Execute a migration.');
        }
        
        $bloco = self::getBlockById($blockId);
        if (!$bloco) {
            throw new \RuntimeException('Bloco não encontrado');
        }
        
        $stmt = $db->prepare("SELECT 1 FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException('Projeto não encontrado');
        }
        
        $stmt = $db->prepare("INSERT IGNORE INTO agenda_block_projects (block_id, project_id) VALUES (?, ?)");
        $stmt->execute([$blockId, $projectId]);
    }
    
    /**
     * Remove projeto do bloco. Se for projeto_foco_id, define como null.
     */
    public static function removeProjectFromBlock(int $blockId, int $projectId): void
    {
        $db = DB::getConnection();
        if (!self::hasBlockProjectsTable($db)) {
            throw new \RuntimeException('Funcionalidade não disponível.');
        }
        
        $bloco = self::getBlockById($blockId);
        if ($bloco && !empty($bloco['projeto_foco_id']) && (int)$bloco['projeto_foco_id'] === $projectId) {
            $stmt = $db->prepare("UPDATE agenda_blocks SET projeto_foco_id = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$blockId]);
        }
        
        $stmt = $db->prepare("DELETE FROM agenda_block_projects WHERE block_id = ? AND project_id = ?");
        $stmt->execute([$blockId, $projectId]);
    }
    
    /**
     * Verifica se a tabela agenda_block_segments existe
     */
    private static function hasSegmentsTable(\PDO $db): bool
    {
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'agenda_block_segments'");
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }
    
    /**
     * Retorna o segmento em execução (running) do bloco, se existir
     */
    public static function getRunningSegmentForBlock(int $blockId): ?array
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            return null;
        }
        $stmt = $db->prepare("
            SELECT s.*, p.name as project_name
            FROM agenda_block_segments s
            LEFT JOIN projects p ON s.project_id = p.id
            WHERE s.block_id = ? AND s.status = 'running'
            ORDER BY s.started_at DESC
            LIMIT 1
        ");
        $stmt->execute([$blockId]);
        $seg = $stmt->fetch();
        return $seg ?: null;
    }
    
    /**
     * Retorna todos os segmentos do bloco (para UI e relatório)
     */
    public static function getSegmentsForBlock(int $blockId): array
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            return [];
        }
        $stmt = $db->prepare("
            SELECT s.*, p.name as project_name,
                   t.title as task_title,
                   COALESCE(bt.nome, bbt.nome) as tipo_nome
            FROM agenda_block_segments s
            LEFT JOIN projects p ON s.project_id = p.id
            LEFT JOIN tasks t ON s.task_id = t.id
            LEFT JOIN agenda_block_types bt ON s.tipo_id = bt.id
            LEFT JOIN agenda_blocks b ON s.block_id = b.id
            LEFT JOIN agenda_block_types bbt ON b.tipo_id = bbt.id
            WHERE s.block_id = ?
            ORDER BY s.started_at ASC
        ");
        $stmt->execute([$blockId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Retorna tempo acumulado por projeto no bloco (em segundos)
     */
    public static function getSegmentTotalsByProjectForBlock(int $blockId): array
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            return [];
        }
        $stmt = $db->prepare("
            SELECT 
                s.project_id,
                COALESCE(p.name, 'Tarefas avulsas') as project_name,
                SUM(
                    CASE 
                        WHEN s.status = 'running' THEN TIMESTAMPDIFF(SECOND, s.started_at, NOW())
                        ELSE COALESCE(s.duration_seconds, TIMESTAMPDIFF(SECOND, s.started_at, COALESCE(s.ended_at, NOW())))
                    END
                ) as total_seconds
            FROM agenda_block_segments s
            LEFT JOIN projects p ON s.project_id = p.id
            WHERE s.block_id = ?
            GROUP BY s.project_id, COALESCE(p.name, 'Tarefas avulsas')
        ");
        $stmt->execute([$blockId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Retorna informações de exibição por projeto para a UI (Início, Fim, Duração).
     * Útil para colunas dinâmicas no modo de trabalho do bloco.
     *
     * @return array Map project_id (ou 'avulsas') => ['started_at'=>?, 'ended_at'=>?, 'total_seconds'=>?, 'is_running'=>bool]
     */
    public static function getSegmentDisplayInfoForBlock(int $blockId): array
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            return [];
        }
        $running = self::getRunningSegmentForBlock($blockId);
        $totals = self::getSegmentTotalsByProjectForBlock($blockId);
        $segments = self::getSegmentsForBlock($blockId);
        $out = [];
        foreach ($totals as $t) {
            $pid = $t['project_id'] ?? 'avulsas';
            $key = ($pid === null || $pid === '') ? 'avulsas' : (int)$pid;
            $projSegments = array_filter($segments, function ($s) use ($pid) {
                $sp = $s['project_id'] ?? null;
                if ($pid === 'avulsas') {
                    return $sp === null || $sp === '';
                }
                return (int)($sp ?? 0) === (int)$pid;
            });
            usort($projSegments, function ($a, $b) {
                return strcmp($b['started_at'] ?? '', $a['started_at'] ?? '');
            });
            $last = $projSegments[0] ?? null;
            $isRunning = $running && (
                ($pid === 'avulsas' && (($running['project_id'] ?? null) === null || ($running['project_id'] ?? '') === '')) ||
                ($pid !== 'avulsas' && (int)($running['project_id'] ?? 0) === (int)$pid)
            );
            $out[$key] = [
                'started_at' => $last['started_at'] ?? null,
                'ended_at' => $isRunning ? null : ($last['ended_at'] ?? null),
                'total_seconds' => (int)($t['total_seconds'] ?? 0),
                'is_running' => (bool)$isRunning,
            ];
        }
        return $out;
    }
    
    /**
     * Inicia um segmento de trabalho (projeto) no bloco.
     * Valida que não há outro segmento running no mesmo block_id.
     * @param int|null $tipoId Tipo de bloco (ex.: Comercial) - quando diferente do bloco atual (interrupção)
     */
    public static function startSegment(int $blockId, ?int $projectId = null, ?int $taskId = null, ?int $tipoId = null): array
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            throw new \RuntimeException('Funcionalidade de segmentos não disponível. Execute a migration.');
        }
        
        $bloco = self::getBlockById($blockId);
        if (!$bloco) {
            throw new \RuntimeException('Bloco não encontrado');
        }
        // Se bloco está planejado, inicia o bloco automaticamente (inicialização por projeto)
        if ($bloco['status'] === 'planned') {
            self::startBlock($blockId);
        } elseif ($bloco['status'] !== 'ongoing') {
            throw new \RuntimeException('O bloco precisa estar planejado ou em andamento para iniciar um projeto.');
        }
        
        // Auto-finaliza o segmento em execução antes de iniciar outro (apenas um ativo por vez)
        $running = self::getRunningSegmentForBlock($blockId);
        if ($running) {
            self::pauseSegment($blockId);
        }
        
        // Se tipo_id não informado, usa o tipo do bloco
        $tipoIdFinal = $tipoId ?? (int)($bloco['tipo_id'] ?? 0);
        if ($tipoIdFinal <= 0) {
            $tipoIdFinal = null;
        }
        
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO agenda_block_segments (block_id, tipo_id, project_id, task_id, status, started_at)
            VALUES (?, ?, ?, ?, 'running', ?)
        ");
        $stmt->execute([$blockId, $tipoIdFinal, $projectId, $taskId, $now]);
        $id = (int) $db->lastInsertId();
        
        return [
            'id' => $id,
            'block_id' => $blockId,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'status' => 'running',
            'started_at' => $now,
        ];
    }
    
    /**
     * Pausa o segmento em execução no bloco.
     */
    public static function pauseSegment(int $blockId): void
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            throw new \RuntimeException('Funcionalidade de segmentos não disponível.');
        }
        
        $running = self::getRunningSegmentForBlock($blockId);
        if (!$running) {
            throw new \RuntimeException('Não há projeto em execução neste bloco para pausar.');
        }
        
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        $duration = (int) (strtotime($now) - strtotime($running['started_at']));
        
        $stmt = $db->prepare("
            UPDATE agenda_block_segments
            SET status = 'paused', ended_at = ?, duration_seconds = ?
            WHERE id = ?
        ");
        $stmt->execute([$now, $duration, $running['id']]);
    }
    
    /**
     * Cria um segmento com horários manuais (entrada tipo planilha).
     * Usa a data do bloco + hora_inicio e hora_fim informados.
     */
    public static function createSegmentManual(int $blockId, ?int $projectId, ?int $taskId, ?int $tipoId, string $horaInicio, string $horaFim): array
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            throw new \RuntimeException('Funcionalidade de segmentos não disponível.');
        }
        $bloco = self::getBlockById($blockId);
        if (!$bloco) {
            throw new \RuntimeException('Bloco não encontrado');
        }
        $dataStr = $bloco['data'] ?? date('Y-m-d');
        if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($horaInicio)) || !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($horaFim))) {
            throw new \RuntimeException('Horário inválido. Use formato HH:MM ou HH:MM:SS.');
        }
        $startedAt = $dataStr . ' ' . self::normalizeTime(trim($horaInicio));
        $endedAt = $dataStr . ' ' . self::normalizeTime(trim($horaFim));
        $duration = (int) (strtotime($endedAt) - strtotime($startedAt));
        if ($duration < 0) {
            throw new \RuntimeException('Horário de fim deve ser após o horário de início.');
        }
        $tipoIdFinal = $tipoId ?? (int)($bloco['tipo_id'] ?? 0);
        if ($tipoIdFinal <= 0) {
            $tipoIdFinal = null;
        }
        $stmt = $db->prepare("
            INSERT INTO agenda_block_segments (block_id, tipo_id, project_id, task_id, status, started_at, ended_at, duration_seconds)
            VALUES (?, ?, ?, ?, 'done', ?, ?, ?)
        ");
        $stmt->execute([$blockId, $tipoIdFinal, $projectId, $taskId, $startedAt, $endedAt, $duration]);
        return ['id' => (int)$db->lastInsertId(), 'started_at' => $startedAt, 'ended_at' => $endedAt, 'duration_seconds' => $duration];
    }
    
    /**
     * Atualiza um segmento com horários manuais.
     */
    public static function updateSegment(int $segmentId, ?int $projectId, ?int $taskId, ?int $tipoId, string $horaInicio, string $horaFim): void
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            throw new \RuntimeException('Funcionalidade de segmentos não disponível.');
        }
        $stmt = $db->prepare("SELECT s.*, b.data FROM agenda_block_segments s INNER JOIN agenda_blocks b ON s.block_id = b.id WHERE s.id = ?");
        $stmt->execute([$segmentId]);
        $seg = $stmt->fetch();
        if (!$seg) {
            throw new \RuntimeException('Registro não encontrado.');
        }
        $dataStr = $seg['data'] ?? date('Y-m-d');
        if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($horaInicio)) || !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($horaFim))) {
            throw new \RuntimeException('Horário inválido. Use formato HH:MM ou HH:MM:SS.');
        }
        $startedAt = $dataStr . ' ' . self::normalizeTime(trim($horaInicio));
        $endedAt = $dataStr . ' ' . self::normalizeTime(trim($horaFim));
        $duration = (int) (strtotime($endedAt) - strtotime($startedAt));
        if ($duration < 0) {
            throw new \RuntimeException('Horário de fim deve ser após o horário de início.');
        }
        $tipoIdFinal = $tipoId > 0 ? $tipoId : null;
        $stmt = $db->prepare("
            UPDATE agenda_block_segments
            SET project_id = ?, task_id = ?, tipo_id = ?, started_at = ?, ended_at = ?, duration_seconds = ?
            WHERE id = ?
        ");
        $stmt->execute([$projectId, $taskId, $tipoIdFinal, $startedAt, $endedAt, $duration, $segmentId]);
    }
    
    /**
     * Remove um segmento.
     */
    public static function deleteSegment(int $segmentId): void
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            throw new \RuntimeException('Funcionalidade de segmentos não disponível.');
        }
        $stmt = $db->prepare("DELETE FROM agenda_block_segments WHERE id = ?");
        $stmt->execute([$segmentId]);
    }
    
    /**
     * Fecha todos os segmentos running do bloco (ao encerrar bloco).
     */
    public static function closeRunningSegmentsForBlock(int $blockId): void
    {
        $db = DB::getConnection();
        if (!self::hasSegmentsTable($db)) {
            return;
        }
        $running = self::getRunningSegmentForBlock($blockId);
        if (!$running) {
            return;
        }
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        $duration = (int) (strtotime($now) - strtotime($running['started_at']));
        $stmt = $db->prepare("
            UPDATE agenda_block_segments
            SET status = 'done', ended_at = ?, duration_seconds = ?
            WHERE id = ?
        ");
        $stmt->execute([$now, $duration, $running['id']]);
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
        
        // Fecha automaticamente qualquer segmento running (multi-projeto)
        self::closeRunningSegmentsForBlock($blockId);
        
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
     * Retorna horas por projeto para relatório (segmentos têm prioridade, fallback: projeto_foco)
     */
    public static function getHorasPorProjetoForReport(string $dataInicio, string $dataFim): array
    {
        $db = DB::getConnection();
        $totals = []; // project_id => ['id' => , 'projeto_nome' => , 'minutos_total' => ]
        
        if (self::hasSegmentsTable($db)) {
            $stmt = $db->prepare("
                SELECT 
                    s.project_id as id,
                    COALESCE(p.name, 'Tarefas avulsas') as projeto_nome,
                    SUM(COALESCE(s.duration_seconds, 0)) / 60 as minutos_total
                FROM agenda_block_segments s
                INNER JOIN agenda_blocks b ON s.block_id = b.id
                LEFT JOIN projects p ON s.project_id = p.id
                WHERE b.data BETWEEN ? AND ?
                AND s.status IN ('paused', 'done')
                GROUP BY s.project_id, COALESCE(p.name, 'Tarefas avulsas')
            ");
            $stmt->execute([$dataInicio, $dataFim]);
            foreach ($stmt->fetchAll() as $row) {
                $pid = $row['id'] ?? 'avulsas';
                if (!isset($totals[$pid])) {
                    $totals[$pid] = ['id' => $row['id'], 'projeto_nome' => $row['projeto_nome'], 'minutos_total' => 0];
                }
                $totals[$pid]['minutos_total'] += (float) $row['minutos_total'];
            }
        }
        
        // Fallback: blocos com projeto_foco_id que não têm segmentos (ou para completar)
        $stmt = $db->prepare("
            SELECT b.id as block_id, b.projeto_foco_id, b.duracao_real, b.duracao_planejada
            FROM agenda_blocks b
            WHERE b.data BETWEEN ? AND ?
            AND b.projeto_foco_id IS NOT NULL
            AND b.status IN ('completed', 'partial')
        ");
        $stmt->execute([$dataInicio, $dataFim]);
        $blocos = $stmt->fetchAll();
        
        foreach ($blocos as $bloco) {
            $minutos = (int) ($bloco['duracao_real'] ?? $bloco['duracao_planejada'] ?? 0);
            if ($minutos <= 0) continue;
            
            $hasSegments = false;
            if (self::hasSegmentsTable($db)) {
                $stmt2 = $db->prepare("SELECT 1 FROM agenda_block_segments WHERE block_id = ? LIMIT 1");
                $stmt2->execute([$bloco['block_id']]);
                $hasSegments = $stmt2->rowCount() > 0;
            }
            
            if (!$hasSegments) {
                $pid = $bloco['projeto_foco_id'];
                if (!isset($totals[$pid])) {
                    $stmtP = $db->prepare("SELECT name FROM projects WHERE id = ?");
                    $stmtP->execute([$pid]);
                    $proj = $stmtP->fetch();
                    $totals[$pid] = ['id' => $pid, 'projeto_nome' => $proj['name'] ?? 'Projeto', 'minutos_total' => 0];
                }
                $totals[$pid]['minutos_total'] += $minutos;
            }
        }
        
        $result = array_values($totals);
        usort($result, fn($a, $b) => (int)($b['minutos_total'] ?? 0) <=> (int)($a['minutos_total'] ?? 0));
        return $result;
    }
    
    /**
     * Relatório semanal de produtividade
     *
     * @param \DateTime $dataInicio Data de início da semana
     * @param \DateTime|null $dataFim Opcional; se null, usa +6 dias (semana)
     * @return array Dados do relatório
     */
    public static function getWeeklyReport(\DateTime $dataInicio, ?\DateTime $dataFim = null): array
    {
        $db = DB::getConnection();

        $dataInicio->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
        if ($dataFim === null) {
            $dataFim = clone $dataInicio;
            $dataFim->modify('+6 days');
        } else {
            $dataFim->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
        }

        $dataInicioStr = $dataInicio->format('Y-m-d');
        $dataFimStr = $dataFim->format('Y-m-d');
        
        // Horas por tipo de bloco
        // Blocos: contagem por tipo do bloco (b.tipo_id); minutos: COALESCE(segmento.tipo_id, bloco.tipo_id)
        $stmt = $db->prepare("
            SELECT 
                bt.id as tipo_id,
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
        
        // Minutos por tipo: segmentos usam COALESCE(s.tipo_id, b.tipo_id); blocos sem segmentos usam b.tipo_id
        $minutosPorTipo = [];
        if (self::hasSegmentsTable($db)) {
            try {
                $stmt = $db->prepare("
                    SELECT COALESCE(s.tipo_id, b.tipo_id) as tipo_id,
                           SUM(COALESCE(s.duration_seconds, 0)) / 60 as minutos
                    FROM agenda_block_segments s
                    INNER JOIN agenda_blocks b ON s.block_id = b.id
                    WHERE b.data BETWEEN ? AND ?
                    AND s.status IN ('paused', 'done')
                    GROUP BY COALESCE(s.tipo_id, b.tipo_id)
                ");
                $stmt->execute([$dataInicioStr, $dataFimStr]);
                foreach ($stmt->fetchAll() as $row) {
                    $tid = (int)$row['tipo_id'];
                    $minutosPorTipo[$tid] = ($minutosPorTipo[$tid] ?? 0) + (float)$row['minutos'];
                }
                $stmt = $db->prepare("
                    SELECT b.tipo_id, SUM(COALESCE(b.duracao_real, b.duracao_planejada)) as minutos
                    FROM agenda_blocks b
                    WHERE b.data BETWEEN ? AND ?
                    AND b.status IN ('completed', 'partial')
                    AND NOT EXISTS (SELECT 1 FROM agenda_block_segments s WHERE s.block_id = b.id LIMIT 1)
                    GROUP BY b.tipo_id
                ");
                $stmt->execute([$dataInicioStr, $dataFimStr]);
                foreach ($stmt->fetchAll() as $row) {
                    $tid = (int)$row['tipo_id'];
                    $minutosPorTipo[$tid] = ($minutosPorTipo[$tid] ?? 0) + (float)$row['minutos'];
                }
            } catch (\PDOException $e) {
                // Tabela/coluna tipo_id pode não existir - manter minutos_total do bloco
                $minutosPorTipo = null;
            }
        } else {
            $minutosPorTipo = null;
        }
        // Mescla minutos em horas_por_tipo (quando temos segmentos com tipo_id)
        if ($minutosPorTipo !== null) {
            $horasPorTipoIndexed = [];
            foreach ($horasPorTipo as $h) {
                $tid = (int)$h['tipo_id'];
                $h['minutos_total'] = (int)round($minutosPorTipo[$tid] ?? 0);
                unset($minutosPorTipo[$tid]);
                $horasPorTipoIndexed[$tid] = $h;
            }
            foreach ($minutosPorTipo as $tid => $min) {
                $stmt = $db->prepare("SELECT id, codigo, nome FROM agenda_block_types WHERE id = ?");
                $stmt->execute([$tid]);
                $bt = $stmt->fetch();
                if ($bt) {
                    $horasPorTipoIndexed[$tid] = [
                        'tipo_id' => $tid,
                        'codigo' => $bt['codigo'],
                        'nome' => $bt['nome'],
                        'blocos_total' => 0,
                        'blocos_concluidos' => 0,
                        'blocos_parciais' => 0,
                        'blocos_cancelados' => 0,
                        'minutos_total' => (int)round($min),
                    ];
                }
            }
            $horasPorTipo = array_values($horasPorTipoIndexed);
            usort($horasPorTipo, fn($a, $b) => strcmp($a['nome'] ?? '', $b['nome'] ?? ''));
        }
        
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
        
        // Horas por projeto (prioridade: segmentos quando existem, fallback: projeto_foco do bloco)
        $horasPorProjeto = self::getHorasPorProjetoForReport($dataInicioStr, $dataFimStr);
        
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
        
        // Tarefas concluídas por data de conclusão (completed_at) - evolução futura
        // Inclui TODAS as tarefas concluídas no período, independente de vínculo com agenda
        $tarefasConcluidasPorData = \PixelHub\Services\TaskService::getTasksCompletedInPeriod($dataInicioStr, $dataFimStr);
        
        return [
            'periodo' => [
                'inicio' => $dataInicioStr,
                'fim' => $dataFimStr,
            ],
            'horas_por_tipo' => $horasPorTipo,
            'tarefas_por_tipo' => $tarefasPorTipo,
            'tarefas_concluidas_por_data' => $tarefasConcluidasPorData,
            'horas_por_projeto' => $horasPorProjeto,
            'blocos_cancelados' => $blocosCancelados,
        ];
    }
    
    /**
     * Relatório para período arbitrário (reutiliza lógica de getWeeklyReport).
     *
     * @param string $dataInicioStr Y-m-d (inclusive)
     * @param string $dataFimStr Y-m-d (inclusive)
     * @return array Dados do relatório
     */
    public static function getReportForDateRange(string $dataInicioStr, string $dataFimStr): array
    {
        $dataInicio = new \DateTime($dataInicioStr, new \DateTimeZone('America/Sao_Paulo'));
        $dataFim = new \DateTime($dataFimStr, new \DateTimeZone('America/Sao_Paulo'));
        return self::getWeeklyReport($dataInicio, $dataFim);
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
        return self::getReportForDateRange($dataInicio->format('Y-m-d'), $dataFim->format('Y-m-d'));
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

    /**
     * Lista itens manuais da agenda para uma data (se tabela existir)
     */
    public static function getManualItemsForDate(string $dateStr): array
    {
        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT id, title, item_date, time_start, time_end, item_type, notes
                FROM agenda_manual_items
                WHERE item_date = ?
                ORDER BY COALESCE(time_start, '00:00:00') ASC, title ASC
            ");
            $stmt->execute([$dateStr]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Normaliza horário para formato HH:MM:SS (MySQL TIME)
     */
    private static function normalizeTime(string $t): string
    {
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t)) {
            $parts = explode(':', $t);
            return sprintf('%02d:%02d:%02d', (int)$parts[0], (int)$parts[1], (int)($parts[2] ?? 0));
        }
        return $t;
    }

    /**
     * Verifica se já existe item manual similar (evitar duplicação)
     * Considera duplicado: mesmo título, mesma data e mesmo horário de início
     */
    public static function findSimilarManualItem(string $title, string $itemDate, ?string $timeStart): ?array
    {
        try {
            $db = DB::getConnection();
            $timeStart = $timeStart ?: null;
            if ($timeStart === null) {
                $stmt = $db->prepare("
                    SELECT id, title, item_date, time_start
                    FROM agenda_manual_items
                    WHERE LOWER(TRIM(title)) = LOWER(TRIM(?))
                    AND item_date = ?
                    AND (time_start IS NULL OR time_start = '00:00:00')
                    LIMIT 1
                ");
                $stmt->execute([$title, $itemDate]);
            } else {
                $stmt = $db->prepare("
                    SELECT id, title, item_date, time_start
                    FROM agenda_manual_items
                    WHERE LOWER(TRIM(title)) = LOWER(TRIM(?))
                    AND item_date = ?
                    AND time_start = ?
                    LIMIT 1
                ");
                $stmt->execute([$title, $itemDate, $timeStart]);
            }
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Cria item manual na agenda
     * @return int ID do item criado
     * @throws \RuntimeException Se houver duplicação ou erro de validação
     */
    public static function createManualItem(array $data): int
    {
        $title = trim($data['title'] ?? '');
        $itemDate = $data['item_date'] ?? '';
        $timeStart = !empty($data['time_start']) ? self::normalizeTime(trim($data['time_start'])) : null;
        $timeEnd = !empty($data['time_end']) ? self::normalizeTime(trim($data['time_end'])) : null;
        $itemType = !empty($data['item_type']) ? trim($data['item_type']) : 'outro';
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;
        $createdBy = isset($data['created_by']) ? (int)$data['created_by'] : null;

        if (empty($title)) {
            throw new \RuntimeException('Título é obrigatório.');
        }
        if (empty($itemDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $itemDate)) {
            throw new \RuntimeException('Data inválida.');
        }

        $similar = self::findSimilarManualItem($title, $itemDate, $timeStart);
        if ($similar) {
            throw new \RuntimeException(
                'Já existe um compromisso com o mesmo título, data e horário. ' .
                'Edite o existente ou use um título/horário diferente.'
            );
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("
            INSERT INTO agenda_manual_items (title, item_date, time_start, time_end, item_type, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $itemDate, $timeStart, $timeEnd, $itemType, $notes, $createdBy]);
        return (int)$db->lastInsertId();
    }

    /**
     * Lista itens manuais da agenda para um período
     */
    public static function getManualItemsForDateRange(string $startStr, string $endStr): array
    {
        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT id, title, item_date, time_start, time_end, item_type, notes
                FROM agenda_manual_items
                WHERE item_date BETWEEN ? AND ?
                ORDER BY item_date ASC, COALESCE(time_start, '00:00:00') ASC, title ASC
            ");
            $stmt->execute([$startStr, $endStr]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Lista tarefas com prazo em uma data (para agenda unificada "o que fazer")
     * Exclui tarefas concluídas
     */
    public static function getAgendaTasksForDate(string $dateStr): array
    {
        $db = DB::getConnection();
        $hasDeletedAt = self::hasDeletedAtColumn($db);
        $deletedCond = $hasDeletedAt ? 'AND t.deleted_at IS NULL' : '';

        $stmt = $db->prepare("
            SELECT t.id, t.title, t.status, t.due_date, t.start_date, t.project_id,
                   p.name as project_name, t2.name as tenant_name
            FROM tasks t
            INNER JOIN projects p ON t.project_id = p.id
            LEFT JOIN tenants t2 ON p.tenant_id = t2.id
            WHERE (t.due_date = ? OR t.start_date = ?)
            AND t.status NOT IN ('concluida', 'completed')
            $deletedCond
            ORDER BY t.due_date ASC, t.start_date ASC, t.title ASC
        ");
        $stmt->execute([$dateStr, $dateStr]);
        return $stmt->fetchAll();
    }

    /**
     * Lista tarefas com prazo em um período (para agenda unificada "o que fazer")
     */
    public static function getAgendaTasksForDateRange(string $startStr, string $endStr): array
    {
        $db = DB::getConnection();
        $hasDeletedAt = self::hasDeletedAtColumn($db);
        $deletedCond = $hasDeletedAt ? 'AND t.deleted_at IS NULL' : '';

        $stmt = $db->prepare("
            SELECT t.id, t.title, t.status, t.due_date, t.start_date, t.project_id,
                   p.name as project_name, t2.name as tenant_name
            FROM tasks t
            INNER JOIN projects p ON t.project_id = p.id
            LEFT JOIN tenants t2 ON p.tenant_id = t2.id
            WHERE (
                (t.due_date BETWEEN ? AND ?)
                OR (t.start_date BETWEEN ? AND ?)
            )
            AND t.status NOT IN ('concluida', 'completed')
            $deletedCond
            ORDER BY COALESCE(t.due_date, t.start_date) ASC, t.title ASC
        ");
        $stmt->execute([$startStr, $endStr, $startStr, $endStr]);
        return $stmt->fetchAll();
    }

    /**
     * Lista projetos ativos com prazo em um período (para agenda unificada e visão macro)
     */
    public static function getAgendaProjectsForDateRange(string $startStr, string $endStr): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.due_date, p.created_at, t.name as tenant_name
            FROM projects p
            LEFT JOIN tenants t ON p.tenant_id = t.id
            WHERE p.due_date BETWEEN ? AND ?
            AND p.status = 'ativo'
            ORDER BY p.due_date ASC, p.name ASC
        ");
        $stmt->execute([$startStr, $endStr]);
        return $stmt->fetchAll();
    }

    /**
     * Adiciona lista de nomes de projetos do bloco (projeto_foco + adicionados).
     * Usado em Blocos da Semana e "O que tenho para fazer hoje".
     */
    public static function enrichBlocksWithProjectNames(array $blocks): array
    {
        $db = DB::getConnection();
        foreach ($blocks as $key => $b) {
            $projetos = self::getProjectsForBlock((int)$b['id']);
            $blocks[$key]['projetos_nomes'] = array_column($projetos, 'name');
            $blocks[$key]['projetos_nomes_str'] = implode(', ', $blocks[$key]['projetos_nomes']);
            // Fatias por segmento (projeto · tipo) para exibir interrupções no card
            $blocks[$key]['segment_fatias'] = [];
            if (self::hasSegmentsTable($db)) {
                try {
                    $stmt = $db->prepare("
                        SELECT s.project_id, p.name as project_name,
                               COALESCE(bt_seg.nome, bt_block.nome) as tipo_nome
                        FROM agenda_block_segments s
                        LEFT JOIN projects p ON s.project_id = p.id
                        LEFT JOIN agenda_block_types bt_seg ON s.tipo_id = bt_seg.id
                        INNER JOIN agenda_blocks b ON s.block_id = b.id
                        LEFT JOIN agenda_block_types bt_block ON b.tipo_id = bt_block.id
                        WHERE s.block_id = ?
                        ORDER BY s.started_at ASC
                    ");
                    $stmt->execute([$b['id']]);
                    foreach ($stmt->fetchAll() as $seg) {
                        $proj = $seg['project_name'] ?? 'Tarefas avulsas';
                        $tipo = $seg['tipo_nome'] ?? '';
                        $blocks[$key]['segment_fatias'][] = $tipo ? "{$proj} · {$tipo}" : $proj;
                    }
                } catch (\PDOException $e) {
                    // ignora se coluna tipo_id não existir
                }
            }
        }
        return $blocks;
    }
    
    /**
     * Enriquece blocos para exibição na agenda unificada (Minha Agenda).
     * - Filtra apenas blocos com projeto vinculado (sem projeto = não exibe, já tem semanal)
     * - Adiciona tarefas vinculadas e checklist de cada tarefa (subtasks)
     * - Adiciona lista de projetos do bloco (multi-projeto)
     *
     * @param array $blocks Blocos brutos
     * @return array Blocos filtrados e enriquecidos
     */
    public static function enrichBlocksForUnifiedAgenda(array $blocks): array
    {
        $result = [];
        foreach ($blocks as $b) {
            $projetos = self::getProjectsForBlock((int)$b['id']);
            if (empty($projetos) && empty($b['projeto_foco_id'])) {
                continue; // Sem projeto = não exibe em Minha Agenda
            }
            $b['tipo_cor_hex'] = $b['tipo_cor'] ?? $b['tipo_cor_hex'] ?? '#cccccc';
            $b['block_tasks'] = [];
            $b['projetos_nomes'] = array_column($projetos, 'name');
            $b['projetos_nomes_str'] = !empty($b['projetos_nomes']) ? implode(', ', $b['projetos_nomes']) : ($b['projeto_foco_nome'] ?? '');
            try {
                $tasks = self::getTasksByBlock((int)$b['id']);
                foreach ($tasks as $t) {
                    $t['checklist'] = TaskChecklistService::getItemsByTask((int)$t['id']);
                    $b['block_tasks'][] = $t;
                }
            } catch (\Exception $e) {
                // ignora
            }
            $result[] = $b;
        }
        return $result;
    }

    /**
     * Retorna itens da agenda para um dia (tarefas + projetos + itens manuais + blocos de tempo)
     */
    public static function getAgendaItemsForDay(string $dateStr): array
    {
        $tasks = self::getAgendaTasksForDate($dateStr);
        $projects = self::getAgendaProjectsForDateRange($dateStr, $dateStr);
        $manualItems = self::getManualItemsForDate($dateStr);
        $blocks = [];
        try {
            $rawBlocks = self::getBlocksByDate($dateStr);
            $blocks = self::enrichBlocksForUnifiedAgenda($rawBlocks);
        } catch (\Exception $e) {
            // ignora se tabela não existir
        }
        return [
            'tasks' => $tasks,
            'projects' => $projects,
            'manual_items' => $manualItems,
            'blocks' => $blocks,
            'date' => $dateStr,
        ];
    }

    /**
     * Retorna itens da agenda para uma semana, agrupados por dia
     */
    public static function getAgendaItemsForWeek(string $startStr, string $endStr): array
    {
        $tasks = self::getAgendaTasksForDateRange($startStr, $endStr);
        $projects = self::getAgendaProjectsForDateRange($startStr, $endStr);
        $manualItems = self::getManualItemsForDateRange($startStr, $endStr);

        $byDay = [];
        $start = new \DateTime($startStr);
        $end = new \DateTime($endStr);
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end->modify('+1 day'));

        foreach ($period as $d) {
            $key = $d->format('Y-m-d');
            $byDay[$key] = [
                'date' => $key,
                'date_formatted' => $d->format('d/m/Y'),
                'tasks' => [],
                'projects' => [],
                'manual_items' => [],
                'blocks' => [],
            ];
        }

        foreach ($tasks as $t) {
            $d = $t['due_date'] ?? $t['start_date'] ?? null;
            if ($d && isset($byDay[$d])) {
                $byDay[$d]['tasks'][] = $t;
            }
        }
        foreach ($projects as $p) {
            $d = $p['due_date'] ?? null;
            if ($d && isset($byDay[$d])) {
                $byDay[$d]['projects'][] = $p;
            }
        }
        foreach ($manualItems as $m) {
            $d = $m['item_date'] ?? null;
            if ($d && isset($byDay[$d])) {
                $byDay[$d]['manual_items'][] = $m;
            }
        }

        try {
            $dataInicio = new \DateTime($startStr);
            $dataFim = new \DateTime($endStr);
            $blocosPorDia = self::getBlocksForPeriod($dataInicio, $dataFim);
            foreach ($blocosPorDia as $dataIso => $blocos) {
                if (isset($byDay[$dataIso])) {
                    $byDay[$dataIso]['blocks'] = self::enrichBlocksForUnifiedAgenda($blocos);
                }
            }
        } catch (\Exception $e) {
            // ignora se tabela não existir
        }

        return [
            'by_day' => $byDay,
            'tasks' => $tasks,
            'projects' => $projects,
            'manual_items' => $manualItems,
        ];
    }

    /**
     * Lista projetos ativos com prazo para visão macro (timeline)
     * Período: próximas 4 semanas a partir de hoje
     */
    public static function getProjectsForTimeline(?string $startStr = null, ?string $endStr = null): array
    {
        $db = DB::getConnection();
        $tz = new \DateTimeZone('America/Sao_Paulo');
        $today = (new \DateTime('now', $tz))->format('Y-m-d');
        $startStr = $startStr ?? $today;
        $end = $endStr ? new \DateTime($endStr) : (new \DateTime($today, $tz))->modify('+28 days');
        $endStr = $end->format('Y-m-d');

        $stmt = $db->prepare("
            SELECT p.id, p.name, p.due_date, p.created_at, t.name as tenant_name
            FROM projects p
            LEFT JOIN tenants t ON p.tenant_id = t.id
            WHERE p.due_date >= ?
            AND p.status = 'ativo'
            ORDER BY p.due_date ASC, p.name ASC
        ");
        $stmt->execute([$startStr]);
        $all = $stmt->fetchAll();
        return array_filter($all, fn($p) => $p['due_date'] <= $endStr);
    }
}

