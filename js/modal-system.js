/**
 * Sistema de Modais Padronizado
 * Substitui alert(), confirm() e prompt() por modais elegantes e modernos.
 * Este arquivo cria uma classe `ModalSystem` que gerencia a criação, exibição e interação com modais.
 * Ele também substitui as funções nativas do navegador para usar este novo sistema.
 */

// Define a classe que encapsula toda a lógica do sistema de modais.
class ModalSystem {
    // O construtor é chamado quando uma nova instância da classe é criada (ex: new ModalSystem()).
    constructor() {
        // `this.currentModal` armazena o estado do modal atualmente visível. Inicia como nulo.
        this.currentModal = null;
        // Chama o método para criar a estrutura HTML e CSS base para todos os modais.
        this.createModalContainer();
    }

    // Método responsável por injetar o HTML e CSS do modal no corpo (<body>) da página.
    createModalContainer() {
        // Verifica se o <body> do documento já está disponível.
        // Se o script for carregado no <head> sem 'defer', o body pode ainda não existir.
        if (!document.body) {
            // Se o body não estiver pronto, tenta novamente após 10 milissegundos.
            setTimeout(() => this.createModalContainer(), 10);
            return; // Encerra a execução atual do método.
        }

        // Procura por um container de modal já existente para evitar duplicatas.
        const existing = document.getElementById('modal-system-container');
        if (existing) {
            // Se um container antigo for encontrado, ele é removido.
            existing.remove();
        }

        // Cria um novo elemento <div> que servirá como o container principal para o sistema de modais.
        const container = document.createElement('div');
        // Define um ID único para o container para fácil referência.
        container.id = 'modal-system-container';
        // Injeta o HTML e o CSS necessários para a estrutura e estilo dos modais.
        // Usa template literals (crases ``) para criar um bloco de string multi-linha.
        container.innerHTML = `
            <style>
                /* Estilo para o fundo escurecido que cobre a página */
                .modal-overlay {
                    position: fixed; /* Posição fixa em relação à janela de visualização */
                    top: 0; left: 0; right: 0; bottom: 0; /* Cobre a tela inteira */
                    background: rgba(0, 0, 0, 0.5); /* Fundo preto semi-transparente */
                    backdrop-filter: blur(8px); /* Efeito de desfoque para o conteúdo atrás do modal */
                    z-index: 10000; /* Garante que o modal fique acima de todos os outros conteúdos */
                    display: none; /* Começa escondido */
                    align-items: center; /* Centraliza o conteúdo verticalmente */
                    justify-content: center; /* Centraliza o conteúdo horizontalmente */
                    padding: 20px; /* Espaçamento para não colar nas bordas da tela */
                    animation: modalFadeIn 0.3s ease-out; /* Animação de entrada */
                }

                /* Classe adicionada via JS para mostrar o modal */
                .modal-overlay.show {
                    display: flex; /* Muda para flex para ativar o alinhamento e tornar visível */
                }

                /* A caixa de diálogo principal do modal */
                .modal-box {
                    background: white; /* Fundo branco */
                    border-radius: 12px; /* Bordas arredondadas */
                    padding: 0; /* Remove padding interno, será adicionado nos filhos */
                    min-width: 300px; /* Largura mínima */
                    max-width: 90vw; /* Largura máxima (90% da largura da janela) */
                    max-height: 80vh; /* Altura máxima (80% da altura da janela) */
                    overflow: hidden; /* Esconde conteúdo que transborda (como cantos do header/footer) */
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); /* Sombra para dar profundidade */
                    transform: scale(0.9); /* Efeito de escala inicial para animação */
                    animation: modalSlideIn 0.3s ease-out forwards; /* Animação de entrada com slide e escala */
                }

                /* Cabeçalho do modal */
                .modal-header {
                    padding: 20px 24px 12px; /* Espaçamento interno */
                    border-bottom: 1px solid #e9ecef; /* Linha divisória na parte inferior */
                    display: flex; /* Layout flexível para alinhar ícone e título */
                    align-items: center; /* Alinha itens verticalmente */
                    gap: 12px; /* Espaço entre o ícone e o título */
                }

                /* Estilo do ícone no cabeçalho */
                .modal-icon {
                    font-size: 1.5rem; /* Tamanho do ícone */
                    width: 40px; height: 40px; /* Dimensões fixas */
                    border-radius: 50%; /* Forma circular */
                    display: flex; align-items: center; justify-content: center; /* Centraliza o ícone dentro do círculo */
                    flex-shrink: 0; /* Impede que o ícone encolha */
                }

                /* Estilos de cores para cada tipo de modal (info, success, etc.) */
                .modal-icon.info { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
                .modal-icon.success { background: rgba(25, 135, 84, 0.1); color: #198754; }
                .modal-icon.warning { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
                .modal-icon.danger { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
                .modal-icon.question { background: rgba(102, 16, 242, 0.1); color: #6610f2; }

                /* Título do modal */
                .modal-title {
                    font-size: 1.25rem; /* Tamanho da fonte */
                    font-weight: 600; /* Peso da fonte (semi-negrito) */
                    color: #212529; /* Cor do texto */
                    margin: 0; /* Remove margem padrão do h3 */
                    flex-grow: 1; /* Faz o título ocupar o espaço restante */
                }

                /* Corpo do modal (onde a mensagem principal aparece) */
                .modal-body {
                    padding: 20px 24px; /* Espaçamento interno */
                    color: #495057; /* Cor do texto */
                    line-height: 1.6; /* Altura da linha para melhor legibilidade */
                    font-size: 1rem; /* Tamanho da fonte padrão */
                }

                /* Campo de input para o modal de prompt */
                .modal-input {
                    width: 100%; /* Ocupa toda a largura */
                    padding: 12px 16px; /* Espaçamento interno */
                    border: 2px solid #e9ecef; /* Borda sutil */
                    border-radius: 8px; /* Bordas arredondadas */
                    font-size: 1rem; /* Tamanho da fonte */
                    margin-top: 12px; /* Espaço acima do input */
                    transition: border-color 0.3s ease; /* Transição suave da cor da borda */
                }

                /* Estilo do input quando está em foco (selecionado) */
                .modal-input:focus {
                    outline: none; /* Remove a borda de foco padrão do navegador */
                    border-color: #007bff; /* Muda a cor da borda para azul */
                    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1); /* Adiciona um brilho sutil */
                }

                /* Rodapé do modal, onde ficam os botões */
                .modal-footer {
                    padding: 16px 24px 24px; /* Espaçamento interno */
                    display: flex; /* Layout flexível */
                    gap: 12px; /* Espaço entre os botões */
                    justify-content: flex-end; /* Alinha os botões à direita */
                    border-top: 1px solid #e9ecef; /* Linha divisória acima */
                    background: #f8f9fa; /* Cor de fundo sutil */
                }

                /* Estilo base para todos os botões do modal */
                .modal-btn {
                    padding: 10px 20px; /* Espaçamento interno */
                    border-radius: 6px; /* Bordas arredondadas */
                    font-size: 0.875rem; /* Tamanho da fonte */
                    font-weight: 500; /* Peso da fonte */
                    cursor: pointer; /* Cursor de mãozinha ao passar o mouse */
                    transition: all 0.3s ease; /* Transição suave para todas as propriedades */
                    border: 1px solid transparent; /* Borda transparente por padrão */
                    min-width: 80px; /* Largura mínima */
                }

                /* Estilos para o botão primário (ex: OK, Confirmar) */
                .modal-btn-primary { background: #007bff; color: white; border-color: #007bff; }
                .modal-btn-primary:hover { background: #0056b3; border-color: #0056b3; transform: translateY(-1px); }

                /* Estilos para o botão secundário */
                .modal-btn-secondary { background: #6c757d; color: white; border-color: #6c757d; }
                .modal-btn-secondary:hover { background: #545b62; border-color: #545b62; transform: translateY(-1px); }

                /* Estilos para o botão de sucesso */
                .modal-btn-success { background: #198754; color: white; border-color: #198754; }
                .modal-btn-success:hover { background: #146c43; border-color: #146c43; transform: translateY(-1px); }

                /* Estilos para o botão de perigo/erro */
                .modal-btn-danger { background: #dc3545; color: white; border-color: #dc3545; }
                .modal-btn-danger:hover { background: #b02a37; border-color: #b02a37; transform: translateY(-1px); }



                /* Estilo para botão "vazado" (geralmente para Cancelar) */
                .modal-btn-outline { background: transparent; color: #6c757d; border-color: #6c757d; }
                .modal-btn-outline:hover { background: #6c757d; color: white; transform: translateY(-1px); }

                /* Animação de fade-in para o overlay */
                @keyframes modalFadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                /* Animação de slide e zoom para a caixa do modal */
                @keyframes modalSlideIn {
                    from { transform: scale(0.9) translateY(-20px); opacity: 0; }
                    to { transform: scale(1) translateY(0); opacity: 1; }
                }

                /* Estilos responsivos para telas pequenas (celulares) */
                @media (max-width: 480px) {
                    .modal-box { min-width: auto; width: 100%; margin: 0 10px; }
                    .modal-footer { flex-direction: column; } /* Empilha os botões verticalmente */
                    .modal-btn { width: 100%; } /* Faz os botões ocuparem a largura total */
                }
            </style>
            
            <!-- Estrutura HTML do modal -->
            <div class="modal-overlay" id="modal-overlay">
                <div class="modal-box" id="modal-box">
                    <div class="modal-header" id="modal-header">
                        <div class="modal-icon" id="modal-icon"></div>
                        <h3 class="modal-title" id="modal-title"></h3>
                    </div>
                    <div class="modal-body" id="modal-body"></div>
                    <div class="modal-footer" id="modal-footer"></div>
                </div>
            </div>
        `;

        // Adiciona o container (com todo o HTML e CSS) ao final do <body> do documento.
        document.body.appendChild(container);
        
        // Adiciona um ouvinte de evento para fechar o modal ao clicar no fundo (overlay).
        document.getElementById('modal-overlay').addEventListener('click', (e) => {
            // Verifica se o elemento clicado foi o próprio overlay, e não um filho dele (como a caixa do modal).
            if (e.target.id === 'modal-overlay') {
                // Chama o método para esconder o modal.
                this.hideModal();
            }
        });

        // Adiciona um ouvinte de evento para fechar o modal ao pressionar a tecla 'Escape'.
        document.addEventListener('keydown', (e) => {
            // Verifica se a tecla pressionada foi 'Escape' e se há um modal ativo.
            if (e.key === 'Escape' && this.currentModal) {
                this.hideModal();
            }
        });
    }

