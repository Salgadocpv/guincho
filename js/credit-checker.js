/**
 * Sistema de Verificação de Créditos
 * Verifica se o guincheiro tem créditos suficientes antes de acessar páginas que exigem créditos
 */

class CreditChecker {
    constructor() {
        this.apiUrl = '../api/credits/get_credits_simple.php';
        this.insufficientCreditsPage = 'insufficient-credits.html';
    }

    /**
     * Verifica se o guincheiro pode aceitar viagens
     */
    async checkCredits() {
        try {
            const response = await fetch(this.apiUrl);
            const data = await response.json();
            
            if (data.success) {
                return {
                    canAcceptTrip: data.data.can_accept_trip,
                    currentBalance: data.data.credits.current_balance,
                    tripCost: data.data.settings.cost_per_trip,
                    credits: data.data.credits,
                    settings: data.data.settings
                };
            }
            
            return {
                canAcceptTrip: false,
                currentBalance: 0,
                tripCost: 25,
                error: data.message || 'Erro ao verificar créditos'
            };
            
        } catch (error) {
            console.error('Erro na verificação de créditos:', error);
            return {
                canAcceptTrip: false,
                currentBalance: 0,
                tripCost: 25,
                error: 'Erro de conexão'
            };
        }
    }

    /**
     * Bloqueia acesso se não tiver créditos suficientes
     */
    async enforceCreditsRequired() {
        const creditInfo = await this.checkCredits();
        
        if (!creditInfo.canAcceptTrip) {
            // Redirecionar para página de créditos insuficientes
            this.redirectToInsufficientCredits();
            return false;
        }
        
        return true;
    }

    /**
     * Redireciona para página de créditos insuficientes
     */
    redirectToInsufficientCredits() {
        // Usar replace para não permitir voltar com botão de voltar
        window.location.replace(this.insufficientCreditsPage);
    }

    /**
     * Mostra modal de aviso sobre créditos insuficientes
     */
    async showInsufficientCreditsModal() {
        const creditInfo = await this.checkCredits();
        
        const modal = `
            <div class="modal-overlay" onclick="this.remove()">
                <div class="modal-content credit-warning-modal" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <h3><i class="fas fa-exclamation-triangle text-warning"></i> Créditos Insuficientes</h3>
                        <button class="modal-close" onclick="this.closest('.modal-overlay').remove()">×</button>
                    </div>
                    <div class="modal-body">
                        <div class="credit-info-modal">
                            <p><strong>Saldo atual:</strong> R$ ${creditInfo.currentBalance.toFixed(2).replace('.', ',')}</p>
                            <p><strong>Custo por viagem:</strong> R$ ${creditInfo.tripCost.toFixed(2).replace('.', ',')}</p>
                        </div>
                        <p>Você precisa recarregar seus créditos via PIX para aceitar viagens.</p>
                    </div>
                    <div class="modal-footer">
                        <button onclick="window.location.href='credits.html'" class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Recarregar PIX
                        </button>
                        <button onclick="this.closest('.modal-overlay').remove()" class="btn btn-secondary">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modal);
    }

    /**
     * Atualiza indicador de créditos na interface
     */
    async updateCreditDisplay(selector = '.credit-display') {
        const creditInfo = await this.checkCredits();
        const elements = document.querySelectorAll(selector);
        
        elements.forEach(element => {
            if (creditInfo.canAcceptTrip) {
                element.innerHTML = `
                    <i class="fas fa-coins text-success"></i>
                    R$ ${creditInfo.currentBalance.toFixed(2).replace('.', ',')}
                `;
                element.className = element.className.replace('insufficient', 'sufficient');
            } else {
                element.innerHTML = `
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    R$ ${creditInfo.currentBalance.toFixed(2).replace('.', ',')}
                `;
                element.className = element.className.replace('sufficient', 'insufficient');
            }
        });
    }

    /**
     * Inicia verificação automática de créditos
     */
    startAutoCheck(interval = 60000) { // 1 minuto
        setInterval(async () => {
            const creditInfo = await this.checkCredits();
            
            // Atualizar displays de crédito se existirem
            this.updateCreditDisplay();
            
            // Se estiver em uma página que requer créditos e não tiver mais
            if (!creditInfo.canAcceptTrip && this.isOnCreditRequiredPage()) {
                this.redirectToInsufficientCredits();
            }
        }, interval);
    }

    /**
     * Verifica se está em uma página que requer créditos
     */
    isOnCreditRequiredPage() {
        const currentPage = window.location.pathname.split('/').pop();
        const creditRequiredPages = [
            'available-requests.html',
            'simple-requests.html',
            'active-trips.html'
        ];
        
        return creditRequiredPages.includes(currentPage);
    }
}

// Instância global
window.creditChecker = new CreditChecker();

// CSS para os modais de crédito
const creditModalCSS = `
<style>
.credit-warning-modal {
    max-width: 400px;
}

.credit-info-modal {
    background: rgba(0,0,0,0.1);
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}

.credit-info-modal p {
    margin: 5px 0;
}

.text-warning {
    color: #ffc107;
}

.text-success {
    color: #28a745;
}

.credit-display.insufficient {
    color: #dc3545;
}

.credit-display.sufficient {
    color: #28a745;
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', creditModalCSS);