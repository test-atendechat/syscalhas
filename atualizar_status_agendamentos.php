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
    
    // ETAPA 1: Identificar agendamentos que devem mudar para status 'em_andamento'
    // Consulta para encontrar os agendamentos que já chegou a hora de início
    $stmt = $pdo->prepare("SELECT id, data_inicio, status, orcamento_id, instalador_id 
                        FROM agendamentos
                        WHERE (status = 'orcamento_agendado' OR status = 'instalacao_agendada') 
                        AND data_inicio <= :datetime_atual 
                        AND data_fim >= :datetime_atual");
                        
    $stmt->bindParam(':datetime_atual', $datetime_atual);
    $stmt->execute();
    
    $agendamentos_para_iniciar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_em_andamento = count($agendamentos_para_iniciar);
    
    // ETAPA 2: Atualizar orçamentos e remover agendamentos
    foreach ($agendamentos_para_iniciar as $agenda) {
        // Atualizar status do orçamento para 'em_andamento'
        $stmt_update_orcamento = $pdo->prepare("UPDATE orcamentos 
                                            SET status_execucao = 'andamento' 
                                            WHERE id = :orcamento_id");
        $stmt_update_orcamento->bindParam(':orcamento_id', $agenda['orcamento_id'], PDO::PARAM_INT);
        $stmt_update_orcamento->execute();
        
        // Excluir o agendamento da tabela agendamentos
        $stmt_delete_agendamento = $pdo->prepare("DELETE FROM agendamentos WHERE id = :agendamento_id");
        $stmt_delete_agendamento->bindParam(':agendamento_id', $agenda['id'], PDO::PARAM_INT);
        $stmt_delete_agendamento->execute();
    }
    
    if ($total_em_andamento > 0) {
        $log .= "Agendamentos atualizados para 'Em Andamento': {$total_em_andamento}\n";
        foreach ($agendamentos_para_iniciar as $agenda) {
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
    
    // ETAPA 3: Identificar agendamentos que devem ser concluídos
    // Consulta para encontrar agendamentos onde já passou da hora de fim
    $stmt = $pdo->prepare("SELECT id, data_fim, status, orcamento_id 
                        FROM agendamentos
                        WHERE status = 'em_andamento' 
                        AND data_fim <= :datetime_atual");
                        
    $stmt->bindParam(':datetime_atual', $datetime_atual);
    $stmt->execute();
    
    $agendamentos_para_concluir = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_concluidos = count($agendamentos_para_concluir);
    
    // ETAPA 4: Atualizar orçamentos e remover agendamentos concluídos
    foreach ($agendamentos_para_concluir as $agenda) {
        // Atualizar status do orçamento para 'finalizado'
        $stmt_update_orcamento = $pdo->prepare("UPDATE orcamentos 
                                            SET status_execucao = 'finalizado' 
                                            WHERE id = :orcamento_id");
        $stmt_update_orcamento->bindParam(':orcamento_id', $agenda['orcamento_id'], PDO::PARAM_INT);
        $stmt_update_orcamento->execute();
        
        // Excluir o agendamento da tabela agendamentos
        $stmt_delete_agendamento = $pdo->prepare("DELETE FROM agendamentos WHERE id = :agendamento_id");
        $stmt_delete_agendamento->bindParam(':agendamento_id', $agenda['id'], PDO::PARAM_INT);
        $stmt_delete_agendamento->execute();
    }
    
    if ($total_concluidos > 0) {
        $log .= "\nAgendamentos atualizados para 'Concluído': {$total_concluidos}\n";
        foreach ($agendamentos_para_concluir as $agenda) {
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