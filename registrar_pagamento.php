<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Inicialização de variáveis
$erro = '';
$sucesso = '';

// Processar formulário de pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Capturar dados do formulário
        $venda_id = isset($_POST['venda_id']) ? intval($_POST['venda_id']) : 0;
        $venda_numero = isset($_POST['venda_numero']) ? $_POST['venda_numero'] : '';
        $valor_total = isset($_POST['valor_total']) ? floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_total'])) : 0;
        $valor_pago = isset($_POST['valor_pago']) ? floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_pago'])) : 0;
        $forma_pagamento = isset($_POST['forma_pagamento']) ? $_POST['forma_pagamento'] : '';
        $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        
        // Validar dados
        if ($venda_id <= 0) {
            throw new Exception("ID da venda inválido.");
        }
        
        if ($valor_pago <= 0) {
            throw new Exception("Valor pago deve ser maior que zero.");
        }
        
        if (empty($forma_pagamento)) {
            throw new Exception("Forma de pagamento não informada.");
        }
        
        // Iniciar transação
        $db->beginTransaction();
        
        // Verificar se a venda existe e está pendente
        $stmt = $db->prepare("SELECT * FROM vendas WHERE id = :id");
        $stmt->bindParam(':id', $venda_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            throw new Exception("Venda não encontrada.");
        }
        
        $venda = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Determinar status de pagamento
        $status_pagamento = ($valor_pago >= $valor_total) ? 'pago_total' : 'pago_parcial';
        $data_pagamento = date('Y-m-d');
        
        // Atualizar status de pagamento da venda
        $stmt = $db->prepare("UPDATE vendas SET 
                            status_pagamento = :status_pagamento, 
                            data_pagamento = :data_pagamento 
                            WHERE id = :id");
        $stmt->bindParam(':status_pagamento', $status_pagamento);
        $stmt->bindParam(':data_pagamento', $data_pagamento);
        $stmt->bindParam(':id', $venda_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registrar movimentação no caixa
        $stmt = $db->prepare("INSERT INTO caixa 
                        (data_operacao, tipo, descricao, valor, forma_pagamento, usuario_id, observacoes, venda_id)
                        VALUES 
                        (:data_operacao, :tipo, :descricao, :valor, :forma_pagamento, :usuario_id, :observacoes, :venda_id)");
        $stmt->bindParam(':data_operacao', $data_pagamento);
        $tipo = 'entrada';
        $stmt->bindParam(':tipo', $tipo);
        $descricao = "Pagamento da venda #{$venda_numero}";
        $stmt->bindParam(':descricao', $descricao);
        $stmt->bindParam(':valor', $valor_pago);
        $stmt->bindParam(':forma_pagamento', $forma_pagamento);
        $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
        $observacoes = ($status_pagamento == 'pago_total') ? "Pagamento total" : "Pagamento parcial";
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':venda_id', $venda_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Finalizar transação
        $db->commit();
        
        // Redirecionar para a visualização da venda com mensagem de sucesso
        header("Location: venda_visualizar.php?id={$venda_id}&mensagem=pagamento_registrado");
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $erro = 'Erro ao registrar pagamento: ' . $e->getMessage();
        
        // Redirecionar com mensagem de erro
        header("Location: venda_visualizar.php?id={$venda_id}&erro=" . urlencode($erro));
        exit;
    }
} else {
    // Acesso direto à página sem POST, redirecionar para lista de vendas
    header('Location: vendas.php');
    exit;
}
