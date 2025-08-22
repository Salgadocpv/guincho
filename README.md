# 🚛 Sistema Iguincho - Plataforma de Solicitações de Guincho

Sistema completo de solicitações de guincho com leilão de propostas em tempo real, desenvolvido com PHP, MySQL e JavaScript.

## 🌟 Funcionalidades Principais

### 📱 Para Clientes
- **Solicitação de Viagem**: Interface intuitiva para definir origem e destino
- **Aguardo de Propostas**: Tela com timer visual de 3 minutos para cada proposta
- **Sistema de Leilão**: Recebe múltiplas propostas de guincheiros próximos
- **Contrapropostas**: Pode aumentar oferta se rejeitar todas as propostas
- **Notificações Tempo Real**: Updates instantâneos sobre status da viagem

### 🚛 Para Guincheiros
- **Solicitações Próximas**: Visualiza chamados em raio configurável
- **Envio de Propostas**: Interface para fazer lances competitivos
- **Timer de Expiração**: Propostas expiram em 3 minutos automaticamente
- **Filtros Inteligentes**: Por tipo de serviço e distância
- **Dashboard Completo**: Gerenciamento de viagens e ganhos

### ⚙️ Para Administradores
- **Configurações do Sistema**: Raio de busca, preços mínimos, timeouts
- **Gestão de Usuários**: Aprovação de guincheiros e parceiros
- **Relatórios**: Estatísticas de uso e performance
- **Controle de Preços**: Sistema de precificação dinâmica

## 🏗️ Arquitetura do Sistema

### 📊 Banco de Dados
```sql
- users: Usuários do sistema (clientes, guincheiros, admin)
- drivers: Dados específicos dos guincheiros
- partners: Dados dos parceiros/estabelecimentos
- trip_requests: Solicitações de viagem dos clientes
- trip_bids: Propostas dos guincheiros
- active_trips: Viagens confirmadas em andamento
- trip_notifications: Notificações em tempo real
- system_settings: Configurações do sistema
```

### 🔌 APIs REST
```
POST /api/trips/create_request.php    # Criar solicitação
GET  /api/trips/get_requests.php      # Buscar solicitações próximas
POST /api/trips/place_bid.php         # Fazer proposta
GET  /api/trips/get_bids.php          # Buscar propostas
POST /api/trips/accept_bid.php        # Aceitar proposta
GET  /api/notifications/stream.php    # Stream de notificações (SSE)
GET  /api/notifications/get.php       # Buscar notificações
POST /api/notifications/mark_read.php # Marcar como lida
```

### 🎨 Interfaces
```
index.html                           # Landing page
register.html                        # Cadastro multi-tipo
service-details.html                 # Solicitação de serviços
trip-proposals.html                  # Aguardo de propostas (cliente)
driver/dashboard.html                # Dashboard guincheiro
driver/available-requests.html       # Solicitações disponíveis
admin/dashboard.html                 # Dashboard admin
```

## 🚀 Como Funciona o Fluxo de Viagem

### 1. **Cliente Solicita Viagem**
```mermaid
Cliente → Define origem/destino → Confirma oferta → Gera solicitação no BD
```

### 2. **Sistema Notifica Guincheiros**
```mermaid
BD → Busca guincheiros no raio → Envia notificações → Guincheiros recebem
```

### 3. **Guincheiros Fazem Propostas**
```mermaid
Guincheiro → Vê solicitação → Faz proposta (valor + tempo) → Timer de 3 min inicia
```

### 4. **Cliente Avalia Propostas**
```mermaid
Cliente → Vê propostas com timer → Aceita/Rejeita → Se rejeita todas: contraproposta
```

### 5. **Confirmação da Viagem**
```mermaid
Proposta aceita → Viagem ativa criada → Notificações enviadas → Guincheiro vai ao local
```

## 🛠️ Instalação e Deploy

### Pré-requisitos
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx
- Extensões PHP: PDO, JSON, OpenSSL

### Passos de Instalação

1. **Clone o repositório**
```bash
git clone https://github.com/Salgadocpv/guincho.git
cd guincho
```

