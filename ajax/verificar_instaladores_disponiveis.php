<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/functions.php');
require_once('../includes/auth.php');

// Definir header como JSON
header('Content-Type: application/json');

// Verificar se o usuário está autenticado
session_start();
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não autenticado',
        'instaladores' => []
    ]);
    exit;
}

// Verificar permissão
require_once('../verificar_permissao.php');
if (!verificarPermissao('gerenciar_agendamentos') && $_SESSION['usuario']['nivel'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Você não tem permissão para acessar este recurso',
        'instaladores' => []
    ]);
    exit;
}

// Verificar se recebeu os dados necessários
$data = isset($_GET['data']) ? $_GET['data'] : null;
$hora_inicio = isset($_GET['hora_inicio']) ? $_GET['hora_inicio'] : null;
$hora_fim = isset($_GET['hora_fim']) ? $_GET['hora_fim'] : null;

if (empty($data) || empty($hora_inicio)) {
    echo json_encode([
        'success' => false,
        'message' => 'Data e hora são obrigatórias',
        'instaladores' => []
    ]);
    exit;
}

// Se não foi informada hora de fim, assume que é 1 hora depois
if (empty($hora_fim)) {
    // Suporta formato HH:MM
    if (strpos($hora_inicio, ':') !== false) {
        $partes = explode(':', $hora_inicio);
        $hora = (int)$partes[0];
        $minutos = (int)$partes[1];
        
        $hora++; // Adiciona 1 hora
        if ($hora > 23) $hora = 23; // Limita a 23h
        
        $hora_fim = sprintf("%02d:%02d", $hora, $minutos);
    } else {
        // Caso não tenha formato válido
        $hora_fim = "18:00";
    }
}

// Buscar instaladores disponíveis para esta data e horário
try {
    // 1. Buscar todos os instaladores ativos
    $stmt = $db->prepare("SELECT id, nome, telefone, auxiliar_id FROM instaladores WHERE ativo = TRUE ORDER BY nome");
    $stmt->execute();
    $todos_instaladores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Buscar instaladores já agendados para esta data e horário
    $sql = "SELECT DISTINCT ai.instalador_id 
            FROM agendamentos a
            INNER JOIN agendamento_instaladores ai ON a.id = ai.agendamento_id
            WHERE a.data_agendamento = :data 
            AND a.status NOT IN ('cancelado')
            AND (
                (a.hora_inicio <= :hora_inicio AND a.hora_fim > :hora_inicio) OR
                (a.hora_inicio < :hora_fim AND a.hora_fim >= :hora_fim) OR
                (a.hora_inicio >= :hora_inicio AND a.hora_fim <= :hora_fim)
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':hora_inicio', $hora_inicio);
    $stmt->bindParam(':hora_fim', $hora_fim);
    $stmt->execute();
    
    $indisponiveis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 3. Verificar indisponibilidades cadastradas
    $sql = "SELECT instalador_id FROM indisponibilidades 
            WHERE data = :data 
            AND (
                (hora_inicio <= :hora_inicio AND hora_fim > :hora_inicio) OR
                (hora_inicio < :hora_fim AND hora_fim >= :hora_fim) OR
                (hora_inicio >= :hora_inicio AND hora_fim <= :hora_fim)
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':data', $data);
    $stmt->bindParam(':hora_inicio', $hora_inicio);
    $stmt->bindParam(':hora_fim', $hora_fim);
    $stmt->execute();
    
    $indisponiveis_cadastrados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Combinar todos os indisponíveis
    $todos_indisponiveis = array_unique(array_merge($indisponiveis, $indisponiveis_cadastrados));
    
    // Filtrar instaladores disponíveis
    $instaladores_disponiveis = [];
    foreach ($todos_instaladores as $instalador) {
        if (!in_array($instalador['id'], $todos_indisponiveis)) {
            $instaladores_disponiveis[] = [
                'id' => $instalador['id'],
                'nome' => $instalador['nome'],
                'telefone' => $instalador['telefone']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => count($instaladores_disponiveis) > 0 ? 'Instaladores disponíveis encontrados' : 'Nenhum instalador disponível para este horário',
        'instaladores' => $instaladores_disponiveis,
        'data' => $data,
        'hora_inicio' => $hora_inicio,
        'hora_fim' => $hora_fim
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar instaladores: ' . $e->getMessage(),
        'instaladores' => []
    ]);
}
