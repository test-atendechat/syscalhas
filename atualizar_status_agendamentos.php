<?php
// Script para atualizar automaticamente o status dos agendamentos
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

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
    
    // Buscar agendamentos que estão com status 'agendado' e já chegou a hora de início
    $stmt = $pdo->prepare("UPDATE agendamentos 
                        SET status = 'em_andamento', 
                            ultima_atualizacao = :datetime_atual 
                        WHERE status = 'agendado' 
                        AND data_agendamento = :data_atual 
                        AND hora_inicio <= :hora_atual
                        RETURNING id, hora_inicio");
                        
    $stmt->bindParam(':data_atual', $data_atual);
    $stmt->bindParam(':hora_atual', $hora_atual);
    $stmt->bindParam(':datetime_atual', $datetime_atual);
    $stmt->execute();
    
    $atualizados_em_andamento = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_em_andamento = count($atualizados_em_andamento);
    
    if ($total_em_andamento > 0) {
        $log .= "Agendamentos atualizados para 'Em Andamento': {$total_em_andamento}\n";
        foreach ($atualizados_em_andamento as $agenda) {
            $log .= "- ID: {$agenda['id']}, Hora de Início: {$agenda['hora_inicio']}\n";
        }
    } else {
        $log .= "Nenhum agendamento atualizado para 'Em Andamento'.\n";
    }
    
    // Buscar agendamentos que estão com status 'em_andamento' e já passou da hora de fim
    $stmt = $pdo->prepare("UPDATE agendamentos 
                        SET status = 'concluido', 
                            ultima_atualizacao = :datetime_atual 
                        WHERE status = 'em_andamento' 
                        AND data_agendamento = :data_atual 
                        AND hora_fim <= :hora_atual
                        RETURNING id, hora_fim");
                        
    $stmt->bindParam(':data_atual', $data_atual);
    $stmt->bindParam(':hora_atual', $hora_atual);
    $stmt->bindParam(':datetime_atual', $datetime_atual);
    $stmt->execute();
    
    $atualizados_concluidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_concluidos = count($atualizados_concluidos);
    
    if ($total_concluidos > 0) {
        $log .= "\nAgendamentos atualizados para 'Concluído': {$total_concluidos}\n";
        foreach ($atualizados_concluidos as $agenda) {
            $log .= "- ID: {$agenda['id']}, Hora de Fim: {$agenda['hora_fim']}\n";
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