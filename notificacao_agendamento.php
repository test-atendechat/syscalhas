<?php
/**
 * Script para adicionar notificações relacionadas a agendamentos
 */

require_once(__DIR__ . '/includes/notificacoes.php');

/**
 * Gera notificações para novos agendamentos
 * 
 * @param int $agendamento_id ID do agendamento
 * @param string $data_formatada Data do agendamento no formato dd/mm/aaaa
 * @param string $hora_inicio Hora de início no formato HH:MM
 * @param string $cliente_nome Nome do cliente
 * @param int $orcamento_id ID do orçamento relacionado
 * @param string $status Status do agendamento (opcional)
 * @return bool Sucesso ou falha
 */
function notificarNovoAgendamento($agendamento_id, $data_formatada, $hora_inicio, $cliente_nome, $orcamento_id, $status = 'agendado') {
    $link = "agendamento.php?id={$agendamento_id}&orcamento_id={$orcamento_id}";
    
    // Determinar o tipo de agendamento com base no status
    $tipo_agendamento = "Serviço";
    
    if ($status === 'orcamento_agendado') {
        $tipo_agendamento = "Orçamento";
    } else if ($status === 'instalacao_agendada') {
        $tipo_agendamento = "Instalação";
    }
    
    $mensagem = "NOVO AGENDAMENTO: {$tipo_agendamento} agendado para {$cliente_nome} no dia {$data_formatada} às {$hora_inicio}";
    
    // Adicionar notificação categorizada
    return adicionarNotificacao($mensagem, 'info', $link, 'agendamentos');
}

/**
 * Gera notificações para alterações em agendamentos
 * 
 * @param int $agendamento_id ID do agendamento
 * @param string $tipo Tipo de alteração (reagendado, cancelado, etc)
 * @param string $data_formatada Data do agendamento no formato dd/mm/aaaa
 * @param string $hora_inicio Hora de início no formato HH:MM
 * @param string $cliente_nome Nome do cliente
 * @param string $status Status do agendamento (opcional)
 * @return bool Sucesso ou falha
 */
function notificarAlteracaoAgendamento($agendamento_id, $tipo, $data_formatada, $hora_inicio, $cliente_nome, $status = 'agendado') {
    $link = "agendamento.php?id={$agendamento_id}";
    $tipo_titulo = ucfirst($tipo);
    $icone = '';
    $tipo_notificacao = 'info';
    
    // Determinar o tipo de atendimento com base no status
    $tipo_atendimento = "Serviço";
    
    if ($status === 'orcamento_agendado') {
        $tipo_atendimento = "Orçamento";
    } else if ($status === 'instalacao_agendada') {
        $tipo_atendimento = "Instalação";
    }
    
    switch ($tipo) {
        case 'reagendado':
            $mensagem = "AGENDAMENTO ALTERADO: {$tipo_atendimento} para {$cliente_nome} reagendado para {$data_formatada} às {$hora_inicio}";
            $tipo_notificacao = 'warning';
            break;
            
        case 'cancelado':
            $mensagem = "AGENDAMENTO CANCELADO: {$tipo_atendimento} para {$cliente_nome} que seria no dia {$data_formatada} às {$hora_inicio} foi cancelado";
            $tipo_notificacao = 'danger';
            break;
            
        case 'finalizado':
            $mensagem = "{$tipo_atendimento} FINALIZADO: Atendimento para {$cliente_nome} do dia {$data_formatada} às {$hora_inicio} foi concluído";
            $tipo_notificacao = 'success';
            break;
            
        case 'andamento':
            $mensagem = "{$tipo_atendimento} EM ANDAMENTO: Atendimento para {$cliente_nome} do dia {$data_formatada} às {$hora_inicio} está em execução";
            $tipo_notificacao = 'primary';
            break;
            
        default:
            $mensagem = "AGENDAMENTO {$tipo_titulo}: {$tipo_atendimento} para {$cliente_nome} do dia {$data_formatada} às {$hora_inicio} foi atualizado";
            break;
    }
    
    // Adicionar notificação categorizada
    return adicionarNotificacao($mensagem, $tipo_notificacao, $link, 'agendamentos');
}

/**
 * Gera notificações para lembrete de agendamentos próximos
 * 
 * @param int $agendamento_id ID do agendamento
 * @param string $data_formatada Data do agendamento no formato dd/mm/aaaa
 * @param string $hora_inicio Hora de início no formato HH:MM
 * @param string $cliente_nome Nome do cliente
 * @param int $tempo_restante Tempo restante em minutos
 * @return bool Sucesso ou falha
 */
function notificarLembreteAgendamento($agendamento_id, $data_formatada, $hora_inicio, $cliente_nome, $tempo_restante) {
    $link = "agendamento.php?id={$agendamento_id}";
    
    if ($tempo_restante <= 60) {
        $mensagem = "LEMBRETE URGENTE: Serviço para {$cliente_nome} agendado HOJE às {$hora_inicio} (em menos de 1 hora)";
    } else if ($tempo_restante <= 180) {
        $mensagem = "LEMBRETE PRÓXIMO: Serviço para {$cliente_nome} agendado HOJE às {$hora_inicio} (em menos de 3 horas)";
    } else {
        $mensagem = "LEMBRETE: Serviço para {$cliente_nome} agendado para {$data_formatada} às {$hora_inicio}";
    }
    
    // Adicionar notificação categorizada
    return adicionarNotificacao($mensagem, 'warning', $link, 'agendamentos');
}
