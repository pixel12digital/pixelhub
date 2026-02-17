<?php

/**
 * Script de Migração: Leads → Tenants (Contatos Unificados)
 * 
 * Este script migra todos os leads da tabela leads para a tabela tenants,
 * mantendo a integridade dos dados e preservando relacionamentos.
 * Usa as configurações do .env existente no projeto.
 * 
 * EXECUÇÃO:
 * php scripts/migrate_leads_to_tenants_env.php [--dry-run] [--force]
 * 
 * OPTIONS:
 * --dry-run  : Simula a migração sem executar (recomendado primeiro)
 * --force    : Executa a migração mesmo que já tenha sido executada
 */

// Carrega autoload do framework
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;

// Carrega configurações do .env
Env::load();

// Obtém configurações do banco
$host = Env::get('DB_HOST', 'localhost');
$port = Env::get('DB_PORT', '3306');
$database = Env::get('DB_NAME', 'pixelhub');
$username = Env::get('DB_USER', 'root');
$password = Env::get('DB_PASS', '');

class LeadMigration
{
    private $pdo;
    private $dryRun;
    private $force;
    
    public function __construct(PDO $pdo, bool $dryRun = false, bool $force = false)
    {
        $this->pdo = $pdo;
        $this->dryRun = $dryRun;
        $this->force = $force;
    }
    
    /**
     * Executa a migração completa
     */
    public function migrate(): void
    {
        echo "=== MIGRAÇÃO LEADS → TENANTS ===\n";
        echo "Modo: " . ($this->dryRun ? "DRY RUN (simulação)" : "EXECUÇÃO REAL") . "\n\n";
        
        // 1. Verifica pré-requisitos
        $this->checkPrerequisites();
        
        // 2. Verifica se já foi executada
        if (!$this->force && $this->alreadyMigrated()) {
            echo "⚠️  Migração já executada anteriormente. Use --force para executar novamente.\n";
            return;
        }
        
        // 3. Conta leads a migrar
        $leadsCount = $this->countLeads();
        echo "📊 Leads encontrados: {$leadsCount}\n";
        
        if ($leadsCount === 0) {
            echo "✅ Nenhum lead para migrar.\n";
            return;
        }
        
        // 4. Executa migração
        $this->performMigration($leadsCount);
        
        // 5. Validação
        if (!$this->dryRun) {
            $this->validateMigration();
        }
        
        echo "\n✅ Migração concluída com sucesso!\n";
    }
    
    /**
     * Verifica pré-requisitos
     */
    private function checkPrerequisites(): void
    {
        // Verifica se tabela tenants tem os novos campos
        $requiredFields = ['contact_type', 'source', 'notes', 'created_by', 'lead_converted_at', 'original_lead_id'];
        
        foreach ($requiredFields as $field) {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM tenants LIKE '{$field}'");
            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException("Campo '{$field}' não encontrado na tabela tenants. Execute a migration migrate_add_lead_fields_env.php primeiro.");
            }
        }
        
