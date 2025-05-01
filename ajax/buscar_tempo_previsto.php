<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/auth.php');

// Definir header como JSON
header('Content-Type: application/json');

// Verificar se usuário está autenticado
session_start();
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario']['id'])) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Usuário não autenticado',
        'tempo_previsto_horas' => 0
    ]);
    exit;
}

// Verificar se recebeu ID do orçamento
$orcamento_id = isset($_GET['orcamento_id']) ? intval($_GET['orcamento_id']) : 0;

if ($orcamento_id <= 0) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'ID do orçamento inválido',
        'tempo_previsto_horas' => 0
    ]);
    exit;
}

// Buscar o tempo previsto do orçamento
try {
    $stmt = $db->prepare("SELECT tempo_previsto_horas FROM orcamentos WHERE id = :id");
    $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
        $tempo_previsto = isset($orcamento['tempo_previsto_horas']) ? floatval($orcamento['tempo_previsto_horas']) : 0;
        
        // Se não tiver tempo previsto, definir um padrão
        if ($tempo_previsto <= 0) {
            $tempo_previsto = 2; // Padrão de 2 horas
        }
        
        echo json_encode([
            'sucesso' => true,
            'tempo_previsto_horas' => $tempo_previsto,
            'mensagem' => 'Tempo previsto: ' . $tempo_previsto . ' hora(s)'
        ]);
    } else {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Orçamento não encontrado',
            'tempo_previsto_horas' => 2 // Padrão de 2 horas
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar tempo previsto: ' . $e->getMessage(),
        'tempo_previsto_horas' => 2 // Padrão de 2 horas em caso de erro
    ]);
}
