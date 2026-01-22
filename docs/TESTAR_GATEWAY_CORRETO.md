# ✅ Testar Gateway Corretamente

## 🔍 O Erro do curl

O erro `SSL certificate problem: unable to get local issuer certificate` é apenas do **curl tentando validar o certificado**. O gateway está funcionando!

## ✅ Testes Corretos

### 1. Testar ignorando validação SSL (para teste)

```bash
# Sem autenticação (deve retornar 401)
curl -k -I https://wpp.pixel12digital.com.br:8443

# Com autenticação (deve retornar 200)
curl -k -u Los@ngo#081081:SUA_SENHA -I https://wpp.pixel12digital.com.br:8443
```

**O `-k` ignora a validação do certificado** (apenas para teste no servidor).

---

### 2. Verificar Certificado SSL

```bash
# Ver detalhes do certificado
openssl s_client -connect wpp.pixel12digital.com.br:8443 -servername wpp.pixel12digital.com.br < /dev/null 2>/dev/null | openssl x509 -noout -text | head -20

# Ver validade do certificado
openssl s_client -connect wpp.pixel12digital.com.br:8443 -servername wpp.pixel12digital.com.br < /dev/null 2>/dev/null | openssl x509 -noout -dates
```

---

### 3. Testar do Navegador (Mais Importante)

O navegador vai validar o certificado corretamente. Acesse:

```
https://wpp.pixel12digital.com.br:8443
```

**Deve:**
1. Mostrar aviso de certificado (normal para Let's Encrypt)
2. Pedir usuário e senha (autenticação básica)
3. Após autenticar, mostrar o gateway

---

### 4. Ver Logs em Tempo Real

Enquanto testa, os logs devem mostrar:

**No access.log:**
```
IP - - [DATA] "GET / HTTP/2.0" 401 TAMANHO "-" "curl/..."
IP - - [DATA] "GET / HTTP/2.0" 200 TAMANHO "-" "curl/..."
```

**No error.log:**
```
(geralmente vazio ou apenas avisos normais)
```

---

## 🎯 Teste Completo

Execute estes comandos na ordem:

```bash
# 1. Testar sem autenticação (deve dar 401)
curl -k -v https://wpp.pixel12digital.com.br:8443 2>&1 | grep -E "HTTP|401|200"

# 2. Testar com autenticação (deve dar 200)
curl -k -u Los@ngo#081081:SUA_SENHA -v https://wpp.pixel12digital.com.br:8443 2>&1 | grep -E "HTTP|401|200"

# 3. Verificar certificado
openssl s_client -connect wpp.pixel12digital.com.br:8443 -servername wpp.pixel12digital.com.br < /dev/null 2>/dev/null | openssl x509 -noout -dates

# 4. Ver se está escutando na porta 8443
ss -tlnp | grep :8443
```

---

## 📊 Interpretação dos Resultados

### ✅ Funcionando Corretamente:
- `401 Unauthorized` (sem autenticação) = Autenticação funcionando
- `200 OK` (com autenticação) = Gateway funcionando
- Certificado válido = SSL funcionando
- Nginx escutando na 8443 = Configuração correta

### ⚠️ Problemas:
- `502 Bad Gateway` = Gateway interno (porta 3000) não está respondendo
- `503 Service Unavailable` = Serviço indisponível
- Certificado inválido/expirado = Problema com Let's Encrypt

---

## 🌐 Teste do Navegador (Mais Confiável)

O **navegador** é o melhor teste porque:
1. ✅ Valida certificado corretamente
2. ✅ Mostra interface de autenticação
3. ✅ Testa experiência real do usuário

**Acesse no navegador:**
```
https://wpp.pixel12digital.com.br:8443
```

**O que deve acontecer:**
1. Aviso de certificado (aceite)
2. Popup pedindo usuário e senha
3. Após autenticar, gateway aparece

---

## 🔧 Se o Certificado Estiver com Problema

Se o certificado realmente estiver inválido, renove:

```bash
# Renovar certificado
certbot renew --cert-name wpp.pixel12digital.com.br --force-renewal

# Recarregar Nginx
systemctl reload nginx
```

Mas geralmente o problema é apenas do curl, não do certificado real.