2. **Configure o banco de dados**
```php
// api/config/database.php
$host = "localhost";
$db_name = "iguincho";
$username = "root";
$password = "";
```

3. **Execute o setup**
```
Acesse: http://localhost/guincho/deploy.php
```

4. **Teste o sistema**
```
Acesse: http://localhost/guincho/api/test/trip-system-test.php
```

### Usuários Padrão
```
Admin: admin@iguincho.com / admin123
Cliente: cliente@teste.com / teste123
Guincheiro: guincheiro@teste.com / teste123
```

## 🔧 Configurações do Sistema

### Parâmetros Principais
- `trip_request_timeout_minutes`: Tempo limite para solicitações (padrão: 30 min)
- `bid_timeout_minutes`: Tempo limite para propostas (padrão: 3 min)
- `driver_search_radius_km`: Raio de busca por guincheiros (padrão: 25 km)
- `minimum_trip_value`: Valor mínimo da viagem (padrão: R$ 25,00)
- `max_bids_per_request`: Máximo de propostas por solicitação (padrão: 10)

### Personalização
Todos os parâmetros podem ser alterados via dashboard admin ou diretamente na tabela `system_settings`.

## 📱 Recursos Avançados

### ⏱️ Sistema de Timer Visual
- Barras de progresso com gradiente decrescente
- Countdown em tempo real
- Cores que mudam conforme proximidade do fim

### 🔔 Notificações em Tempo Real
- Server-Sent Events (SSE) para updates instantâneos
- Notificações sonoras opcionais
- Badges de contagem não lidas
- Persistência entre sessões

### 🗺️ Geolocalização Inteligente
- Busca automática por GPS
- Fallback para endereço manual
- Cálculo de distâncias otimizado
- Integração com Google Maps

### 💰 Sistema de Precificação
- Preços dinâmicos baseados em distância
- Multiplicadores por horário (noturno, fim de semana)
- Validação de valores mínimos
- Comissões configuráveis

## 🧪 Testes

### Teste Manual
1. Acesse `/api/test/trip-system-test.php`
2. Execute cada teste sequencialmente
3. Verifique logs de erro em caso de falha

### Teste das APIs
```bash
# Criar solicitação
curl -X POST http://localhost/guincho/api/trips/create_request.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"service_type":"guincho","origin_lat":-23.5505,"origin_lng":-46.6333,...}'

# Buscar solicitações
curl -X GET "http://localhost/guincho/api/trips/get_requests.php?lat=-23.5505&lng=-46.6333" \
  -H "Authorization: Bearer TOKEN"
```

## 🐛 Solução de Problemas

### Problemas Comuns

**Erro: "Table doesn't exist"**
- Execute o script de criação de tabelas: `/api/database/setup_trip_system.php`

**Notificações não funcionam**
- Verifique se o Server-Sent Events está habilitado no servidor
- Teste a URL: `/api/notifications/stream.php?token=TOKEN`

**GPS não funciona**
- Verifique se o site está sendo acessado via HTTPS
- Teste com endereços manuais como fallback

**Propostas não aparecem**
- Verifique se há guincheiros cadastrados e aprovados
- Confirme se estão dentro do raio de busca configurado

## 📈 Roadmap Futuro

- [ ] App mobile nativo
- [ ] Integração com WhatsApp Business
- [ ] Sistema de pagamento integrado
- [ ] Rastreamento GPS em tempo real
- [ ] Sistema de avaliações e reviews
- [ ] Chat entre cliente e guincheiro
- [ ] Relatórios avançados e analytics
- [ ] API para parceiros externos

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📝 Licença

Este projeto está sob a licença MIT. Veja o arquivo `LICENSE` para mais detalhes.

## 📞 Suporte

Para suporte técnico ou dúvidas:
- Email: suporte@iguincho.com
- WhatsApp: (11) 99999-9999
- GitHub Issues: [Criar issue](https://github.com/Salgadocpv/guincho/issues)

---

**Sistema desenvolvido com ❤️ para conectar clientes e guincheiros de forma eficiente e transparente.**