<?php
/**
 * WORKER DE PROCESSAMENTO WHATSAPP
 * 
 * SEGURANÇA:
 * - Roda em paralelo ao sistema atual
 * - Não modifica código existente
 * - Processa apenas eventos 'queued'
 * - Pode ser parado a qualquer momento
 * - Auto-recuperação de erros
 */

define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'src/Core/Env.php';

PixelHub\Core\Env::load();

$config = require ROOT_PATH . 'config/database.php';

// Configuração do Worker
define('WORKER_SLEEP', 1); // 1 segundo entre ciclos
define('BATCH_SIZE', 3); // Pequeno para não sobrecarregar
define('MAX_ERRORS', 5); // Parar após muitos erros
define('LOG_FILE', ROOT_PATH . 'logs/whatsapp_worker.log');

class WhatsAppWorker {
    private PDO $pdo;
    private int $errors = 0;
    private bool $running = true;
    
    public function __construct(array $config) {
        $this->pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", 
            $config['username'], 
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Handler para shutdown gracioso
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
    }
    
    public function run(): void {
        $this->log("WORKER INICIADO - PID: " . getmypid());
        
        while ($this->running) {
            try {
                $this->processBatch();
                $this->errors = 0; // Reset contador de erros
                
                if ($this->errors >= MAX_ERRORS) {
                    $this->log("Muitos erros, parando worker");
                    break;
                }
                
                sleep(WORKER_SLEEP);
                
            } catch (Exception $e) {
                $this->errors++;
                $this->log("ERRO: " . $e->getMessage());
                
                if ($this->errors >= MAX_ERRORS) {
                    $this->log("Máximo de erros atingido, parando");
                    break;
                }
                
                sleep(WORKER_SLEEP * 2); // Espera mais após erro
            }
        }
        
        $this->log("WORKER FINALIZADO");
    }
    
    private function processBatch(): void {
        // Buscar eventos em fila
        $sql = "SELECT id, event_type, payload, tenant_id, created_at
                FROM communication_events 
                WHERE status = 'queued' 
                ORDER BY created_at ASC 
                LIMIT " . BATCH_SIZE;
        
        $stmt = $this->pdo->query($sql);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($events)) {
            return; // Nada para processar
        }
        
        $this->log("Processando " . count($events) . " eventos");
        
