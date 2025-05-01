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
    
    // Buscar colaboradores ocupados neste horário
    $stmt = $db->prepare("SELECT DISTINCT colaborador_id FROM agendamentos 
                         WHERE status = 'agendado' 
                         AND ((data_inicio <= :data_inicio AND data_fim >= :data_inicio) 
                         OR (data_inicio <= :data_fim AND data_fim >= :data_fim) 
                         OR (data_inicio >= :data_inicio AND data_fim <= :data_fim))");
    $stmt->bindParam(':data_inicio', $data_hora_inicio->format('Y-m-d H:i:s'));
    $stmt->bindParam(':data_fim', $data_hora_fim->format('Y-m-d H:i:s'));
    $stmt->execute();
    
    $colaboradores_ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
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
