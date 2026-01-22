# 🔐 Testar Senha Corretamente

## ⚠️ Problema

Você está usando "SUA_SENHA" literalmente no curl. Precisa substituir pela senha real.

---

## 🛠️ Solução: Testar Corretamente

Execute:

```bash
# 1. Testar com senha real (substitua SENHA_REAL pela senha que você digitou)
# Se a senha tiver caracteres especiais, pode precisar de escape
curl -k -u "Los@ngo#081081:SENHA_REAL" -I https://wpp.pixel12digital.com.br:8443

# 2. Alternativa: Usar variável de ambiente (mais seguro)
export SENHA="sua_senha_aqui"
curl -k -u "Los@ngo#081081:$SENHA" -I https://wpp.pixel12digital.com.br:8443

# 3. Testar do navegador (mais fácil)
# Acesse: https://wpp.pixel12digital.com.br:8443
# Use: Los@ngo#081081 e sua senha
```

---

## 🔧 Se Ainda Der 401

Verifique:

1. **Caracteres especiais no usuário**: O `@` e `#` podem precisar de escape
2. **Senha com caracteres especiais**: Pode precisar de aspas ou escape
3. **Teste do navegador**: Mais fácil para verificar se funciona

---

## ✅ Teste Alternativo: Criar Usuário Simples

Se continuar com problema, crie um usuário sem caracteres especiais:

```bash
# 1. Adicionar novo usuário simples
htpasswd /etc/nginx/.htpasswd_wpp.pixel12digital.com.br admin

# 2. Recarregar
nginx -s reload

# 3. Testar
curl -k -u "admin:senha" -I https://wpp.pixel12digital.com.br:8443
```

---

## 🎯 Melhor Opção: Testar do Navegador

O mais fácil é testar do navegador:
1. Acesse: `https://wpp.pixel12digital.com.br:8443`
2. Digite usuário: `Los@ngo#081081`
3. Digite a senha que você criou
4. Veja se funciona

