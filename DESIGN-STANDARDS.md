# PADRÕES DE DESIGN - GUINCHO APP

## ESTILO OFICIAL ADOTADO

**Baseado nas escolhas do usuário e refinado para minimalismo elegante**

---

## 🎨 PALETA DE CORES OFICIAL

### Cores Primárias
- **Azul Principal**: #007bff (gradientes e elementos principais)
- **Cinza Elegante**: #495057 (texto e elementos secundários)
- **Branco**: #ffffff (botões principais e texto destacado)

### Transparências
- **Elementos transparentes**: rgba(255,255,255,0.1) a rgba(255,255,255,0.4)
- **Texto secundário**: rgba(255,255,255,0.7)
- **Bordas suaves**: rgba(255,255,255,0.2)

---

## 📐 ELEMENTOS DE INTERFACE

### Tipografia
- **Fonte principal**: 'Inter', 'Segoe UI', sans-serif
- **Títulos**: font-weight: 300, letter-spacing: -0.5px
- **Texto**: font-weight: 400-500
- **Sem negrito excessivo**

### Campos de Entrada
- **Estilo**: Underline minimalista (sem bordas/caixas)
- **Border**: 1px solid rgba(255,255,255,0.3)
- **Focus**: border-color: white
- **Background**: transparent
- **Padding**: 20px 0

### Botões
- **Principal**: Background branco, texto cinza escuro
- **Secundário**: Background transparente, border suave
- **Hover**: Slight opacity/background change
- **Sem sombras ou efeitos excessivos**

### Ícones
- **Fonte**: Font Awesome 6.0+
- **Tamanho padrão**: 1rem - 1.2rem
- **Cor**: rgba(255,255,255,0.6) a white
- **Nunca usar emoticons**

---

## 🚫 ELEMENTOS PROIBIDOS

### Não Usar Jamais:
- Emoticons (🚗, 👁️, 🔒, etc.)
- Cards flutuantes com bordas
- Box-shadows excessivos
- Gradientes muito coloridos
- Bordas arredondadas exageradas
- Elementos biométricos em grade

### Evitar:
- Muitos elementos visuais na mesma tela
- Cores saturadas
- Animações excessivas
- Múltiplas opções de login social

---

## 🎯 PRINCÍPIOS DE DESIGN

### 1. Minimalismo
- Vista única sem divisões
- Elementos integrados no fundo
- Máximo 3-4 elementos visuais por tela

### 2. Elegância
- Transparências sutis
- Transições suaves (0.3s ease)
- Espaçamento generoso

### 3. Funcionalidade
- Foco na usabilidade
- Menos opções, mais eficiência
- Mobile-first sempre

### 4. Consistência
- Mesma paleta em todo app
- Padrões repetidos
- Comportamentos previsíveis

---

## 📱 LAYOUT PADRÃO

### Estrutura Geral
```
body: gradient background (azul para cinza)
├── header: título + subtítulo (centralizados)
├── main content: elementos principais
├── actions: botões de ação
└── footer: links secundários
```

### Responsividade
- **Mobile-first** sempre
- Padding lateral: 24px
- Max-width: 400px em desktop
- Grid/flexbox para layouts

### Animações
- **Fade-in**: 0.6s ease-out
- **Stagger**: delay 0.1s entre elementos
- **Hover**: 0.3s ease
- **Nunca** animações longas ou chamativas

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

Para cada nova tela/componente, verificar:

- [ ] Usa apenas ícones Font Awesome (sem emoticons)
- [ ] Background gradiente azul-cinza
- [ ] Elementos transparentes integrados
- [ ] Campos com underline (sem bordas)
- [ ] Botão principal branco
- [ ] Máximo 1 login social (Google apenas)
- [ ] Sem elementos biométricos em grade
- [ ] Animações suaves e discretas
- [ ] Mobile-first responsive
- [ ] Paleta de cores consistente

---

## 🔧 CÓDIGO BASE REUTILIZÁVEL

### CSS Variables
```css
:root {
  --primary-blue: #007bff;
  --secondary-gray: #495057;
  --white: #ffffff;
  --transparent-light: rgba(255,255,255,0.1);
  --transparent-medium: rgba(255,255,255,0.3);
  --transparent-text: rgba(255,255,255,0.7);
}
```

### Background Padrão
```css
body {
  background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-gray) 100%);
  font-family: 'Inter', 'Segoe UI', sans-serif;
  min-height: 100vh;
  display: grid;
  place-items: center;
}
```

### Input Padrão
```css
.form-input {
  width: 100%;
  padding: 20px 0;
  border: none;
  border-bottom: 1px solid var(--transparent-medium);
  background: transparent;
  color: white;
  font-size: 1.1rem;
}

.form-input:focus {
  outline: none;
  border-bottom-color: white;
}
```

### Botão Padrão
```css
.btn-primary {
  width: 100%;
  padding: 20px;
  background: white;
  color: var(--secondary-gray);
  border: none;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-primary:hover {
  background: rgba(255,255,255,0.9);
  transform: translateY(-1px);
}
```

---

**Este documento define o padrão oficial para todo o desenvolvimento do Guincho App. Qualquer desvio deve ser justificado e aprovado.**