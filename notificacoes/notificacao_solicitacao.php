<?php
/**
 * Script para adicionar notificações para solicitações de orçamento
 */

require_once(__DIR__ . '/includes/notificacoes.php');

/**
 * Adiciona uma notificação para uma nova solicitação de orçamento
 * 
 * @param int $cliente_id ID do cliente
 * @param string $cliente_nome Nome do cliente
 * @param array $dados_adicionais Dados adicionais como telefone, endereço, etc.
 * @param string $tipo_solicitacao Tipo de solicitação (visita, orçamento, etc)
 * @return bool Sucesso ou falha
 */
function notificarSolicitacaoOrcamento($cliente_id, $cliente_nome, $dados_adicionais = [], $tipo_solicitacao = 'visita') {
    $link = "clientes.php?id={$cliente_id}";
    $telefone = isset($dados_adicionais['telefone']) ? $dados_adicionais['telefone'] : '';
    
    // Construir mensagem de notificação
    $mensagem = "NOVA SOLICITAÇÃO: "; 
    
    if ($tipo_solicitacao == 'visita') {
        $mensagem .= "Visita técnica agendada";
    } else {
        $mensagem .= "Orçamento solicitado";
    }
    
    $mensagem .= " por {$cliente_nome}";
    
    if (!empty($telefone)) {
        $mensagem .= " - {$telefone}";
    }
    
    if (isset($dados_adicionais['data_agendamento']) && isset($dados_adicionais['hora_inicio'])) {
        $data_formatada = date('d/m/Y', strtotime($dados_adicionais['data_agendamento']));
        $mensagem .= " para {$data_formatada} às {$dados_adicionais['hora_inicio']}";
    }
    
    // Adicionar notificação categorizada
    return adicionarNotificacao($mensagem, 'warning', $link, 'solicitacoes');
}

/**
 * Notifica sobre a criação de um novo orçamento a partir de solicitação
 * 
 * @param int $orcamento_id ID do orçamento criado
 * @param string $numero Número do orçamento
 * @param string $cliente_nome Nome do cliente
 * @return bool Sucesso ou falha
 */
function notificarNovoOrcamento($orcamento_id, $numero, $cliente_nome) {
    $link = "orcamento_visualizar.php?id={$orcamento_id}";
    $mensagem = "NOVO ORÇAMENTO #{$numero} criado para {$cliente_nome}";
    
    // Adicionar notificação categorizada
    return adicionarNotificacao($mensagem, 'primary', $link, 'solicitacoes');
}