    // Método para configurar e exibir um modal com base nas opções fornecidas.
    showModal(options) {
        // Obtém referências aos elementos do modal que serão atualizados.
        const overlay = document.getElementById('modal-overlay');
        const icon = document.getElementById('modal-icon');
        const title = document.getElementById('modal-title');
        const body = document.getElementById('modal-body');
        const footer = document.getElementById('modal-footer');

        // Define o tipo do modal (info, success, etc.), com 'info' como padrão.
        const type = options.type || 'info';
        // Atualiza a classe do ícone para aplicar o estilo de cor correto.
        icon.className = `modal-icon ${type}`;
        
        // Mapeia os tipos de modal para as classes de ícone do Font Awesome.
        const icons = {
            info: 'fas fa-info-circle',
            success: 'fas fa-check-circle',
            warning: 'fas fa-exclamation-triangle',
            danger: 'fas fa-exclamation-circle',
            question: 'fas fa-question-circle'
        };
        
        // Define o ícone correto dentro do elemento de ícone.
        icon.innerHTML = `<i class="${icons[type]}"></i>`;

        // Define o texto do título e do corpo do modal, com valores padrão.
        title.textContent = options.title || 'Aviso';
        body.innerHTML = options.message || ''; // Usa innerHTML para permitir HTML na mensagem.

        // Limpa o rodapé de botões antigos.
        footer.innerHTML = '';
        
        // Verifica se foram fornecidos botões nas opções.
        if (options.buttons) {
            // Itera sobre cada botão a ser criado.
            options.buttons.forEach(btn => {
                const button = document.createElement('button'); // Cria o elemento <button>.
                button.className = `modal-btn ${btn.class || 'modal-btn-primary'}`; // Define as classes CSS.
                button.textContent = btn.text; // Define o texto do botão.
                // Define a ação a ser executada quando o botão for clicado.
                button.onclick = () => {
                    this.hideModal(); // Sempre esconde o modal ao clicar em um botão.
                    if (btn.callback) btn.callback(); // Se houver uma função de callback, a executa.
                };
                footer.appendChild(button); // Adiciona o botão ao rodapé.
            });
        }

        // Mostra o modal adicionando a classe 'show' ao overlay.
        overlay.classList.add('show');
        // Armazena as opções do modal atual.
        this.currentModal = options;

        // Foca no primeiro botão para acessibilidade (permite usar Enter/Espaço).
        setTimeout(() => {
            const firstBtn = footer.querySelector('.modal-btn');
            if (firstBtn) firstBtn.focus();
        }, 100); // Timeout para garantir que o modal esteja visível e focar funcione.
    }

