<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/functions.php');

// Inicializar resposta JSON
header('Content-Type: application/json');
$resposta = ['status' => 'erro', 'mensagem' => '', 'colaboradores' => []];

// Verificar se os dados foram enviados
$data = isset($_GET['data']) ? $_GET['data'] : '';
$hora = isset($_GET['hora']) ? $_GET['hora'] : '';
$tempo_previsto = isset($_GET['tempo_previsto']) ? (int)$_GET['tempo_previsto'] : 60;
$unidade_tempo = isset($_GET['unidade_tempo']) ? $_GET['unidade_tempo'] : 'minutos';

// Validar dados recebidos
if (empty($data) || empty($hora)) {
    $resposta['mensagem'] = 'Data e horário são obrigatórios';
    echo json_encode($resposta);
    exit;
}

// Obter configurações de horário de funcionamento
$stmt = $db->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_inicio', 'horario_fim', 'dias_funcionamento')");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Valores padrão caso não existam configurações
$horario_inicio = isset($config['horario_inicio']) ? $config['horario_inicio'] : '07:00';
$horario_fim = isset($config['horario_fim']) ? $config['horario_fim'] : '17:00';
$dias_funcionamento = isset($config['dias_funcionamento']) ? explode(',', $config['dias_funcionamento']) : [1, 2, 3, 4, 5]; // Padrão: Segunda a Sexta

