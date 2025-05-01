<?php
/**
 * Script para adicionar notificações de pagamento
 * Este arquivo é chamado do caixa_form.php após salvar um pagamento
 */

require_once('includes/notificacoes.php');

// Verifica se temos todas as variáveis necessárias
if (!isset($orcamento_id_redirect) || !isset($movimentacao) || !isset($status_data)) {
    error_log("Erro: Notificação de pagamento não pode ser criada, dados insuficientes");
    return;
}

// Formatar valor para exibição na notificação
$valor_formatado = 'R$ ' . number_format($movimentacao['valor'], 2, ',', '.');

// Determinar o tipo de notificação com base no status de pagamento
if ($status_data && $status_data['status_pagamento'] == 'pago_total') {
    // Adicionar notificação de pagamento total
    adicionarNotificacao(
        "Pagamento TOTAL de {$valor_formatado} registrado para o orçamento #{$status_data['numero']}",
        'success',
        "orcamento_visualizar.php?id={$orcamento_id_redirect}"
    );
} else {
    // Adicionar notificação de pagamento parcial
    adicionarNotificacao(
        "Pagamento PARCIAL de {$valor_formatado} registrado para o orçamento #{$status_data['numero']}",
        'warning',
        "orcamento_visualizar.php?id={$orcamento_id_redirect}"
    );
}
