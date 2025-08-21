<?php
/**
 * Script para criar configurações de preços no sistema
 */

// Ajustar diretório de trabalho
chdir(dirname(__DIR__));
require_once 'config/database.php';
require_once 'classes/SystemSettings.php';

try {
    echo "🚀 Criando configurações de preços do sistema...\n\n";
    
    $settings = new SystemSettings();
    
    // Verificar se as configurações já existem
    $pricingSettings = [
        'minimum_trip_value' => [
            'value' => 25.00,
            'type' => 'number',
            'description' => 'Valor mínimo para uma viagem de guincho (R$)',
            'category' => 'pricing',
            'is_public' => true
        ],
        'price_per_km' => [
            'value' => 3.50,
            'type' => 'number',
            'description' => 'Valor por quilômetro rodado (R$)',
            'category' => 'pricing',
            'is_public' => true
        ],
        'base_service_fee' => [
            'value' => 15.00,
            'type' => 'number',
            'description' => 'Taxa base do serviço (R$)',
            'category' => 'pricing',
            'is_public' => true
        ],
        'emergency_multiplier' => [
            'value' => 1.5,
            'type' => 'number',
            'description' => 'Multiplicador para atendimentos emergenciais',
            'category' => 'pricing',
            'is_public' => true
        ],
        'night_shift_multiplier' => [
            'value' => 1.3,
            'type' => 'number',
            'description' => 'Multiplicador para atendimentos noturnos (22h-6h)',
            'category' => 'pricing',
            'is_public' => true
        ],
        'weekend_multiplier' => [
            'value' => 1.2,
            'type' => 'number',
            'description' => 'Multiplicador para finais de semana',
            'category' => 'pricing',
            'is_public' => true
        ]
    ];
    
    foreach ($pricingSettings as $key => $config) {
        if (!$settings->exists($key)) {
            $result = $settings->create(
                $key,
                $config['value'],
                $config['type'],
                $config['description'],
                $config['category'],
                $config['is_public']
            );
            
            if ($result) {
                echo "✅ Criada configuração: {$key} = R$ {$config['value']}\n";
            } else {
                echo "❌ Erro ao criar configuração: {$key}\n";
            }
        } else {
            echo "ℹ️  Configuração já existe: {$key}\n";
        }
    }
    
    echo "\n📋 CONFIGURAÇÕES DE PREÇOS CRIADAS:\n";
    echo "=====================================\n";
    echo "• Valor mínimo da viagem: R$ " . number_format($pricingSettings['minimum_trip_value']['value'], 2, ',', '.') . "\n";
    echo "• Preço por quilômetro: R$ " . number_format($pricingSettings['price_per_km']['value'], 2, ',', '.') . "\n";
    echo "• Taxa base do serviço: R$ " . number_format($pricingSettings['base_service_fee']['value'], 2, ',', '.') . "\n";
    echo "• Multiplicador emergencial: " . $pricingSettings['emergency_multiplier']['value'] . "x\n";
    echo "• Multiplicador noturno: " . $pricingSettings['night_shift_multiplier']['value'] . "x\n";
    echo "• Multiplicador fim de semana: " . $pricingSettings['weekend_multiplier']['value'] . "x\n";
    
    echo "\n🔧 Essas configurações podem ser editadas no painel administrativo.\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>