        echo "✅ Pré-requisitos verificados\n";
    }
    
    /**
     * Verifica se migração já foi executada
     */
    private function alreadyMigrated(): bool
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM tenants WHERE contact_type = 'lead' AND original_lead_id IS NOT NULL");
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Conta leads a migrar
     */
    private function countLeads(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM leads WHERE status != 'converted'");
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Executa a migração
     */
    private function performMigration(int $totalLeads): void
    {
        $batchSize = 100;
        $processed = 0;
        $duplicates = 0;
        
        echo "\n🔄 Iniciando migração (batch size: {$batchSize})...\n";
        
        // Busca leads em lotes (MariaDB não suporta LIMIT com OFFSET em prepared statements)
        $offset = 0;
        while ($offset < $totalLeads) {
            $sql = "SELECT * FROM leads WHERE status != 'converted' ORDER BY id ASC LIMIT " . ($batchSize) . " OFFSET " . $offset;
            $stmt = $this->pdo->query($sql);
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($leads)) {
                break;
            }
            
            foreach ($leads as $lead) {
                try {
                    $this->migrateLead($lead);
                    $processed++;
                } catch (\Exception $e) {
                    echo "❌ Erro ao migrar lead #{$lead['id']}: " . $e->getMessage() . "\n";
                    $duplicates++;
                }
                
                // Progresso
                if ($processed % 10 === 0 || $processed === $totalLeads) {
                    $percent = round(($processed / $totalLeads) * 100, 1);
                    echo "Progresso: {$processed}/{$totalLeads} ({$percent}%)\n";
                }
            }
            
            $offset += $batchSize;
        }
        
        echo "\n📊 Resultados:\n";
        echo "- Leads processados: {$processed}\n";
        echo "- Duplicados/erros: {$duplicates}\n";
    }
    
    /**
     * Migra um lead individual
     */
    private function migrateLead(array $lead): void
    {
        // Verifica duplicidade por telefone
        if (!empty($lead['phone'])) {
            $stmt = $this->pdo->prepare("
                SELECT id FROM tenants 
                WHERE phone = ? AND contact_type = 'client'
                LIMIT 1
            ");
            $stmt->execute([$lead['phone']]);
            if ($stmt->fetch()) {
                throw new \RuntimeException("Já existe cliente com este telefone");
            }
        }
        
        if ($this->dryRun) {
            echo "[DRY RUN] Lead #{$lead['id']}: {$lead['name']}\n";
            return;
        }
        
        // Insere lead como tenant
        $stmt = $this->pdo->prepare("
            INSERT INTO tenants (
                name, phone, email, contact_type, source, notes, 
                created_by, lead_converted_at, original_lead_id,
                created_at, updated_at
            ) VALUES (?, ?, ?, 'lead', ?, ?, ?, NULL, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $lead['name'],
            $lead['phone'],
            $lead['email'],
            $lead['source'] ?? 'whatsapp',
            $lead['notes'],
            $lead['created_by'],
            $lead['id'] // original_lead_id
        ]);
        
        $newTenantId = (int) $this->pdo->lastInsertId();
        
        // Atualiza conversas vinculadas
        $stmt = $this->pdo->prepare("
            UPDATE conversations 
            SET tenant_id = ?, lead_id = NULL
            WHERE lead_id = ?
        ");
        $stmt->execute([$newTenantId, $lead['id']]);
        
        // Atualiza opportunities
        $stmt = $this->pdo->prepare("
            UPDATE opportunities 
            SET tenant_id = ?
            WHERE lead_id = ?
        ");
        $stmt->execute([$newTenantId, $lead['id']]);
    }
    
    /**
     * Valida a migração
     */
    private function validateMigration(): void
    {
        echo "\n🔍 Validando migração...\n";
        
        // Verifica se todos os leads foram migrados
        $leadsRemaining = $this->countLeads();
        if ($leadsRemaining > 0) {
            echo "⚠️  Ainda existem {$leadsRemaining} leads não migrados\n";
        }
        
        // Verifica tenants com contact_type = lead
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM tenants WHERE contact_type = 'lead'");
        $leadsAsTenants = $stmt->fetchColumn();
        echo "✅ Leads migrados como tenants: {$leadsAsTenants}\n";
        
        // Verifica relacionamentos
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM conversations 
            WHERE tenant_id IN (SELECT id FROM tenants WHERE contact_type = 'lead')
        ");
        $conversationsLinked = $stmt->fetchColumn();
        echo "✅ Conversas vinculadas: {$conversationsLinked}\n";
        
        // Estatísticas finais
        $stats = $this->pdo->query("
            SELECT 
                contact_type,
                COUNT(*) as count,
                source
            FROM tenants 
            WHERE contact_type = 'lead'
            GROUP BY contact_type, source
            ORDER BY count DESC
        ")->fetchAll();
        
        echo "\n📊 LEADS MIGRADOS POR FONTE:\n";
        foreach ($stats as $stat) {
            echo "- {$stat['source']}: {$stat['count']}\n";
        }
    }
}

// Execução do script
$dryRun = in_array('--dry-run', $argv);
$force = in_array('--force', $argv);

try {
    // Conexão com o banco
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado ao banco de dados: $database\n";
    
    $migration = new LeadMigration($pdo, $dryRun, $force);
    $migration->migrate();
} catch (PDOException $e) {
    echo "🚨 ERRO DE CONEXÃO: " . $e->getMessage() . "\n";
    echo "Verifique se o arquivo .env está configurado corretamente.\n";
    exit(1);
} catch (\Exception $e) {
    echo "🚨 ERRO FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
