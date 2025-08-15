# 🚗 PROJETO GUINCHO - OBSERVAÇÕES E ESPECIFICAÇÕES

## 📱 VISÃO GERAL DO PROJETO
**Tipo**: Aplicativo Mobile (PWA)  
**Tecnologias**: HTML, CSS, JavaScript puro  
**Foco**: Aplicativo de socorro automotivo mobile-first  

---

## 🎯 OBJETIVO PRINCIPAL
Desenvolver um aplicativo para usuários que necessitam de ajuda com veículos (carros, motos, outros), oferecendo diversos tipos de socorro automotivo através de uma plataforma similar ao inDriver.

---

## 🛠️ TIPOS DE SERVIÇOS DISPONÍVEIS

### Serviços Principais:
1. **🚛 Guincho** (serviço principal)
2. **🔋 Socorro com bateria**
3. **🛞 Socorro com pneu/estepe**
4. **🔑 Chaveiro**
5. **🔧 Mecânico**
6. **⚡ Eletricista automotivo**

---

## 📋 CARACTERÍSTICAS TÉCNICAS
- **Mobile-first**: Interface otimizada para dispositivos móveis
- **Tecnologia**: HTML5, CSS3, JavaScript vanilla (sem frameworks)
- **Inspiração**: Interface similar ao inDriver (definição de preço pelo usuário)
- **Responsividade**: Totalmente responsivo para diferentes tamanhos de tela

---

## 🎨 CONCEITO DE INTERFACE
- Tela de mapa para localização
- Seleção de tipo de serviço
- Definição de preço pelo usuário
- Sistema de matching com prestadores de serviço
- Interface clean e intuitiva

---

## 📊 FLUXOGRAMA EXISTENTE
Já existe um fluxograma em `fluxogramas/GUINCHO.drawio` com:
- Tela splash
- Sistema de login/registro
- Diferentes tipos de acesso (usuário, parceiro, administrador)
- Validações de autenticidade

---

## 💡 SUGESTÕES DE MELHORIAS

### Funcionalidades Adicionais:
1. **Geolocalização em tempo real**
2. **Chat integrado** entre usuário e prestador
3. **Sistema de avaliação** (5 estrelas)
4. **Histórico de serviços**
5. **Pagamento integrado** (PIX, cartão)
6. **Notificações push**
7. **Modo offline** para emergências

### Melhorias de UX/UI:
1. **Dark mode** para uso noturno
2. **Botão de emergência** destacado
3. **Estimativa de tempo de chegada**
4. **Fotos do problema** (câmera integrada)
5. **Localização por voz** (accessibility)

### Recursos de Segurança:
1. **Verificação de prestadores** (documentos, seguro)
2. **Compartilhamento de localização** com contatos
3. **Gravação de chamadas** (opcional)
4. **Botão de pânico/SOS**

### Recursos de Negócio:
1. **Sistema de fidelidade** (pontos/desconto)
2. **Parcerias com seguradoras**
3. **Planos mensais/anuais**
4. **Serviços programados** (manutenção preventiva)

---

## 🗂️ ESTRUTURA SUGERIDA DO PROJETO

```
guincho/
├── index.html              # Página principal
├── manifest.json           # PWA manifest
├── service-worker.js       # Service worker para offline
├── css/
│   ├── main.css            # Estilos principais
│   ├── components.css      # Componentes reutilizáveis
│   └── responsive.css      # Media queries
├── js/
│   ├── app.js              # Lógica principal
│   ├── map.js              # Funcionalidades do mapa
│   ├── geolocation.js      # Geolocalização
│   └── services.js         # Gerenciamento de serviços
├── assets/
│   ├── icons/              # Ícones do app
│   ├── images/             # Imagens
│   └── sounds/             # Sons de notificação
├── pages/
│   ├── login.html          # Tela de login
│   ├── register.html       # Tela de registro
│   ├── profile.html        # Perfil do usuário
│   └── history.html        # Histórico de serviços
└── docs/
    ├── fluxogramas/        # Diagramas e fluxos
    └── PROJETO-OBSERVACOES.md # Este arquivo
```

