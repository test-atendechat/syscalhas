<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/functions.php');

header('Content-Type: application/json');

// Verificar parâmetros
if (!isset($_GET['data']) || !isset($_GET['hora']) || !isset($_GET['tempo_previsto'])) {
    echo json_encode(['erro' => 'Parâmetros incompletos']);
    exit;
}

$data = $_GET['data'];
$hora_inicio = $_GET['hora'];
$tempo_previsto = floatval($_GET['tempo_previsto']);

// Calcular hora de fim
$hora_fim_timestamp = strtotime("+{$tempo_previsto} hours", strtotime("{$data} {$hora_inicio}"));
$hora_fim = date('H:i', $hora_fim_timestamp);

// Obter dia da semana
$dia_semana = date('w', strtotime($data));

// Verificar se o horário está disponível
$stmt = $db->prepare("SELECT * FROM horarios_disponiveis 
                    WHERE dia_semana = :dia_semana 
                    AND hora_inicio = :hora_inicio
                    AND disponivel = true");
$stmt->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
$stmt->bindParam(':hora_inicio', $hora_inicio);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    echo json_encode(['disponiveis' => [], 'mensagem' => 'Horário indisponível']);
    exit;
}

// Verificar se não existe indisponibilidade para esta data
$stmt = $db->prepare("SELECT * FROM indisponibilidades 
                    WHERE :data BETWEEN data_inicio AND data_fim");
$stmt->bindParam(':data', $data);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $indisponibilidade = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['disponiveis' => [], 'mensagem' => "Data bloqueada: {$indisponibilidade['motivo']}"]);
    exit;
}

// Buscar instaladores que não estão ocupados nesta data/horário
$sql = "SELECT i.id, i.nome, i.especialidade 
        FROM instaladores i
        WHERE i.ativo = true 
        AND i.id NOT IN (
            -- Instaladores já agendados para a mesma data e hora
            SELECT ai.instalador_id
            FROM agendamentos a
            JOIN agendamento_instaladores ai ON a.id = ai.agendamento_id
            WHERE a.data_agendamento = :data
            AND a.hora_inicio = :hora_inicio
            AND a.status NOT IN ('cancelado', 'reagendado')
        )
        ORDER BY i.nome ASC";

$stmt = $db->prepare($sql);
$stmt->bindParam(':data', $data);
$stmt->bindParam(':hora_inicio', $hora_inicio);
$stmt->execute();

$instaladores = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['disponiveis' => $instaladores]);
