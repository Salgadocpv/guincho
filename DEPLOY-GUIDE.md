# 🚀 Guia de Deploy - Projeto Guincho

## Estratégia de Deploy com Branches

### 🏗️ Arquitetura de Branches

```
main (deploy) ← Hostinger faz deploy automático deste branch
├── feature/navigation-module ← Sistema de navegação/mapas  
├── feature/auth-module ← Sistema de autenticação/cadastro
├── feature/api-module ← Backend e APIs
└── feature/ui-components ← Interface e componentes
```

### 📋 Processo de Deploy

#### **1. Desenvolvimento em Branches de Feature**
```bash
# Trabalhar em funcionalidades específicas
git checkout feature/auth-module
# Fazer mudanças...
git add .
git commit -m "feat: nova funcionalidade"
git push origin feature/auth-module
```

#### **2. Deploy para Produção (Hostinger)**
```bash
# 1. Ir para main
git checkout main
git pull origin main

# 2. Fazer merge das funcionalidades prontas
git merge feature/auth-module

# 3. Push para main (dispara deploy automático)
git push origin main
```

### ⚙️ Configuração da Hostinger

A Hostinger está configurada para fazer deploy automático do branch `main`:
- **Branch de Deploy:** `main`
- **Diretório de Deploy:** `public_html/`
- **Deploy Automático:** ✅ Ativado
- **Webhook:** GitHub → Hostinger

### 🔄 Workflow Completo

#### **Para Funcionalidades Novas:**
1. Criar/usar branch de feature apropriado
2. Desenvolver e testar localmente
3. Push para branch de feature
4. Quando pronto, fazer merge para main
5. Push do main (deploy automático)

#### **Para Hotfixes:**
```bash
git checkout main
git pull origin main
# Fazer correção diretamente no main
git add .
git commit -m "fix: correção crítica"
git push origin main  # Deploy imediato
```

### 📁 Estrutura de Deploy

Após o deploy, a estrutura na Hostinger fica:
```
public_html/
├── index.html ← Tela de login com modal
├── register.html ← Cadastro de clientes
├── register-driver.html ← Cadastro de guincheiros
├── navigation.html ← Sistema de navegação
├── services.html ← Seleção de serviços
├── api/ ← Backend PHP
├── modules/ ← Organização modular (futuro)
└── manifest.json ← PWA config
```

### 🎯 Vantagens desta Estratégia

1. **Deploy Seguro:** Main sempre estável
2. **Desenvolvimento Paralelo:** Múltiplas features simultâneas  
3. **Rollback Fácil:** Reverter commits específicos
4. **Controle de Qualidade:** Review antes do merge
5. **Deploy Automático:** Push no main = deploy imediato

### 🚨 Regras Importantes

#### **✅ SEMPRE FAZER:**
- Testar localmente antes do push
- Fazer merge para main só quando funcionalidade estiver completa
- Pull do main antes de fazer merge
- Usar commits descritivos (`feat:`, `fix:`, `refactor:`)

#### **❌ NUNCA FAZER:**
- Push direto no main sem testar
- Merge de funcionalidades incompletas
- Deploy em horário de pico sem aviso
- Commit de credenciais ou senhas

### 🛠️ Comandos Úteis

#### **Verificar Status:**
```bash
git status                    # Status atual
git branch -a                 # Todas as branches
git log --oneline -5          # Últimos 5 commits
```

#### **Deploy de Emergência:**
```bash
git checkout main
git revert HEAD              # Reverter último commit
git push origin main         # Deploy da reversão
```

#### **Sincronizar Branch de Feature:**
```bash
git checkout feature/sua-branch
git merge main              # Atualizar com main
git push origin feature/sua-branch
```

### 📊 Monitoramento de Deploy

#### **Verificar Deploy:**
1. ✅ Push para main realizado
2. ✅ Hostinger recebeu webhook  
3. ✅ Arquivos atualizados no servidor
4. ✅ Site funcionando corretamente

#### **Em Caso de Problemas:**
```bash
# Verificar logs do último commit
git log -1 --stat

# Reverter se necessário
git revert HEAD
git push origin main
```

### 🔄 Fluxo de Deploy Típico

```mermaid
graph LR
    A[Desenvolver] --> B[feature/branch]
    B --> C[Test Local]
    C --> D[Push Feature]
    D --> E[Merge para Main]
    E --> F[Push Main]
    F --> G[Deploy Hostinger]
    G --> H[Verificar Site]
```

### 📞 Suporte

- **Git Issues:** [GitHub Repository Issues](https://github.com/Salgadocpv/guincho/issues)
- **Deploy Issues:** Verificar logs da Hostinger
- **Rollback:** Usar `git revert` + push

---

## ✅ Resultado Final

✅ **Deploy Configurado:** Main branch → Hostinger automático  
✅ **Funcionalidades Integradas:** Modal de cadastro + tela de guincheiro  
✅ **Workflow Documentado:** Processo claro para toda equipe  
✅ **Sistema Modular:** Desenvolvimento organizado por features  

**Próximo deploy:** Simplesmente `git push origin main` 🚀