    // Método para esconder o modal.
    hideModal() {
        const overlay = document.getElementById('modal-overlay');
        // Remove a classe 'show' para que o CSS o esconda.
        overlay.classList.remove('show');
        // Limpa a referência ao modal atual.
        this.currentModal = null;
    }

    // Substituto para a função alert() nativa. Retorna uma Promise.
    alert(message, title = 'Aviso', type = 'info') {
        // Uma Promise é usada para que o código que chama o alert possa esperar o usuário clicar em OK.
        return new Promise((resolve) => {
            this.showModal({
                type: type,
                title: title,
                message: message,
                buttons: [
                    {
                        text: 'OK',
                        class: 'modal-btn-primary',
                        callback: resolve // A Promise é resolvida quando o botão OK é clicado.
                    }
                ]
            });
        });
    }

    // Substituto para a função confirm() nativa. Retorna uma Promise.
    confirm(message, title = 'Confirmação') {
        // A Promise resolve para `true` se confirmar, e `false` se cancelar.
        return new Promise((resolve) => {
            this.showModal({
                type: 'question',
                title: title,
                message: message,
                buttons: [
                    {
                        text: 'Cancelar',
                        class: 'modal-btn-outline',
                        callback: () => resolve(false) // Resolve com `false` ao cancelar.
                    },
                    {
                        text: 'Confirmar',
                        class: 'modal-btn-primary',
                        callback: () => resolve(true) // Resolve com `true` ao confirmar.
                    }
                ]
            });
        });
    }

