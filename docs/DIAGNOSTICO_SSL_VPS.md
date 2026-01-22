# Diagnóstico SSL - ERR_SSL_PROTOCOL_ERROR

## Problema
Após adicionar login na VPS, o gateway do WhatsApp `wpp.pixel12digital.com.br` está retornando `ERR_SSL_PROTOCOL_ERROR`.

## Contexto
- AzuraCast rodando na VPS (não pode ser alterado)
- Certificado SSL obtido através do reverse proxy do AzuraCast
- Sem Cloudflare
- DNS configurado manualmente

---

## Comandos para Executar na VPS

### 1. Verificar status do Nginx e serviços relacionados

```bash
systemctl status nginx
```

**O que esperamos:** Nginx rodando sem erros

---

### 2. Verificar configurações do Nginx para wpp.pixel12digital.com.br

```bash
grep -r "wpp.pixel12digital.com.br" /etc/nginx/
```

**O que esperamos:** Encontrar arquivo de configuração com o domínio

---

### 3. Listar todos os arquivos de configuração do Nginx

```bash
ls -la /etc/nginx/sites-enabled/
ls -la /etc/nginx/conf.d/
```

**O que esperamos:** Ver arquivos de configuração, especialmente relacionados ao AzuraCast e wpp

---

### 4. Verificar configuração completa do Nginx para wpp.pixel12digital.com.br

```bash
find /etc/nginx -type f -name "*.conf" -exec grep -l "wpp.pixel12digital" {} \;
```

Depois, para cada arquivo encontrado, execute:

```bash
cat /caminho/do/arquivo.conf
```

**O que esperamos:** Ver configuração do server block com SSL

---

### 5. Verificar certificados SSL (Let's Encrypt)

```bash
certbot certificates
```

**O que esperamos:** Ver certificados válidos, especialmente para `wpp.pixel12digital.com.br`

---

### 6. Verificar certificados SSL manualmente

```bash
ls -la /etc/letsencrypt/live/
ls -la /etc/letsencrypt/live/*/ 2>/dev/null | grep -E "(cert.pem|privkey.pem|chain.pem|fullchain.pem)"
```

**O que esperamos:** Ver diretórios com certificados válidos

---

### 7. Verificar validade do certificado para wpp.pixel12digital.com.br

```bash
openssl s_client -connect wpp.pixel12digital.com.br:443 -servername wpp.pixel12digital.com.br < /dev/null 2>/dev/null | openssl x509 -noout -dates
```

**O que esperamos:** Ver datas de validade (notBefore e notAfter)

---

### 8. Testar conexão SSL diretamente

```bash
curl -vI https://wpp.pixel12digital.com.br
```

**O que esperamos:** Ver detalhes da conexão SSL e possíveis erros

---

### 9. Verificar logs do Nginx (últimas 50 linhas)

```bash
tail -50 /var/log/nginx/error.log
```

**O que esperamos:** Ver erros relacionados a SSL, certificados ou conexões

---

### 10. Verificar logs de acesso do Nginx

```bash
tail -50 /var/log/nginx/access.log | grep wpp.pixel12digital
```

**O que esperamos:** Ver requisições ao domínio

---

### 11. Verificar se o Nginx está escutando na porta 443

```bash
netstat -tlnp | grep :443
# ou
ss -tlnp | grep :443
```

**O que esperamos:** Ver nginx escutando na porta 443

---

### 12. Verificar configuração de SSL no Nginx (padrões)

```bash
grep -r "ssl_certificate" /etc/nginx/ | grep -v "#"
grep -r "listen.*443" /etc/nginx/ | grep -v "#"
```

**O que esperamos:** Ver configurações SSL ativas

---

### 13. Verificar se há firewall bloqueando porta 443

```bash
ufw status
# ou
iptables -L -n | grep 443
```

**O que esperamos:** Porta 443 permitida no firewall

---

### 14. Verificar processos do Nginx

```bash
ps aux | grep nginx
```

**O que esperamos:** Processos master e worker do nginx rodando

---

### 15. Testar configuração do Nginx (syntax check)

```bash
nginx -t
```

**O que esperamos:** Sintaxe válida, sem erros

---

### 16. Verificar se AzuraCast está usando reverse proxy

```bash
docker ps | grep -i azura
```

