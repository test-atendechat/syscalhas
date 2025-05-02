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
$status_novo = isset($_POST['status_novo']) ? $_POST['status_novo'] : '';
$instalador_id = isset($_POST['instalador_id']) ? intval($_POST['instalador_id']) : 0;
$data_instalacao = isset($_POST['data_instalacao']) ? $_POST['data_instalacao'] : '';
$hora_instalacao = isset($_POST['hora_instalacao']) ? $_POST['hora_instalacao'] : '';

// Verificar se todos os campos necessários foram enviados
if ($orcamento_id <= 0 || empty($status_novo)) {
    $resposta = array(
        'status' => 'erro',
        'mensagem' => 'Parâmetros incompletos'
    );
    echo json_encode($resposta);
    exit;
}

// Formatar data para o formato SQL (se fornecida)
if (!empty($data_instalacao)) {
    $data_formatada = dataBrParaISO($data_instalacao);
}

try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Obter status atual do orçamento
    $stmt = $pdo->prepare("SELECT a.status as status_atual, a.id as agendamento_id, a.instalador_id as instalador_atual
                          FROM agendamentos a WHERE a.orcamento_id = :orcamento_id");
    $stmt->bindParam(':orcamento_id', $orcamento_id);
    $stmt->execute();
    
    $dados_atuais = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_atual = $dados_atuais ? $dados_atuais['status_atual'] : null;
    $agendamento_id = $dados_atuais ? $dados_atuais['agendamento_id'] : null;
    $instalador_atual = $dados_atuais ? $dados_atuais['instalador_atual'] : null;
    
    // Tratar transição de status
    if ($status_atual == 'orcamento_agendado' && $status_novo == 'agendado') {
        // Validação de dados para instalação
        if ($instalador_id <= 0 || empty($data_instalacao) || empty($hora_instalacao)) {
            $pdo->rollback();
            $resposta = array(
                'status' => 'erro',
                'mensagem' => 'Para agendar instalação é necessário informar o instalador, data e hora.'
            );
            echo json_encode($resposta);
            exit;
        }
        
        // Mudança de orçamento agendado para instalação agendada
        // Excluir o agendamento anterior com o orçamentista
        $stmt_delete = $pdo->prepare("DELETE FROM agendamentos WHERE id = :agendamento_id");
        $stmt_delete->bindParam(':agendamento_id', $agendamento_id);
        $stmt_delete->execute();
        
        // Criar um novo agendamento com o instalador
        $stmt_insert = $pdo->prepare("INSERT INTO agendamentos (orcamento_id, instalador_id, data_agendamento, hora_inicio, status) 
                                     VALUES (:orcamento_id, :instalador_id, :data_agendamento, :hora_inicio, :status)");
        $stmt_insert->bindParam(':orcamento_id', $orcamento_id);
        $stmt_insert->bindParam(':instalador_id', $instalador_id);
        $stmt_insert->bindParam(':data_agendamento', $data_formatada);
        $stmt_insert->bindParam(':hora_inicio', $hora_instalacao);
        $stmt_insert->bindValue(':status', 'instalacao_agendada');
        $stmt_insert->execute();
        
        // Atualizar status do orçamento (já deve estar aprovado)
        $stmt_orcamento = $pdo->prepare("UPDATE orcamentos SET status = 'aprovado' WHERE id = :orcamento_id");
        $stmt_orcamento->bindParam(':orcamento_id', $orcamento_id);
        $stmt_orcamento->execute();
        
        $mensagem = "Orçamento agendado para instalação com sucesso!";
    } 
    else if ($status_atual == 'instalacao_agendada' && $status_novo == 'em_andamento') {
        // Mudança de instalação agendada para instalação em andamento
        // Remover o agendamento da agenda do instalador
        $stmt_delete = $pdo->prepare("DELETE FROM agendamentos WHERE id = :agendamento_id");
        $stmt_delete->bindParam(':agendamento_id', $agendamento_id);
        $stmt_delete->execute();
        
        // Atualizar status do orçamento
        $stmt_orcamento = $pdo->prepare("UPDATE orcamentos SET status = 'em_andamento' WHERE id = :orcamento_id");
        $stmt_orcamento->bindParam(':orcamento_id', $orcamento_id);
        $stmt_orcamento->execute();
        
        $mensagem = "Instalação iniciada com sucesso e removida da agenda!";
    }
    else if ($status_atual == 'em_andamento' && $status_novo == 'finalizado') {
        // Mudança de instalação em andamento para instalação finalizada
        // Verificar se ainda existe algum agendamento
        if ($agendamento_id) {
            // Remover o agendamento completamente
            $stmt_delete = $pdo->prepare("DELETE FROM agendamentos WHERE id = :agendamento_id");
            $stmt_delete->bindParam(':agendamento_id', $agendamento_id);
            $stmt_delete->execute();
        }
        
        // Atualizar status do orçamento
        $stmt_orcamento = $pdo->prepare("UPDATE orcamentos SET status = 'finalizado' WHERE id = :orcamento_id");
        $stmt_orcamento->bindParam(':orcamento_id', $orcamento_id);
        $stmt_orcamento->execute();
        
        $mensagem = "Instalação finalizada com sucesso!";
    }
    else {
        // Outros casos de transição
        $stmt_update = $pdo->prepare("UPDATE agendamentos SET status = :status_novo WHERE id = :agendamento_id");
        $stmt_update->bindParam(':status_novo', $status_novo);
        $stmt_update->bindParam(':agendamento_id', $agendamento_id);
        $stmt_update->execute();
        
        // Atualizar status do orçamento
        $stmt_orcamento = $pdo->prepare("UPDATE orcamentos SET status = :status_novo WHERE id = :orcamento_id");
        $stmt_orcamento->bindParam(':status_novo', $status_novo);
        $stmt_orcamento->bindParam(':orcamento_id', $orcamento_id);
        $stmt_orcamento->execute();
        
        $mensagem = "Status atualizado com sucesso!";
    }
    
    // Commit da transação
    $pdo->commit();
    
    $resposta = array(
        'status' => 'sucesso',
        'mensagem' => $mensagem
    );
} catch (Exception $e) {
    // Rollback em caso de erro
    $pdo->rollback();
    
    $resposta = array(
        'status' => 'erro',
        'mensagem' => 'Erro ao atualizar status: ' . $e->getMessage()
    );
}

// Retornar resposta como JSON
header('Content-Type: application/json');
echo json_encode($resposta);