try {
    // Calcular data e hora de início
    $data_inicio = $data . ' ' . $hora . ':00';
    $data_hora_inicio = new DateTime($data_inicio);
    
    // Converter tempo previsto para minutos
    $duracao_minutos = $tempo_previsto;
    if ($unidade_tempo === 'horas') {
        $duracao_minutos = $tempo_previsto * 60;
    } else if ($unidade_tempo === 'dias') {
        $duracao_minutos = $tempo_previsto * 60 * 8; // 8 horas por dia
    }
    
    // Calcular data e hora de término
    $data_hora_fim = clone $data_hora_inicio;
    $data_hora_fim->add(new DateInterval('PT' . $duracao_minutos . 'M'));
    
    // Verificar se a data é um dia de funcionamento
    $dia_semana = date('N', strtotime($data)); // Retorna 1 (segunda) a 7 (domingo)
    
    if (!in_array($dia_semana, $dias_funcionamento)) {
        $resposta['mensagem'] = 'O dia selecionado não é um dia de funcionamento.';
        echo json_encode($resposta);
        exit;
    }
    
    // Verificar se está dentro do horário de funcionamento
    $hora_inicio_expediente = new DateTime($data . ' ' . $horario_inicio);
    $hora_fim_expediente = new DateTime($data . ' ' . $horario_fim);
    
    // Se a hora de início for anterior ao expediente ou se a hora do fim for posterior ao expediente
    if ($data_hora_inicio < $hora_inicio_expediente || $data_hora_fim > $hora_fim_expediente) {
        $resposta['mensagem'] = 'O horário selecionado está fora do horário de funcionamento (' . 
                                $horario_inicio . ' - ' . $horario_fim . ').';
        echo json_encode($resposta);
        exit;
    }
    
    // Verificar se a tabela agendamentos tem registros
    $resultado = $db->query("SELECT COUNT(*) FROM agendamentos");
    $tem_agendamentos = ($resultado->fetchColumn() > 0);
    
    $colaboradores_ocupados = [];
    
    // 1. Verificar colaboradores com agendamentos neste horário
    if ($tem_agendamentos) {
        // Verificar colunas na tabela
        $stmt_colunas = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'agendamentos'");
        $colunas = $stmt_colunas->fetchAll(PDO::FETCH_COLUMN);
        
        // Verificar se existem as colunas data_inicio e data_fim
        if (in_array('data_inicio', $colunas) && in_array('data_fim', $colunas)) {
            $stmt = $db->prepare("SELECT DISTINCT colaborador_id FROM agendamentos 
                             WHERE status = 'agendado' 
                             AND ((data_inicio <= :data_inicio AND data_fim >= :data_inicio) 
                             OR (data_inicio <= :data_fim AND data_fim >= :data_fim) 
                             OR (data_inicio >= :data_inicio AND data_fim <= :data_fim))");
            $stmt->bindParam(':data_inicio', $data_hora_inicio->format('Y-m-d H:i:s'));
            $stmt->bindParam(':data_fim', $data_hora_fim->format('Y-m-d H:i:s'));
            $stmt->execute();
            
            $colaboradores_ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // Alternativa usando data_agendamento e hora_inicio/hora_fim
            $data_apenas = $data_hora_inicio->format('Y-m-d');
            $hora_inicio_apenas = $data_hora_inicio->format('H:i:s');
            $hora_fim_apenas = $data_hora_fim->format('H:i:s');
            
            $stmt = $db->prepare("SELECT DISTINCT instalador_id FROM agendamentos 
                             WHERE status = 'agendado' AND data_agendamento = :data_agendamento 
                             AND ((hora_inicio <= :hora_inicio AND hora_fim >= :hora_inicio) 
                             OR (hora_inicio <= :hora_fim AND hora_fim >= :hora_fim) 
                             OR (hora_inicio >= :hora_inicio AND hora_fim <= :hora_fim))");
            $stmt->bindParam(':data_agendamento', $data_apenas);
            $stmt->bindParam(':hora_inicio', $hora_inicio_apenas);
            $stmt->bindParam(':hora_fim', $hora_fim_apenas);
            $stmt->execute();
            
            $colaboradores_ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    
    // 2. Verificar colaboradores com indisponibilidades registradas
    // Tabela colaborador_indisponibilidade contém: colaborador_id, data_inicio, data_fim, motivo
    $data_apenas = $data_hora_inicio->format('Y-m-d');
    // Verificar se a tabela existe antes de consultar
    $stmt_check = $db->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'colaborador_indisponibilidade')");
    $tabela_existe = $stmt_check->fetchColumn();
    
    if ($tabela_existe) {
        // Verificar indisponibilidades para o dia e horário
        $stmt_indisponibilidade = $db->prepare("SELECT DISTINCT colaborador_id FROM colaborador_indisponibilidade 
                                       WHERE (data_inicio <= :data_inicio AND data_fim >= :data_inicio) 
                                       OR (data_inicio <= :data_fim AND data_fim >= :data_fim) 
                                       OR (data_inicio >= :data_inicio AND data_fim <= :data_fim)");
        $stmt_indisponibilidade->bindParam(':data_inicio', $data_hora_inicio->format('Y-m-d H:i:s'));
        $stmt_indisponibilidade->bindParam(':data_fim', $data_hora_fim->format('Y-m-d H:i:s'));
        $stmt_indisponibilidade->execute();
        
        // Adicionar colaboradores indisponíveis ao array de ocupados
        $indisponiveis = $stmt_indisponibilidade->fetchAll(PDO::FETCH_COLUMN);
        $colaboradores_ocupados = array_merge($colaboradores_ocupados, $indisponiveis);
        
        // Remover duplicatas
        $colaboradores_ocupados = array_unique($colaboradores_ocupados);
    }
    
    // Buscar colaboradores disponíveis (instaladores)
    $sql = "SELECT id, nome FROM colaboradores WHERE tipo = 'instalador' ORDER BY nome";
    
    // Se há colaboradores ocupados, excluí-los da busca
    if (!empty($colaboradores_ocupados)) {
        $sql .= " AND id NOT IN (" . implode(',', $colaboradores_ocupados) . ")";
    }
    
    $stmt = $db->query($sql);
    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retornar resultado
    $resposta = [
        'status' => 'sucesso',
        'colaboradores' => $colaboradores
    ];
    
} catch (Exception $e) {
    $resposta['mensagem'] = 'Erro ao verificar disponibilidade: ' . $e->getMessage();
}

echo json_encode($resposta);
