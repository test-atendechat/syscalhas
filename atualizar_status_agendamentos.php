<?php
// Script para atualizar automaticamente o status dos agendamentos
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('notificacao_agendamento.php');

// Inicialização das variáveis
$data_atual = date('Y-m-d');
$hora_atual = date('H:i:s');
$datetime_atual = date('Y-m-d H:i:s');
$log = "====================================================================\n";
$log .= "Atualização de Status de Agendamentos - " . date('Y-m-d H:i:s') . "\n";
$log .= "====================================================================\n\n";

// Contador para estatísticas
$total_atualizados = 0;
$total_em_andamento = 0;
$total_concluidos = 0;

try {
    global $pdo;
    
    // Buscar agendamentos que estão com status 'agendado', 'orcamento_agendado' ou 'instalacao_agendada' e já chegou a hora de início
    // Consulta baseada em data_inicio (timestamp) para a nova estrutura de dados
    $stmt = $pdo->prepare("UPDATE agendamentos 
                        SET status = 'em_andamento'
                        WHERE (status = 'agendado' OR status = 'orcamento_agendado' OR status = 'instalacao_agendada') 
                        AND data_inicio <= :datetime_atual 
                        AND data_fim >= :datetime_atual
                        RETURNING id, data_inicio, status");
                        
    $stmt->bindParam(':datetime_atual', $datetime_atual);
    $stmt->execute();
    
    $atualizados_em_andamento = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_em_andamento = count($atualizados_em_andamento);
    
    if ($total_em_andamento > 0) {
        $log .= "Agendamentos atualizados para 'Em Andamento': {$total_em_andamento}\n";
        foreach ($atualizados_em_andamento as $agenda) {
            $log .= "- ID: {$agenda['id']}, Data/Hora de Início: {$agenda['data_inicio']}\n";
            
            // Buscar dados do agendamento para notificação
            $agendamento_id = $agenda['id'];
            $status_original = $agenda['status']; // Status antes da mudança
            
            $stmt_notificacao = $pdo->prepare("SELECT a.data_agendamento, a.hora_inicio, cl.nome as cliente_nome
                                         FROM agendamentos a
                                         JOIN orcamentos o ON o.id = a.orcamento_id
                                         JOIN clientes cl ON cl.id = o.cliente_id
                                         WHERE a.id = :agendamento_id");
            $stmt_notificacao->bindParam(':agendamento_id', $agendamento_id, PDO::PARAM_INT);
            $stmt_notificacao->execute();
            $dados_notificacao = $stmt_notificacao->fetch(PDO::FETCH_ASSOC);
            
            if ($dados_notificacao) {
                $data_formatada = date('d/m/Y', strtotime($dados_notificacao['data_agendamento']));
                notificarAlteracaoAgendamento(
                    $agendamento_id,
                    'andamento',
                    $data_formatada,
                    $dados_notificacao['hora_inicio'],
                    $dados_notificacao['cliente_nome'],
                    $status_original
                );
            }
        }
    } else {
        $log .= "Nenhum agendamento atualizado para 'Em Andamento'.\n";
    }
    
    // Buscar agendamentos que estão com status 'em_andamento' e já passou da hora de fim
    $stmt = $pdo->prepare("UPDATE agendamentos 
                        SET status = 'concluido'
                        WHERE status = 'em_andamento' 
                        AND data_fim <= :datetime_atual
                        RETURNING id, data_fim, status");
                        
    $stmt->bindParam(':datetime_atual', $datetime_atual);
    $stmt->execute();
    
    $atualizados_concluidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_concluidos = count($atualizados_concluidos);
    
    if ($total_concluidos > 0) {
        $log .= "\nAgendamentos atualizados para 'Concluído': {$total_concluidos}\n";
        foreach ($atualizados_concluidos as $agenda) {
            $log .= "- ID: {$agenda['id']}, Data/Hora de Fim: {$agenda['data_fim']}\n";
            
            // Buscar dados do agendamento para notificação
            $agendamento_id = $agenda['id'];
            $status_original = 'em_andamento'; // Status antes da mudança (sempre será em_andamento)
            
            $stmt_notificacao = $pdo->prepare("SELECT a.data_agendamento, a.hora_inicio, cl.nome as cliente_nome
                                         FROM agendamentos a
                                         JOIN orcamentos o ON o.id = a.orcamento_id
                                         JOIN clientes cl ON cl.id = o.cliente_id
                                         WHERE a.id = :agendamento_id");
            $stmt_notificacao->bindParam(':agendamento_id', $agendamento_id, PDO::PARAM_INT);
            $stmt_notificacao->execute();
            $dados_notificacao = $stmt_notificacao->fetch(PDO::FETCH_ASSOC);
            
            if ($dados_notificacao) {
                $data_formatada = date('d/m/Y', strtotime($dados_notificacao['data_agendamento']));
                notificarAlteracaoAgendamento(
                    $agendamento_id,
                    'finalizado',
                    $data_formatada,
                    $dados_notificacao['hora_inicio'],
                    $dados_notificacao['cliente_nome'],
                    $status_original
                );
            }
        }
    } else {
        $log .= "\nNenhum agendamento atualizado para 'Concluído'.\n";
    }
    
    $total_atualizados = $total_em_andamento + $total_concluidos;
    
} catch (Exception $e) {
    $log .= "\nERRO: " . $e->getMessage() . "\n";
}

// Verificar se a coluna ultima_atualizacao existe, se não existir, criar
try {
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                         WHERE table_name = 'agendamentos' AND column_name = 'ultima_atualizacao'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // A coluna não existe, vamos criá-la
        $stmt = $pdo->prepare("ALTER TABLE agendamentos ADD COLUMN ultima_atualizacao TIMESTAMP");
        $stmt->execute();
        $log .= "\nColuna 'ultima_atualizacao' adicionada à tabela agendamentos.\n";
    }
} catch (Exception $e) {
    $log .= "\nERRO ao verificar/criar coluna: " . $e->getMessage() . "\n";
}

$log .= "\n====================================================================\n";
$log .= "Total de atualizações realizadas: {$total_atualizados}\n";
$log .= "====================================================================\n";

// Se for chamado via CLI, exibe o log na tela
if (php_sapi_name() === 'cli') {
    echo $log;
} else {
    // Se for chamado via web, formatar o log
    echo '<pre>'.$log.'</pre>';
}

// Opcional: salvar o log em um arquivo
$log_file = 'logs/atualizacao_agendamentos_' . date('Y-m-d') . '.log';
// Verificar se o diretório 'logs' existe, se não, criar
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

// Verificar se o arquivo de log existe, se não, criar
if (!file_exists($log_file)) {
    touch($log_file);
    chmod($log_file, 0644);
}

// Adicionar ao log com horário
file_put_contents($log_file, $log . "\n\n", FILE_APPEND);
?>