---

## 📝 LOG DE OBSERVAÇÕES
*[As próximas observações do usuário serão adicionadas aqui cronologicamente]*

### Data: 2025-08-15
- **Observação inicial**: Projeto definido como aplicativo mobile para socorro automotivo
- **Decisão técnica**: HTML/CSS/JS puro, sem frameworks
- **Escopo**: 6 tipos de serviços (guincho como principal)
- **Referência**: Interface inspirada no inDriver
- **Escolhas de Design Definidas**: PAL-1, PAL-6, BTN-P1, BTN-S1, BTN-S2, BTN-S3, CARD-C4, BTN-SHAPE2, INPUT-I3

- **Perfil de Design Identificado**:
  - Estilo profissional moderno (azul + cinza)
  - Minimalismo funcional (outline buttons)
  - Sutileza premium (dark themes com detalhes refinados)
  - Modernidade controlada (bordas arredondadas sem exagero)

- **Tela de Login Final** (`login.html`):
  - Design minimalista e elegante sem bordas/cards flutuantes
  - Vista única com fundo gradiente (PAL-1 + PAL-6)
  - Ícones Font Awesome substituindo emoticons
  - Campos de entrada com underline minimalista
  - REMOVIDO: Autenticação biométrica (quadrados)
  - REMOVIDO: Login pela Apple
  - REMOVIDO: Opções de acessibilidade no topo
  - Login social: apenas Google
  - Botão principal branco contrastante
  - Micro-interações suaves e fade-in animations
  - Design responsivo mobile-first
  - Interface totalmente transparente e limpa

- **PADRÃO DE DESIGN OFICIAL DEFINIDO** (`DESIGN-STANDARDS.md`):
  - Estilo minimalista elegante adotado para todo o app
  - Paleta: Azul (#007bff) + Cinza (#495057) + Branco
  - Elementos transparentes integrados no fundo gradiente
  - Campos underline sem bordas
  - Proibido: emoticons, cards flutuantes, elementos biométricos em grade
  - Ícones: Font Awesome apenas
  - Botões: principal branco, secundário transparente
  - Mobile-first com animações suaves
  - Código base reutilizável definido

- **Página de Seleção de Serviços Reformulada** (`services.html`):
  - REFORMULADO: Layout estilo chocolate 3x2 (3 colunas, 2 linhas)
  - REDUZIDO: Textos do header menores e mais compactos
  - REMOVIDO: Botão "Continuar" - redirecionamento direto
  - MUDANÇA: Botões agora são principais (brancos) com redirecionamento imediato
  - Grid 3x2 com os 6 serviços: Guincho, Bateria, Pneu, Chaveiro, Mecânico, Eletricista
  - Botão de emergência destacado em vermelho (mantido)
  - Detecção automática de localização reduzida
  - Redirecionamento direto ao clicar no serviço
  - Design chocolate: botões brancos sobre fundo gradiente
  - Interface mobile-first: 2x3 no mobile
  - Animações fade-in suaves mantidas

- **Página de Localização Criada** (`service-details.html`):
  - UNIVERSAL: Todos os serviços direcionam para a mesma tela
  - Google Maps integrado com API key configurável
  - Detecção automática de localização via GPS
  - Fallback para simulação caso API indisponível
  - Botão voltar para services.html
  - Título dinâmico baseado no serviço selecionado
  - Opção de inserir endereço manualmente
  - Status visual da detecção (sucesso/erro)
  - Botão "Confirmar Localização" para próximo passo
  - Design minimalista seguindo padrão oficial
  - Mapa responsivo com loading state
  - Marcador personalizado na localização do usuário

---

## ⚠️ NOTAS IMPORTANTES
- Manter foco em performance para dispositivos móveis
- Priorizar usabilidade em situações de emergência
- Considerar conectividade limitada (modo offline)
- Interface deve ser intuitiva mesmo sob estresse
- Acessibilidade para diferentes tipos de usuários

---

*Documento atualizado automaticamente conforme evolução do projeto*