**O que esperamos:** Containers do AzuraCast rodando

---

### 17. Verificar configuração do AzuraCast (se aplicável)

```bash
docker exec -it azuracast_web_1 cat /etc/nginx/azuracast.conf 2>/dev/null || echo "Container não encontrado ou sem arquivo"
```

---

### 18. Verificar se há múltiplas configurações conflitantes

```bash
grep -r "server_name.*wpp" /etc/nginx/ | grep -v "#"
```

**O que esperamos:** Apenas uma configuração para o domínio

---

### 19. Verificar se o certificado está sendo renovado automaticamente

```bash
systemctl status certbot.timer
# ou
systemctl list-timers | grep certbot
```

**O que esperamos:** Certbot configurado para renovação automática

---

### 20. Verificar última renovação do certificado

```bash
ls -la /etc/letsencrypt/renewal/ | grep wpp
cat /etc/letsencrypt/renewal/wpp.pixel12digital.com.br.conf 2>/dev/null || echo "Arquivo não encontrado"
```

---

### 21. Verificar se há problemas de permissão nos certificados

```bash
ls -la /etc/letsencrypt/live/*/ 2>/dev/null | head -20
```

**O que esperamos:** Certificados com permissões corretas (leitura para nginx)

---

### 22. Verificar configuração de autenticação básica (se foi adicionada)

```bash
grep -r "auth_basic" /etc/nginx/ | grep -v "#"
```

**O que esperamos:** Ver se há autenticação básica configurada que possa estar interferindo

---

### 23. Testar conexão HTTP (porta 80) para verificar se redireciona para HTTPS

```bash
curl -I http://wpp.pixel12digital.com.br
```

**O que esperamos:** Redirecionamento 301/302 para HTTPS ou resposta HTTP

---

### 24. Verificar se há proxy reverso configurado

```bash
grep -r "proxy_pass" /etc/nginx/ | grep -i wpp
```

**O que esperamos:** Ver configuração de proxy reverso para o gateway

---

### 25. Verificar variáveis de ambiente do AzuraCast (se aplicável)

```bash
docker exec azuracast_web_1 env | grep -i ssl 2>/dev/null || echo "Container não encontrado"
```

---

## Após Executar os Comandos

**Compartilhe os resultados** para identificarmos:

1. **Status do Nginx:** Está rodando? Há erros de sintaxe?
2. **Certificados SSL:** Existem? Estão válidos? Onde estão localizados?
3. **Configuração do Nginx:** Como está configurado o server block para wpp?
4. **Logs de Erro:** Quais erros aparecem nos logs?
5. **Portas:** Nginx está escutando na porta 443?
6. **Autenticação:** Foi adicionada autenticação básica que pode estar interferindo?

---

## Possíveis Causas e Soluções

### Causa 1: Certificado SSL não encontrado ou inválido
**Sintomas:** 
- `certbot certificates` não mostra certificado para wpp
- `openssl s_client` retorna erro de certificado

**Solução:** Renovar ou emitir novo certificado:
```bash
certbot certonly --nginx -d wpp.pixel12digital.com.br
```

### Causa 2: Configuração do Nginx apontando para certificado errado
**Sintomas:**
- Certificado existe mas Nginx não está usando
- `nginx -t` mostra erro de caminho de certificado

**Solução:** Corrigir caminhos em `/etc/nginx/sites-enabled/`

### Causa 3: Autenticação básica interferindo com SSL
**Sintomas:**
- `auth_basic` configurado no nginx
- Erro SSL antes mesmo de chegar na autenticação

**Solução:** Verificar ordem das diretivas no nginx

### Causa 4: Porta 443 não acessível
**Sintomas:**
- Firewall bloqueando
- Nginx não escutando na porta 443

**Solução:** Liberar porta 443 no firewall e verificar listen no nginx

### Causa 5: Múltiplas configurações conflitantes
**Sintomas:**
- Mais de um server block para o mesmo domínio
- Configurações conflitantes

**Solução:** Remover configurações duplicadas

---

## Próximos Passos

Após coletar os resultados, vamos:
1. Identificar a causa raiz do problema SSL
2. Corrigir a configuração sem afetar o AzuraCast
3. Garantir que o certificado seja renovado automaticamente
4. Testar o acesso ao gateway do WhatsApp

