# Solução: Erro de Comunicação com Asaas no Ambiente Local

## 🔍 Problema Identificado

O erro **401 - Chave de API inválida ou expirada** no ambiente local ocorre porque:

1. **A chave de API foi criptografada em produção** usando a `INFRA_SECRET_KEY` de produção
2. **A `INFRA_SECRET_KEY` local é diferente** da de produção
3. **A descriptografia falha localmente**, então o sistema tenta usar a chave criptografada diretamente
4. **A API do Asaas rejeita a chave criptografada** (ela espera a chave em texto plano), resultando em erro 401

## ✅ Solução

### Opção 1: Reconfigurar a Chave Localmente (Recomendado)

1. **Acesse o painel do Asaas** e copie sua chave de API
2. **Acesse as configurações do Asaas** no painel local:
   ```
   http://localhost/painel.pixel12digital/public/settings/asaas
   ```
3. **Cole a chave de API** no campo "Chave de API"
4. **Clique em "Salvar Configurações"**
5. A chave será **criptografada automaticamente** com a `INFRA_SECRET_KEY` local
6. **Teste a conexão** novamente

### Opção 2: Usar a Mesma INFRA_SECRET_KEY (Não Recomendado)

⚠️ **ATENÇÃO**: Não é recomendado usar a mesma `INFRA_SECRET_KEY` em produção e desenvolvimento por questões de segurança.

Se ainda assim quiser fazer isso:
1. Copie o valor de `INFRA_SECRET_KEY` do `.env` de produção
2. Cole no `.env` local
3. Recarregue as variáveis de ambiente

## 🔧 Melhorias Implementadas

O código foi atualizado para:

1. **Detectar melhor** quando a descriptografia falha devido a `INFRA_SECRET_KEY` diferente
2. **Exibir mensagens de erro mais claras** explicando o problema
3. **Fornecer instruções passo a passo** para resolver o problema

## 📝 Como Funciona a Criptografia

- A chave de API do Asaas é **criptografada** antes de ser salva no `.env`
- A criptografia usa `INFRA_SECRET_KEY` como chave de criptografia
- Cada ambiente deve ter sua própria `INFRA_SECRET_KEY` por segurança
- Por isso, chaves criptografadas em um ambiente não podem ser descriptografadas em outro

## 🎯 Verificação

Após reconfigurar a chave localmente:

1. O teste de conexão deve retornar **HTTP 200**
2. Os logs devem mostrar: **"✅ Chave Asaas detectada em formato texto plano - pronta para uso!"**
3. A conexão com a API do Asaas deve funcionar normalmente

## 📌 Notas Importantes

- **Nunca compartilhe** a chave de API do Asaas em texto plano
- A chave é **criptografada automaticamente** ao salvar
- Cada ambiente (local, staging, produção) deve ter sua própria configuração
- A `INFRA_SECRET_KEY` deve ser **única e segura** em cada ambiente