        foreach ($events as $event) {
            if (!$this->running) break;
            
            $this->processEvent($event);
        }
    }
    
    private function processEvent(array $event): void {
        $eventId = $event['id'];
        
        try {
            // Marcar como processando (evita conflito)
            $this->pdo->beginTransaction();
            
            $updateSql = "UPDATE communication_events 
                          SET status = 'processing', processed_at = NOW() 
                          WHERE id = ? AND status = 'queued'";
            $stmt = $this->pdo->prepare($updateSql);
            $affected = $stmt->execute([$eventId]);
            
            if ($affected == 0) {
                $this->pdo->rollBack();
                return; // Já sendo processado
            }
            
            // Processar evento
            $success = $this->processEventData($event);
            
            if ($success) {
                // Marcar como processado
                $completeSql = "UPDATE communication_events 
                               SET status = 'processed', processed_at = NOW() 
                               WHERE id = ?";
                $stmt = $this->pdo->prepare($completeSql);
                $stmt->execute([$eventId]);
                
                $this->pdo->commit();
                $this->log("✅ Evento $eventId processado");
                
            } else {
                throw new Exception("Falha no processamento do evento");
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            // Marcar como failed para retry futuro
            $failSql = "UPDATE communication_events 
                       SET status = 'failed', error_message = ?, processed_at = NOW() 
                       WHERE id = ?";
            $stmt = $this->pdo->prepare($failSql);
            $stmt->execute([$e->getMessage(), $eventId]);
            
            $this->log("❌ Evento $eventId falhou: " . $e->getMessage());
        }
    }
    
    private function processEventData(array $event): bool {
        $payload = json_decode($event['payload'], true);
        
        if (!$payload) {
            return false;
        }
        
        // Processar diferentes tipos de eventos
        switch ($event['event_type']) {
            case 'whatsapp.inbound.message':
                return $this->processInboundMessage($payload, $event['tenant_id']);
                
            case 'whatsapp.outbound.message':
                return $this->processOutboundMessage($payload, $event['tenant_id']);
                
            default:
                $this->log("Tipo de evento não tratado: {$event['event_type']}");
                return true; // Marcar como processado mesmo assim
        }
    }
    
    private function processInboundMessage(array $payload, ?string $tenantId): bool {
        $message = $payload['message'] ?? [];
        $from = $message['from'] ?? '';
        $text = $message['text'] ?? '';
        
        if (!$from || !$tenantId) {
            return false;
        }
        
        // Normalizar telefone
        $phone = $this->normalizePhone($from);
        
        // Verificar/criar conversa
        return $this->ensureConversation($phone, $tenantId, $payload);
    }
    
    private function processOutboundMessage(array $payload, ?string $tenantId): bool {
        // Para mensagens outbound, apenas registrar
        return true;
    }
    
    private function ensureConversation(string $phone, string $tenantId, array $payload): bool {
        // Verificar se conversa já existe
        $checkSql = "SELECT id FROM conversations 
                    WHERE contact_external_id = ? AND tenant_id = ?";
        $stmt = $this->pdo->prepare($checkSql);
        $stmt->execute([$phone, $tenantId]);
        
        if ($stmt->fetch()) {
            return true; // Conversa já existe
        }
        
        // Criar nova conversa
        $insertSql = "INSERT INTO conversations 
                      (conversation_key, channel_type, channel_account_id, 
                       contact_external_id, contact_name, tenant_id, 
                       status, created_at, updated_at, last_message_at, 
                       message_count, unread_count)
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), 1, 1)";
        
        $stmt = $this->pdo->prepare($insertSql);
        $conversationKey = 'whatsapp_' . $tenantId . '_' . $phone;
        $contactName = $this->extractContactName($payload);
        
        $success = $stmt->execute([
            $conversationKey,
            'whatsapp',
            $tenantId,
            $phone,
            $contactName,
            $tenantId,
            'new'
        ]);
        
        if ($success) {
            $this->log("📝 Nova conversa criada: $phone ($contactName)");
        }
        
        return $success;
    }
    
    private function normalizePhone(string $phone): string {
        // Remove @lid, @c.us, etc.
        $phone = preg_replace('/@.*$/', '', $phone);
        
        // Remove caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Adiciona +55 se for número brasileiro
        if (strlen($phone) >= 8 && strlen($phone) <= 11 && !str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }
        
        return '+' . $phone;
    }
    
    private function extractContactName(array $payload): string {
        $message = $payload['message'] ?? [];
        $raw = $message['raw'] ?? [];
        $sender = $raw['sender'] ?? [];
        
        return $sender['pushname'] ?? $sender['formattedName'] ?? 'Unknown';
    }
    
    private function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        echo $logMessage;
        file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public function handleSignal(int $signal): void {
        $this->log("Sinal $signal recebido, finalizando...");
        $this->running = false;
    }
}

// Verificar se já está rodando
$pidFile = ROOT_PATH . 'storage/whatsapp_worker.pid';
if (file_exists($pidFile)) {
    $pid = (int)file_get_contents($pidFile);
    if (posix_kill($pid, 0)) {
        echo "Worker já está rodando (PID: $pid)\n";
        exit(1);
    }
}

// Iniciar worker
$worker = new WhatsAppWorker($config);

// Salvar PID
file_put_contents($pidFile, getmypid());

try {
    $worker->run();
} finally {
    if (file_exists($pidFile)) {
        unlink($pidFile);
    }
}
