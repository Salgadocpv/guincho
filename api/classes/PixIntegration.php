<?php
class PixIntegration {
    private $pdo;
    private $settings;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $query = "SELECT setting_key, setting_value FROM system_settings 
                 WHERE category LIKE 'pix_%' OR setting_key LIKE '%pix%'";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        
        $this->settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    // Gerar QR Code PIX
    public function generatePixQRCode($amount, $description = '', $additionalInfo = []) {
        $provider = $this->settings['pix_provider'] ?? 'manual';
        
        switch ($provider) {
            case 'manual':
                return $this->generateManualPixQR($amount, $description, $additionalInfo);
                
            case 'mercadopago':
                return $this->generateMercadoPagoPixQR($amount, $description, $additionalInfo);
                
            case 'pagseguro':
                return $this->generatePagSeguroPixQR($amount, $description, $additionalInfo);
                
            case 'gerencianet':
                return $this->generateGerencianetPixQR($amount, $description, $additionalInfo);
                
            default:
                throw new Exception('Provedor PIX não configurado ou inválido');
        }
    }
    
    // Verificar status de pagamento PIX
    public function checkPaymentStatus($paymentId) {
        $provider = $this->settings['pix_provider'] ?? 'manual';
        
        switch ($provider) {
            case 'manual':
                // Modo manual sempre retorna pendente
                return [
                    'status' => 'pending',
                    'message' => 'Verificação manual necessária'
                ];
                
            case 'mercadopago':
                return $this->checkMercadoPagoPayment($paymentId);
                
            case 'pagseguro':
                return $this->checkPagSeguroPayment($paymentId);
                
            case 'gerencianet':
                return $this->checkGerencianetPayment($paymentId);
                
            default:
                throw new Exception('Provedor PIX não configurado ou inválido');
        }
    }
    
    // Gerar QR Code manual (EMV)
    private function generateManualPixQR($amount, $description, $additionalInfo) {
        $pixKey = $this->settings['company_pix_key'] ?? '';
        $merchantName = $this->settings['company_name'] ?? 'Iguincho Serviços';
        $merchantCity = $this->settings['company_city'] ?? 'São Paulo';
        
        if (empty($pixKey)) {
            throw new Exception('Chave PIX da empresa não configurada');
        }
        
        // Gerar payload EMV básico
        $payload = $this->generateEMVPayload($pixKey, $merchantName, $merchantCity, $amount, $description);
        
        return [
            'qr_code' => $payload,
            'payment_id' => 'MANUAL_' . time() . '_' . rand(1000, 9999),
            'amount' => $amount,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'provider' => 'manual',
            'pix_key' => $pixKey,
            'merchant_name' => $merchantName,
            'description' => $description
        ];
    }
    
    // Gerar payload EMV para PIX
    private function generateEMVPayload($pixKey, $merchantName, $merchantCity, $amount, $description) {
        // Simplificado - em produção usar biblioteca específica para EMV
        $merchantAccountInfo = "0014br.gov.bcb.pix01" . str_pad(strlen($pixKey), 2, '0', STR_PAD_LEFT) . $pixKey;
        
        $payload = "00020126" . // Payload Format Indicator
                  str_pad(strlen($merchantAccountInfo), 2, '0', STR_PAD_LEFT) . $merchantAccountInfo . // Merchant Account Information
                  "52040000" . // Merchant Category Code
                  "5303986" . // Transaction Currency (986 = BRL)
                  "54" . str_pad(strlen(number_format($amount, 2, '.', '')), 2, '0', STR_PAD_LEFT) . number_format($amount, 2, '.', '') . // Transaction Amount
                  "5802BR" . // Country Code
                  "59" . str_pad(strlen($merchantName), 2, '0', STR_PAD_LEFT) . $merchantName . // Merchant Name
                  "60" . str_pad(strlen($merchantCity), 2, '0', STR_PAD_LEFT) . $merchantCity; // Merchant City
        
        if (!empty($description)) {
            $payload .= "62" . str_pad(strlen($description) + 4, 2, '0', STR_PAD_LEFT) . "05" . str_pad(strlen($description), 2, '0', STR_PAD_LEFT) . $description;
        }
        
        // Calcular CRC16
        $payload .= "6304";
        $crc = $this->calculateCRC16($payload);
        $payload .= strtoupper(dechex($crc));
        
        return $payload;
    }
    
