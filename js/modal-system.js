/**
 * Sistema de Modais Padronizado
 * Substitui alert(), confirm() e prompt() por modais elegantes
 */

class ModalSystem {
    constructor() {
        this.currentModal = null;
        this.createModalContainer();
    }

    createModalContainer() {
        // Aguardar DOM estar pronto
        if (!document.body) {
            setTimeout(() => this.createModalContainer(), 10);
            return;
        }

        // Remove modal container existente se houver
        const existing = document.getElementById('modal-system-container');
        if (existing) {
            existing.remove();
        }

        // Cria o container dos modais
        const container = document.createElement('div');
        container.id = 'modal-system-container';
        container.innerHTML = `
            <style>
                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.5);
                    backdrop-filter: blur(8px);
                    z-index: 10000;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    animation: modalFadeIn 0.3s ease-out;
                }

                .modal-overlay.show {
                    display: flex;
                }

                .modal-box {
                    background: white;
                    border-radius: 12px;
                    padding: 0;
                    min-width: 300px;
                    max-width: 90vw;
                    max-height: 80vh;
                    overflow: hidden;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    transform: scale(0.9);
                    animation: modalSlideIn 0.3s ease-out forwards;
                }

                .modal-header {
                    padding: 20px 24px 12px;
                    border-bottom: 1px solid #e9ecef;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .modal-icon {
                    font-size: 1.5rem;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }

                .modal-icon.info {
                    background: rgba(13, 110, 253, 0.1);
                    color: #0d6efd;
                }

                .modal-icon.success {
                    background: rgba(25, 135, 84, 0.1);
                    color: #198754;
                }

                .modal-icon.warning {
                    background: rgba(255, 193, 7, 0.1);
                    color: #ffc107;
                }

                .modal-icon.danger {
                    background: rgba(220, 53, 69, 0.1);
                    color: #dc3545;
                }

                .modal-icon.question {
                    background: rgba(102, 16, 242, 0.1);
                    color: #6610f2;
                }

                .modal-title {
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: #212529;
                    margin: 0;
                    flex-grow: 1;
                }

                .modal-body {
                    padding: 20px 24px;
                    color: #495057;
                    line-height: 1.6;
                    font-size: 1rem;
                }

                .modal-input {
                    width: 100%;
                    padding: 12px 16px;
                    border: 2px solid #e9ecef;
                    border-radius: 8px;
                    font-size: 1rem;
                    margin-top: 12px;
                    transition: border-color 0.3s ease;
                }

                .modal-input:focus {
                    outline: none;
                    border-color: #007bff;
                    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
                }

                .modal-footer {
                    padding: 16px 24px 24px;
                    display: flex;
                    gap: 12px;
                    justify-content: flex-end;
                    border-top: 1px solid #e9ecef;
                    background: #f8f9fa;
                }

                .modal-btn {
                    padding: 10px 20px;
                    border-radius: 6px;
                    font-size: 0.875rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    border: 1px solid transparent;
                    min-width: 80px;
                }

                .modal-btn-primary {
                    background: #007bff;
                    color: white;
                    border-color: #007bff;
                }

                .modal-btn-primary:hover {
                    background: #0056b3;
                    border-color: #0056b3;
                    transform: translateY(-1px);
                }

                .modal-btn-secondary {
                    background: #6c757d;
                    color: white;
                    border-color: #6c757d;
                }

                .modal-btn-secondary:hover {
                    background: #545b62;
                    border-color: #545b62;
                    transform: translateY(-1px);
                }

                .modal-btn-success {
                    background: #198754;
                    color: white;
                    border-color: #198754;
                }

                .modal-btn-success:hover {
                    background: #146c43;
                    border-color: #146c43;
                    transform: translateY(-1px);
                }

                .modal-btn-danger {
                    background: #dc3545;
                    color: white;
                    border-color: #dc3545;
                }

                .modal-btn-danger:hover {
                    background: #b02a37;
                    border-color: #b02a37;
                    transform: translateY(-1px);
                }

                .modal-btn-outline {
                    background: transparent;
                    color: #6c757d;
                    border-color: #6c757d;
                }

                .modal-btn-outline:hover {
                    background: #6c757d;
                    color: white;
                    transform: translateY(-1px);
                }

                @keyframes modalFadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                @keyframes modalSlideIn {
                    from {
                        transform: scale(0.9) translateY(-20px);
                        opacity: 0;
                    }
                    to {
                        transform: scale(1) translateY(0);
                        opacity: 1;
                    }
                }

                @media (max-width: 480px) {
                    .modal-box {
                        min-width: auto;
                        width: 100%;
                        margin: 0 10px;
                    }
                    
                    .modal-footer {
                        flex-direction: column;
                    }
                    
                    .modal-btn {
                        width: 100%;
                    }
                }
            </style>
            
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

        document.body.appendChild(container);
        
        // Event listener para fechar ao clicar no overlay
        document.getElementById('modal-overlay').addEventListener('click', (e) => {
            if (e.target.id === 'modal-overlay') {
                this.hideModal();
            }
        });

        // Event listener para ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.currentModal) {
                this.hideModal();
            }
        });
    }

    showModal(options) {
        const overlay = document.getElementById('modal-overlay');
        const icon = document.getElementById('modal-icon');
        const title = document.getElementById('modal-title');
        const body = document.getElementById('modal-body');
        const footer = document.getElementById('modal-footer');

        // Configurar ícone e estilo
        const type = options.type || 'info';
        icon.className = `modal-icon ${type}`;
        
        const icons = {
            info: 'fas fa-info-circle',
            success: 'fas fa-check-circle',
            warning: 'fas fa-exclamation-triangle',
            danger: 'fas fa-exclamation-circle',
            question: 'fas fa-question-circle'
        };
        
        icon.innerHTML = `<i class="${icons[type]}"></i>`;

        // Configurar título e corpo
        title.textContent = options.title || 'Aviso';
        body.innerHTML = options.message || '';

        // Limpar footer e adicionar botões
        footer.innerHTML = '';
        
        if (options.buttons) {
            options.buttons.forEach(btn => {
                const button = document.createElement('button');
                button.className = `modal-btn ${btn.class || 'modal-btn-primary'}`;
                button.textContent = btn.text;
                button.onclick = () => {
                    this.hideModal();
                    if (btn.callback) btn.callback();
                };
                footer.appendChild(button);
            });
        }

        // Mostrar modal
        overlay.classList.add('show');
        this.currentModal = options;

        // Focus no primeiro botão
        setTimeout(() => {
            const firstBtn = footer.querySelector('.modal-btn');
            if (firstBtn) firstBtn.focus();
        }, 100);
    }

    hideModal() {
        const overlay = document.getElementById('modal-overlay');
        overlay.classList.remove('show');
        this.currentModal = null;
    }

    // Substituto para alert()
    alert(message, title = 'Aviso', type = 'info') {
        return new Promise((resolve) => {
            this.showModal({
                type: type,
                title: title,
                message: message,
                buttons: [
                    {
                        text: 'OK',
                        class: 'modal-btn-primary',
                        callback: resolve
                    }
                ]
            });
        });
    }

    // Substituto para confirm()
    confirm(message, title = 'Confirmação') {
        return new Promise((resolve) => {
            this.showModal({
                type: 'question',
                title: title,
                message: message,
                buttons: [
                    {
                        text: 'Cancelar',
                        class: 'modal-btn-outline',
                        callback: () => resolve(false)
                    },
                    {
                        text: 'Confirmar',
                        class: 'modal-btn-primary',
                        callback: () => resolve(true)
                    }
                ]
            });
        });
    }

    // Substituto para prompt()
    prompt(message, defaultValue = '', title = 'Digite') {
        return new Promise((resolve) => {
            const inputId = 'modal-input-' + Date.now();
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
                        callback: () => resolve(null)
                    },
                    {
                        text: 'OK',
                        class: 'modal-btn-primary',
                        callback: () => {
                            const input = document.getElementById(inputId);
                            resolve(input ? input.value : null);
                        }
                    }
                ]
            });

            // Focus no input após mostrar
            setTimeout(() => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 100);
        });
    }

    // Métodos de conveniência
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

// Criar instância global
window.modalSystem = new ModalSystem();

// Substituir funções globais
window.alert = function(message, title, type) {
    return window.modalSystem.alert(message, title, type);
};

window.confirm = function(message, title) {
    return window.modalSystem.confirm(message, title);
};

window.prompt = function(message, defaultValue, title) {
    return window.modalSystem.prompt(message, defaultValue, title);
};