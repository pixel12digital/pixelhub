# Verificação de Logs via Navegador

## Acesso

O script está disponível em:
```
https://hub.pixel12digital.com.br/check-logs-webhook.php
```

## Autenticação

**Usuário:** `admin`  
**Senha:** `pixel12hub2024`

⚠️ **IMPORTANTE:** Alterar a senha no arquivo `public/check-logs-webhook.php` após o primeiro uso!

## O que o script verifica

1. **Docker** - Verifica se Docker está disponível
2. **Containers** - Lista containers disponíveis e identifica o do Hub
3. **correlation_id** - Busca o correlation_id do teste nos logs
4. **HUB_WEBHOOK_IN** - Verifica se o webhook chegou ao Hub
5. **HUB_MSG_SAVE** - Verifica se a mensagem foi salva
6. **HUB_MSG_DROP** - Verifica se a mensagem foi descartada
7. **Erros/Exceções** - Busca erros próximos ao horário do teste
8. **Banco de Dados** - Verifica se o evento está no banco

## Segurança

### Alterar Senha

Edite `public/check-logs-webhook.php` e altere:
```php
$_SERVER['PHP_AUTH_PW'] !== 'pixel12hub2024'
```

### Restringir por IP (Opcional)

No arquivo `public/check-logs-webhook.php`, descomente e configure:
```php
$allowedIPs = ['SEU_IP_AQUI'];
```

## Exemplo de Uso

1. Acesse: `https://hub.pixel12digital.com.br/check-logs-webhook.php`
2. Digite usuário e senha quando solicitado
3. O script executará automaticamente e mostrará os resultados
4. Use o link "Atualizar" para executar novamente

## Troubleshooting

### Docker não encontrado
- Verifique se o script está sendo executado no servidor correto
- Verifique se o usuário do PHP tem permissão para executar `docker`

### Container não encontrado
- O script tenta encontrar automaticamente containers com "hub" ou "pixel" no nome
- Se não encontrar, ajuste `$containerName` no código

### Nenhum log encontrado
- Verifique o horário do teste (pode estar em UTC)
- Verifique se o container está rodando: `docker ps`
- Verifique se os logs estão sendo gerados