    // Substituto para a função prompt() nativa. Retorna uma Promise.
    prompt(message, defaultValue = '', title = 'Digite') {
        // A Promise resolve com o texto digitado, ou `null` se for cancelado.
        return new Promise((resolve) => {
            // Cria um ID único para o campo de input para poder selecioná-lo depois.
            const inputId = 'modal-input-' + Date.now();
            // Cria o conteúdo do corpo do modal, incluindo a mensagem e o campo de input.
            const bodyContent = `
                ${message}
                <input type="text" class="modal-input" id="${inputId}" value="${defaultValue}" placeholder="Digite aqui...">
            `;

            this.showModal({
                type: 'question',
                title: title,
                message: bodyContent,
                buttons: [
                    {
                        text: 'Cancelar',
                        class: 'modal-btn-outline',
                        callback: () => resolve(null) // Resolve com `null` ao cancelar.
                    },
                    {
                        text: 'OK',
                        class: 'modal-btn-primary',
                        callback: () => {
                            // Pega o valor do input e resolve a Promise com ele.
                            const input = document.getElementById(inputId);
                            resolve(input ? input.value : null);
                        }
                    }
                ]
            });

            // Foca no campo de input e seleciona o texto padrão.
            setTimeout(() => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 100);
        });
    }

    // Métodos de conveniência para os tipos mais comuns de alertas.
    success(message, title = 'Sucesso') {
        return this.alert(message, title, 'success');
    }

    error(message, title = 'Erro') {
        return this.alert(message, title, 'danger');
    }

    warning(message, title = 'Atenção') {
        return this.alert(message, title, 'warning');
    }

    info(message, title = 'Informação') {
        return this.alert(message, title, 'info');
    }
}

// Cria uma instância global da classe ModalSystem, acessível em qualquer lugar como `window.modalSystem`.
window.modalSystem = new ModalSystem();

// Substitui as funções globais do navegador pelas novas funções do nosso sistema de modal.
// Isso permite que código antigo que usa `alert()` continue funcionando, mas com os novos modais.
window.alert = function(message, title, type) {
    return window.modalSystem.alert(message, title, type);
};

window.confirm = function(message, title) {
    return window.modalSystem.confirm(message, title);
};

window.prompt = function(message, defaultValue, title) {
    return window.modalSystem.prompt(message, defaultValue, title);
};
