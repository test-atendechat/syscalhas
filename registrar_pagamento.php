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
        
        // Verificar pagamentos já realizados para a venda
        $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa WHERE venda_id = :venda_id AND tipo = 'entrada'");
        $stmt->bindParam(':venda_id', $venda_id, PDO::PARAM_INT);
        $stmt->execute();
        $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_pago_anterior = floatval($pagamentos['total_pago'] ?? 0);
        
        // Calcular o valor restante a pagar
        $valor_restante = max(0, $valor_total - $total_pago_anterior);
        
        // Verificar se o valor pago é maior que o valor restante a pagar
        // Sem margem de tolerância
        if ($valor_pago > $valor_restante) {
            throw new Exception("O valor pago (R$ " . number_format($valor_pago, 2, ',', '.') . ") é maior que o valor restante a pagar (R$ " . number_format($valor_restante, 2, ',', '.') . ").");
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
        
        // Total pago já foi calculado anteriormente
        $total_pago = $total_pago_anterior + $valor_pago;
        
        // Comparar valores de forma precisa
        // Considerar como pago total se o valor restante for zero ou menor que R$ 0,01
        // ou se o valor pago for igual ao valor total
        $condicao1 = ($valor_restante - $valor_pago < 0.01);
        $condicao2 = (abs($total_pago - $valor_total) < 0.01);
        $valor_diferenca = abs($total_pago - $valor_total);
        
        $mensagem_debug = "Valor total: {$valor_total}, Total pago anterior: {$total_pago_anterior}, " .
                         "Valor pago agora: {$valor_pago}, Total pago: {$total_pago}, " .
                         "Valor restante após pagamento: " . max(0, $valor_total - $total_pago) . ", " .
                         "Diferença com total: {$valor_diferenca}, Condição 1: " . ($condicao1 ? 'true' : 'false') . ", " .
                         "Condição 2: " . ($condicao2 ? 'true' : 'false');
        
        // Adicionar mensagem de debug no log
        error_log($mensagem_debug);
        
        $pagamento_total = $condicao1 || $condicao2 || ($total_pago >= $valor_total);
        
        // Definir status de pagamento
        $status_pagamento = $pagamento_total ? 'pago_total' : 'pago_parcial';
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
        $observacoes = ($status_pagamento == 'pago_total') ? 
            "Pagamento total - Valor pago anteriormente: R$ " . number_format($total_pago_anterior, 2, ',', '.') . 
            ", Pagamento atual: R$ " . number_format($valor_pago, 2, ',', '.') . 
            ", Total pago: R$ " . number_format($total_pago, 2, ',', '.') : 
            "Pagamento parcial - Valor pago anteriormente: R$ " . number_format($total_pago_anterior, 2, ',', '.') . 
            ", Pagamento atual: R$ " . number_format($valor_pago, 2, ',', '.') . 
            ", Valor restante: R$ " . number_format(max(0, $valor_total - $total_pago), 2, ',', '.');
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
