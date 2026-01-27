# Instruções para Corrigir Timeout de Áudio

## Passo 1: Ver o arquivo completo de configuração

Execute na VPS:
```bash
cat /etc/nginx/sites-available/whatsapp-multichannel
```

## Passo 2: Editar o arquivo

```bash
sudo nano /etc/nginx/sites-available/whatsapp-multichannel
```

## Passo 3: Localizar e alterar os timeouts

Procure por estas linhas (podem estar em diferentes lugares do arquivo):
```nginx
proxy_connect_timeout 60s;
proxy_send_timeout 60s;
proxy_read_timeout 60s;
```

**Altere para:**
```nginx
proxy_connect_timeout 120s;
proxy_send_timeout 120s;
proxy_read_timeout 120s;
```

**OU se não existirem, adicione dentro do bloco `location / {` ou `server {`:**
```nginx
proxy_connect_timeout 120s;
proxy_send_timeout 120s;
proxy_read_timeout 120s;
```

## Passo 4: Salvar e testar

No nano:
- `Ctrl + O` para salvar
- `Enter` para confirmar
- `Ctrl + X` para sair

Depois:
```bash
# Testar configuração
sudo nginx -t

# Se OK, recarregar
sudo systemctl reload nginx
```

## Passo 5: Testar envio de áudio

Volte ao projeto e tente enviar um áudio novamente. Deve funcionar agora!
