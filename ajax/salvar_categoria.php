<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/functions.php');

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Capturar e validar dados
$nome = isset($_POST['nome']) ? limpaString($_POST['nome']) : '';

if (empty($nome)) {
    echo json_encode(['success' => false, 'message' => 'Nome da categoria é obrigatório']);
    exit;
}

try {
    // Verificar se já existe uma categoria com este nome
    $stmt = $db->prepare("SELECT COUNT(*) FROM categorias WHERE nome = :nome");
    $stmt->bindParam(':nome', $nome);
    $stmt->execute();
    
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma categoria com este nome']);
        exit;
    }
    
    // Inserir nova categoria
    $stmt = $db->prepare("INSERT INTO categorias (nome) VALUES (:nome)");
    $stmt->bindParam(':nome', $nome);
    $stmt->execute();
    
    $id = $db->lastInsertId();
    
    echo json_encode(['success' => true, 'id' => $id, 'nome' => $nome]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar categoria: ' . $e->getMessage()]);
}
