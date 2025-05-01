<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/functions.php');
require_once('../includes/auth.php');

// Verificar autenticação via AJAX
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Usuário não autenticado']);
    exit;
}

// Verificar se o ID do orçamento foi enviado
if (!isset($_GET['orcamento_id']) || empty($_GET['orcamento_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'ID do orçamento não fornecido']);
    exit;
}

$orcamento_id = intval($_GET['orcamento_id']);

try {
    // Consultar o tempo previsto do orçamento
    $stmt = $db->prepare("SELECT tempo_previsto_horas FROM orcamentos WHERE id = :id");
    $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => true,
            'tempo_previsto_horas' => $orcamento['tempo_previsto_horas'] ?? 2 // Usar 2 como padrão se não estiver definido
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['erro' => 'Orçamento não encontrado']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Erro ao buscar tempo previsto: ' . $e->getMessage()]);
}
