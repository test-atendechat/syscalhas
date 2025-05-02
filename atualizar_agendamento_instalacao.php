<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão
if (!verificarPermissao('permissao_orcamentos_editar')) {
    header('Location: dashboard.php?erro=' . urlencode('Você não tem permissão para acessar este recurso.'));
    exit;
}

// Receber parâmetros
$orcamento_id = isset($_POST['orcamento_id']) ? intval($_POST['orcamento_id']) : 0;
$instalador_id = isset($_POST['instalador_id']) ? intval($_POST['instalador_id']) : 0;
$data_instalacao = isset($_POST['data_instalacao']) ? $_POST['data_instalacao'] : '';
$hora_instalacao = isset($_POST['hora_instalacao']) ? $_POST['hora_instalacao'] : '';
$forma_pgto = isset($_POST['forma_pgto']) ? $_POST['forma_pgto'] : '';

// Verificar se todos os campos necessários foram enviados
if ($orcamento_id <= 0 || $instalador_id <= 0 || empty($data_instalacao) || empty($hora_instalacao)) {
    $resposta = array(
        'status' => 'erro',
        'mensagem' => 'Todos os campos são obrigatórios'
    );
    echo json_encode($resposta);
    exit;
}

// Formatar data para o formato SQL
$data_formatada = dataBrParaISO($data_instalacao);

// Verificar se já existe um agendamento para este orçamento
try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Verificar se já existe um agendamento para este orçamento
    $stmt = $pdo->prepare("SELECT id, status FROM agendamentos WHERE orcamento_id = :orcamento_id");
    $stmt->bindParam(':orcamento_id', $orcamento_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Existe um agendamento, vamos atualizar
        $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se o status anterior era 'orcamento_agendado', devemos mudar para 'instalacao_agendada'
        if ($agendamento['status'] == 'orcamento_agendado') {
            // Atualizar o agendamento existente com o novo instalador e status
            $sql_update = "UPDATE agendamentos SET 
                       instalador_id = :instalador_id,
                       data_agendamento = :data_agendamento,
                       hora_inicio = :hora_inicio,
                       status = 'agendado'
                       WHERE id = :id";
                       
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':instalador_id', $instalador_id);
            $stmt_update->bindParam(':data_agendamento', $data_formatada);
            $stmt_update->bindParam(':hora_inicio', $hora_instalacao);
            $stmt_update->bindParam(':id', $agendamento['id']);
            $stmt_update->execute();
            
            // Atualizar status do orçamento
            $stmt_orcamento = $pdo->prepare("UPDATE orcamentos SET status = 'agendado', forma_pagamento = :forma_pgto WHERE id = :orcamento_id");
            $stmt_orcamento->bindParam(':forma_pgto', $forma_pgto);
            $stmt_orcamento->bindParam(':orcamento_id', $orcamento_id);
            $stmt_orcamento->execute();
            
            // Commit da transação
            $pdo->commit();
            
            $resposta = array(
                'status' => 'sucesso',
                'mensagem' => 'O orçamento foi agendado para instalação com sucesso!',
                'agendamento_id' => $agendamento['id']
            );
        } else {
            // Já estava em um status diferente de orçamento_agendado, apenas atualizamos
            $sql_update = "UPDATE agendamentos SET 
                       instalador_id = :instalador_id,
                       data_agendamento = :data_agendamento,
                       hora_inicio = :hora_inicio
                       WHERE id = :id";
                       
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->bindParam(':instalador_id', $instalador_id);
            $stmt_update->bindParam(':data_agendamento', $data_formatada);
            $stmt_update->bindParam(':hora_inicio', $hora_instalacao);
            $stmt_update->bindParam(':id', $agendamento['id']);
            $stmt_update->execute();
            
            // Atualizar status do orçamento se necessário
            if ($forma_pgto) {
                $stmt_orcamento = $pdo->prepare("UPDATE orcamentos SET forma_pagamento = :forma_pgto WHERE id = :orcamento_id");
                $stmt_orcamento->bindParam(':forma_pgto', $forma_pgto);
                $stmt_orcamento->bindParam(':orcamento_id', $orcamento_id);
                $stmt_orcamento->execute();
            }
            
            // Commit da transação
            $pdo->commit();
            
            $resposta = array(
                'status' => 'sucesso',
                'mensagem' => 'O agendamento de instalação foi atualizado com sucesso!',
                'agendamento_id' => $agendamento['id']
            );
        }
    } else {
        // Não existe agendamento, criar novo
        $sql_insert = "INSERT INTO agendamentos (orcamento_id, instalador_id, data_agendamento, hora_inicio, status)
                      VALUES (:orcamento_id, :instalador_id, :data_agendamento, :hora_inicio, 'agendado')";
                      
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->bindParam(':orcamento_id', $orcamento_id);
        $stmt_insert->bindParam(':instalador_id', $instalador_id);
        $stmt_insert->bindParam(':data_agendamento', $data_formatada);
        $stmt_insert->bindParam(':hora_inicio', $hora_instalacao);
        $stmt_insert->execute();
        
        $agendamento_id = $pdo->lastInsertId();
        
        // Atualizar status do orçamento
        $stmt_orcamento = $pdo->prepare("UPDATE orcamentos SET status = 'agendado', forma_pagamento = :forma_pgto WHERE id = :orcamento_id");
        $stmt_orcamento->bindParam(':forma_pgto', $forma_pgto);
        $stmt_orcamento->bindParam(':orcamento_id', $orcamento_id);
        $stmt_orcamento->execute();
        
        // Commit da transação
        $pdo->commit();
        
        $resposta = array(
            'status' => 'sucesso',
            'mensagem' => 'O orçamento foi agendado para instalação com sucesso!',
            'agendamento_id' => $agendamento_id
        );
    }
} catch (Exception $e) {
    // Rollback em caso de erro
    $pdo->rollback();
    
    $resposta = array(
        'status' => 'erro',
        'mensagem' => 'Erro ao agendar instalação: ' . $e->getMessage()
    );
}

// Retornar resposta como JSON
header('Content-Type: application/json');
echo json_encode($resposta);
