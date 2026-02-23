# Prospecção Ativa - Grandes Volumes

## Como o Sistema Lida com 3000+ Resultados

### Arquitetura Implementada

O sistema foi projetado para lidar com grandes volumes de forma eficiente e segura:

#### 1. Paginação Automática
- A API Minha Receita retorna até **100 registros por requisição**
- O sistema faz múltiplas requisições usando **cursor-based pagination**
- Cada página é processada incrementalmente

#### 2. Filtros Automáticos
Empresas são filtradas **antes** de serem salvas no banco:
- ❌ **INAPTA** - removida automaticamente
- ❌ **BAIXADA** - removida automaticamente  
- ❌ **SUSPENSA** - removida automaticamente
- ❌ **NULA** - removida automaticamente
- ✅ **ATIVA** - incluída nos resultados

**Impacto**: Se a API retornar 3000 registros mas 1500 forem inaptos/baixados, apenas 1500 válidos serão salvos.

#### 3. Rate Limiting
- **500ms de sleep** entre cada requisição
- Previne sobrecarga da API pública
- Evita bloqueios por rate limit

#### 4. Limites de Segurança

| Componente | Limite Atual | Proteção |
|------------|--------------|----------|
| Frontend (padrão) | 100 resultados | Busca rápida |
| Frontend (opções) | 100, 500, 1000, 5000, 10000 | Seleção via modal |
| Backend (máximo) | 10000 resultados | Hard limit no controller |
| API (requisições) | Ilimitado para volumes >1000 | Configurável dinamicamente |
| Timeout PHP | 300-900 segundos | 5-15 min baseado no volume |

#### 5. Processamento Incremental

```
Requisição 1: 100 registros → 60 válidos (40 filtrados)
Requisição 2: 100 registros → 55 válidos (45 filtrados)
Requisição 3: 100 registros → 70 válidos (30 filtrados)
...
Requisição N: até atingir limite ou fim dos dados
```

#### 6. Logs de Progresso

O sistema registra o progresso em `logs/pixelhub.log`:

```
[ProspectingService] CNAE 4755501: 100 buscados, 35 filtrados, 65 válidos
[ProspectingService] CNAE 4755501: 200 buscados, 78 filtrados, 122 válidos
[ProspectingService] CNAE 4755501: 300 buscados, 115 filtrados, 185 válidos
```

### Cenários de Uso

#### Cenário 1: Busca Pequena (100 resultados)
- **Tempo**: ~5-10 segundos
- **Requisições**: 1-2
- **Experiência**: Instantânea
- **Uso**: Teste rápido, validação de CNAE

#### Cenário 2: Busca Média (500 resultados)
- **Tempo**: ~30-60 segundos
- **Requisições**: 5-10
- **Experiência**: Aguardar com feedback
- **Uso**: Prospecção focada em cidade específica

#### Cenário 3: Busca Grande (1.000 resultados)
- **Tempo**: 1-3 minutos
- **Requisições**: 10-20
- **Experiência**: Aguardar, logs em tempo real
- **Uso**: Prospecção completa de município

#### Cenário 4: Busca Muito Grande (5.000 resultados)
- **Tempo**: ~8-10 minutos
- **Requisições**: 50-100
- **Experiência**: Aguardar, pode demorar
- **Uso**: Prospecção estadual ou CNAE amplo
- **⚠️ Atenção**: Use apenas se realmente necessário

#### Cenário 5: Volume Extremo (10.000 resultados)
- **Tempo**: ~12-15 minutos
- **Requisições**: 100-200
- **Experiência**: Processo longo, acompanhe logs
- **Uso**: Base completa de CNAE em estado inteiro
- **🔥 Crítico**: 
  - Sistema fará até 200 requisições à API
  - Consumirá ~40-50MB de memória
  - Pode gerar timeout em conexões lentas
  - Recomendado apenas para bases iniciais
  - Considere dividir em múltiplas receitas por cidade

### Otimizações Aplicadas

1. ✅ **Deduplicação por CNPJ** - evita duplicatas
2. ✅ **Filtro de situação cadastral** - reduz volume de dados inválidos
3. ✅ **Sleep entre requisições** - evita rate limit
4. ✅ **Limite de requisições** - previne loops infinitos
5. ✅ **Timeout estendido** - permite buscas longas
6. ✅ **Callback de progresso** - logs detalhados

### Recomendações

#### Para Buscas com Muitos Resultados Esperados:

1. **Refine o filtro**:
   - Adicione cidade específica (reduz de estado inteiro para município)
   - Use CNAEs mais específicos (subclasse em vez de classe)
   - Exemplo: Em vez de "SC inteiro", faça "Florianópolis", "Blumenau", etc.

2. **Busque em lotes**:
   - Crie múltiplas receitas por cidade
   - Exemplo: "Blumenau Centro", "Blumenau Bairro X", etc.
   - Vantagem: Processamento paralelo, melhor organização

3. **Configure limite adequado**:
   - **100**: teste rápido, validação
   - **500**: prospecção focada, cidade pequena
   - **1.000**: prospecção completa, cidade média
   - **5.000**: base regional, múltiplas cidades
   - **10.000**: base estadual completa (use com cautela)

4. **Para 10k+ empresas**:
   - ✅ **Faça**: Divida por cidade ou região
   - ✅ **Faça**: Execute em horário de baixo uso
   - ✅ **Faça**: Monitore logs durante execução
   - ❌ **Evite**: Executar múltiplas buscas 10k simultâneas
   - ❌ **Evite**: Buscar 10k sem filtro de cidade

### Limitações Conhecidas

1. **API Pública**: Sem garantia de SLA ou disponibilidade
2. **Rate Limiting**: API pode ter limites não documentados (sleep de 500ms mitiga)
3. **Timeout de Navegador**: Buscas > 10 minutos podem causar timeout no frontend
4. **Memória PHP**: Buscas 10k+ consomem ~50MB (dentro do limite padrão de 128MB)
5. **Conexão**: Buscas longas podem falhar em conexões instáveis
6. **Filtros**: ~40% das empresas podem ser inaptas/baixadas (filtradas automaticamente)

### Melhorias Futuras (Backlog)

- [ ] Sistema de fila assíncrona (processar em background)
- [ ] Websocket para feedback de progresso em tempo real
- [ ] Exportação incremental para CSV durante a busca
- [ ] Cache de resultados por CNAE+região
- [ ] Retry automático em caso de falha de rede
- [ ] Estimativa de tempo baseada em buscas anteriores

### Monitoramento

Verifique os logs para identificar problemas:

```bash
# Logs de progresso
tail -f logs/pixelhub.log | grep ProspectingService

# Erros da API
tail -f logs/pixelhub.log | grep MinhaReceitaClient
```

### Troubleshooting

**Problema**: Busca retorna menos resultados que esperado
- **Causa**: Muitas empresas inaptas/baixadas filtradas
- **Solução**: Normal, apenas empresas ativas são incluídas

**Problema**: Timeout após 30 segundos
- **Causa**: Limite de timeout do servidor não configurado
- **Solução**: Já implementado `set_time_limit(300)` no controller

**Problema**: Rate limit atingido
- **Causa**: Muitas requisições em curto período
- **Solução**: Sleep de 500ms já implementado entre requisições

**Problema**: Busca trava em 5000 resultados
- **Causa**: Limite de segurança de 50 requisições
- **Solução**: Refine a busca ou crie múltiplas receitas