    // Calcular CRC16 CCITT
    private function calculateCRC16($data) {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc = $crc << 1;
                }
                $crc &= 0xFFFF;
            }
        }
        return $crc;
    }
    
    // Integração MercadoPago
    private function generateMercadoPagoPixQR($amount, $description, $additionalInfo) {
        $accessToken = $this->settings['mp_access_token'] ?? '';
        $sandbox = $this->settings['pix_sandbox_mode'] === 'true';
        
        if (empty($accessToken)) {
            throw new Exception('Access Token do MercadoPago não configurado');
        }
        
        $baseUrl = $sandbox ? 'https://api.mercadopago.com/sandbox' : 'https://api.mercadopago.com';
        
        $paymentData = [
            'transaction_amount' => floatval($amount),
            'description' => $description ?: 'Recarga de créditos Iguincho',
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $additionalInfo['email'] ?? 'usuario@iguincho.com'
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            throw new Exception('Erro ao criar pagamento PIX no MercadoPago: ' . $response);
        }
        
        $data = json_decode($response, true);
        
        return [
            'qr_code' => $data['point_of_interaction']['transaction_data']['qr_code'] ?? '',
            'payment_id' => $data['id'],
            'amount' => $amount,
            'expires_at' => $data['date_of_expiration'] ?? date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'provider' => 'mercadopago',
            'status' => $data['status'],
            'description' => $description,
            'qr_code_base64' => $data['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null
        ];
    }
    
    // Verificar pagamento MercadoPago
    private function checkMercadoPagoPayment($paymentId) {
        $accessToken = $this->settings['mp_access_token'] ?? '';
        $sandbox = $this->settings['pix_sandbox_mode'] === 'true';
        
        $baseUrl = $sandbox ? 'https://api.mercadopago.com/sandbox' : 'https://api.mercadopago.com';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/payments/' . $paymentId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Erro ao consultar pagamento no MercadoPago');
        }
        
        $data = json_decode($response, true);
        
        return [
            'status' => $data['status'], // pending, approved, rejected, cancelled
            'amount' => $data['transaction_amount'],
            'paid_at' => $data['date_approved'] ?? null,
            'payer_info' => $data['payer'] ?? []
        ];
    }
    
    // Métodos para PagSeguro e Gerencianet seguiriam padrão similar...
    private function generatePagSeguroPixQR($amount, $description, $additionalInfo) {
        // Implementação PagSeguro
        throw new Exception('Integração PagSeguro em desenvolvimento');
    }
    
    private function checkPagSeguroPayment($paymentId) {
        throw new Exception('Verificação PagSeguro em desenvolvimento');
    }
    
    private function generateGerencianetPixQR($amount, $description, $additionalInfo) {
        // Implementação Gerencianet
        throw new Exception('Integração Gerencianet em desenvolvimento');
    }
    
    private function checkGerencianetPayment($paymentId) {
        throw new Exception('Verificação Gerencianet em desenvolvimento');
    }
    
    // Webhook para notificações de pagamento
    public function processWebhook($provider, $data) {
        switch ($provider) {
            case 'mercadopago':
                return $this->processMercadoPagoWebhook($data);
                
            case 'pagseguro':
                return $this->processPagSeguroWebhook($data);
                
            case 'gerencianet':
                return $this->processGerencianetWebhook($data);
                
            default:
                throw new Exception('Webhook não suportado para o provedor: ' . $provider);
        }
    }
    
    private function processMercadoPagoWebhook($data) {
        // Processar webhook do MercadoPago
        if (isset($data['type']) && $data['type'] === 'payment') {
            $paymentId = $data['data']['id'];
            
            // Buscar informações completas do pagamento
            $paymentInfo = $this->checkMercadoPagoPayment($paymentId);
            
            if ($paymentInfo['status'] === 'approved') {
                // Pagamento aprovado - processar
                return [
                    'payment_approved' => true,
                    'payment_id' => $paymentId,
                    'amount' => $paymentInfo['amount'],
                    'paid_at' => $paymentInfo['paid_at']
                ];
            }
        }
        
        return ['payment_approved' => false];
    }
    
    private function processPagSeguroWebhook($data) {
        // Implementação futura
        return ['payment_approved' => false];
    }
    
    private function processGerencianetWebhook($data) {
        // Implementação futura
        return ['payment_approved' => false];
    }
}
?>