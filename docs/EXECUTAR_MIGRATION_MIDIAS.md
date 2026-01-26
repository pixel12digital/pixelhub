# Como Executar a Migration de Mídias

## Problema
O áudio não aparece no Pixel Hub porque a tabela `communication_media` ainda não foi criada.

## Solução
Execute a migration para criar a tabela.

### Opção 1: Via PHP direto (Recomendado)

```bash
cd C:\xampp\htdocs\painel.pixel12digital
php database/migrations/20260116_create_communication_media_table.php
```

### Opção 2: Via SQL direto

Execute no MySQL:

```sql
CREATE TABLE IF NOT EXISTS communication_media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID do evento (FK para communication_events)',
    media_id VARCHAR(255) NOT NULL COMMENT 'ID da mídia no WhatsApp Gateway',
    media_type VARCHAR(50) NOT NULL COMMENT 'Tipo de mídia (audio, image, video, document, sticker)',
    mime_type VARCHAR(100) NULL COMMENT 'MIME type do arquivo',
    stored_path VARCHAR(500) NULL COMMENT 'Caminho relativo do arquivo armazenado',
    file_name VARCHAR(255) NULL COMMENT 'Nome do arquivo',
    file_size INT UNSIGNED NULL COMMENT 'Tamanho do arquivo em bytes',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_event_id (event_id),
    INDEX idx_media_id (media_id),
    INDEX idx_media_type (media_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (event_id) REFERENCES communication_events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Opção 3: Criar migration runner

Se não tiver um runner de migrations, crie o arquivo `database/run-migration-media.php`:

```php
<?php
require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

$migration = new CreateCommunicationMediaTable();
$migration->up($db);

echo "Migration executada com sucesso!\n";
```

Depois execute:
```bash
php database/run-migration-media.php
```

## Verificação

Após executar a migration, verifique se a tabela foi criada:

```sql
SHOW TABLES LIKE 'communication_media';
SELECT * FROM communication_media LIMIT 5;
```

## Processamento de Mídias Existentes

Se você já tem mensagens com mídia no banco, pode reprocessar executando:

```php
<?php
require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;
use PixelHub\Services\WhatsAppMediaService;

$db = DB::getConnection();

// Busca eventos recentes com possível mídia
$stmt = $db->query("
    SELECT event_id, payload 
    FROM communication_events 
    WHERE event_type = 'whatsapp.inbound.message'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
");

while ($event = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $result = WhatsAppMediaService::processMediaFromEvent($event);
    if ($result) {
        echo "Processada mídia para evento: {$event['event_id']}\n";
    }
}

echo "Reprocessamento concluído!\n";
```

## Próximos Passos

1. Execute a migration
2. Reprocesse mensagens existentes (se necessário)
3. Teste enviando uma nova mídia pelo WhatsApp
4. Verifique se a mídia aparece no Pixel Hub








