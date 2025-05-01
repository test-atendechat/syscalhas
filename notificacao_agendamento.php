<?php
/**
 * Script para adicionar notificações para agendamentos
 */

require_once(__DIR__ . '/includes/notificacoes.php');

/**
 * Adiciona uma notificação para um novo agendamento
 * 
 * @param int $agendamento_id ID do agendamento
 * @param string $data_agendamento Data do agendamento formatada
 * @param string $hora_inicio Hora de início do agendamento
 * @param string $cliente_nome Nome do cliente
 * @param int $orcamento_id ID do orçamento relacionado
 * @return bool Sucesso ou falha
 */
function notificarNovoAgendamento($agendamento_id, $data_agendamento, $hora_inicio, $cliente_nome, $orcamento_id = 0) {
    $link = "agendamento.php?data={$data_agendamento}";
    
    $mensagem = "NOVO AGENDAMENTO: para {$data_agendamento} às {$hora_inicio}";
    
    if (!empty($cliente_nome)) {
        $mensagem .= " - Cliente: {$cliente_nome}";
    }
    
    if ($orcamento_id > 0) {
        $mensagem .= " - <a href='orcamento_visualizar.php?id={$orcamento_id}'>Ver Orçamento</a>";
    }
    
    // Adicionar notificação
    return adicionarNotificacao($mensagem, 'primary', $link);
}

/**
 * Adiciona uma notificação para alteração em agendamento
 * 
 * @param int $agendamento_id ID do agendamento
 * @param string $tipo_alteracao Tipo de alteração (cancelado, reagendado, etc)
 * @param string $data_agendamento Data do agendamento formatada
 * @param string $hora_inicio Hora de início do agendamento
 * @param string $cliente_nome Nome do cliente
 * @return bool Sucesso ou falha
 */
function notificarAlteracaoAgendamento($agendamento_id, $tipo_alteracao, $data_agendamento, $hora_inicio, $cliente_nome) {
    $link = "agendamento.php?data={$data_agendamento}";
    
    $mensagem = "ATENÇÃO: Agendamento {$tipo_alteracao}";
    
    if (!empty($data_agendamento) && !empty($hora_inicio)) {
        $mensagem .= " - {$data_agendamento} às {$hora_inicio}";
    }
    
    if (!empty($cliente_nome)) {
        $mensagem .= " - Cliente: {$cliente_nome}";
    }
    
    // Determinar tipo com base no tipo de alteração
    $tipo = 'primary';
    if ($tipo_alteracao == 'cancelado') {
        $tipo = 'danger';
    } elseif ($tipo_alteracao == 'reagendado') {
        $tipo = 'warning';
    } elseif ($tipo_alteracao == 'finalizado') {
        $tipo = 'success';
    }
    
    // Adicionar notificação
    return adicionarNotificacao($mensagem, $tipo, $link);
}
