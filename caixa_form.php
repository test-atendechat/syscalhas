<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Inicialização de variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$orcamento_id = isset($_GET['orcamento_id']) ? intval($_GET['orcamento_id']) : 0;
$orcamento = null;
$cliente = null;

$movimentacao = [
    'id' => 0,
    'data_operacao' => date('Y-m-d'),
    'tipo' => 'entrada',
    'descricao' => '',
    'valor' => '',
    'forma_pagamento' => 'dinheiro',
    'orcamento_id' => null,
    'cliente_id' => null,
    'observacoes' => '',
    'is_fechamento' => false
];
$erro = '';
$sucesso = '';
$titulo = 'Registrar Nova Movimentação';
$modo = 'cadastrar';

// Se for uma movimentação relacionada a um orçamento
if ($orcamento_id > 0) {
    require_once('includes/db.php');
    $stmt = $db->prepare("SELECT o.*, c.nome as cliente_nome, c.id as cliente_id 
                        FROM orcamentos o
                        JOIN clientes c ON o.cliente_id = c.id
                        WHERE o.id = :id");
    $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se o orçamento já estiver pago, redirecionar
        if ($orcamento['status_pagamento'] == 'pago_total') {
            header("Location: orcamento_visualizar.php?id={$orcamento_id}&mensagem=pago");
            exit;
        }
        
        // Buscar pagamentos anteriores
        $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa 
                          WHERE orcamento_id = :orcamento_id AND tipo = 'entrada'");
        $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
        $stmt->execute();
        $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_pago = floatval($pagamentos['total_pago'] ?? 0);
        
        // Calcular valor restante para pagamento
        $valor_restante = $orcamento['valor_total'] - $total_pago;
        if ($valor_restante < 0) $valor_restante = 0; // Evitar valores negativos
        
        // Preencher movimentação com dados do orçamento
        $movimentacao['descricao'] = "Pagamento do orçamento #{$orcamento['numero']}";
        $movimentacao['valor'] = $valor_restante;
        $movimentacao['orcamento_id'] = $orcamento_id;
        $movimentacao['forma_pagamento'] = $orcamento['forma_pagamento'];
        $movimentacao['valor_total_orcamento'] = $orcamento['valor_total'];
        $movimentacao['total_ja_pago'] = $total_pago;
        $titulo = 'Registrar Pagamento de Orçamento';
    } else {
        $erro = 'Orçamento não encontrado.';
    }
}

// Se for edição, buscar dados da movimentação
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM caixa WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $movimentacao = $stmt->fetch(PDO::FETCH_ASSOC);
        $titulo = 'Editar Movimentação';
        $modo = 'editar';
    } else {
        $erro = 'Movimentação não encontrada.';
    }
}

// Verificar se é um fechamento de caixa
if (isset($_GET['fechamento']) && $_GET['fechamento'] == '1') {
    $titulo = 'Fechamento de Caixa Diário';
    $movimentacao['is_fechamento'] = true;
    $movimentacao['tipo'] = 'saida';
    
    // Obter totais do dia atual para cada forma de pagamento
    $sql_totais = "SELECT 
                    forma_pagamento,
                    SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as total_entradas,
                    SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as total_saidas
                  FROM caixa 
                  WHERE DATE(data_operacao) = CURRENT_DATE
                  GROUP BY forma_pagamento";
    $stmt = $db->prepare($sql_totais);
    $stmt->execute();
    $totais_por_forma = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular saldo total do dia
    $total_entradas = 0;
    $total_saidas = 0;
    $saldo_dinheiro = 0;
    $saldo_cartao_credito = 0;
    $saldo_cartao_debito = 0;
    $saldo_pix = 0;
    $saldo_outros = 0;
    
    foreach ($totais_por_forma as $total) {
        $forma = $total['forma_pagamento'];
        $entradas = floatval($total['total_entradas']);
        $saidas = floatval($total['total_saidas']);
        $saldo = $entradas - $saidas;
        
        $total_entradas += $entradas;
        $total_saidas += $saidas;
        
        switch ($forma) {
            case 'dinheiro':
                $saldo_dinheiro = $saldo;
                break;
            case 'cartao_credito':
                $saldo_cartao_credito = $saldo;
                break;
            case 'cartao_debito':
                $saldo_cartao_debito = $saldo;
                break;
            case 'pix':
                $saldo_pix = $saldo;
                break;
            case 'outros':
                $saldo_outros = $saldo;
                break;
        }
    }
    
    $saldo_total = $total_entradas - $total_saidas;
    
    // Preencher valores iniciais para o fechamento
    $movimentacao['descricao'] = 'Fechamento de Caixa - ' . date('d/m/Y');
    $movimentacao['valor'] = $saldo_dinheiro > 0 ? $saldo_dinheiro : 0;
    $movimentacao['forma_pagamento'] = 'dinheiro';
    $movimentacao['observacoes'] = "Fechamento do caixa:\n";
    $movimentacao['observacoes'] .= "- Dinheiro: R$ " . number_format($saldo_dinheiro, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "- Cartão de Crédito: R$ " . number_format($saldo_cartao_credito, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "- Cartão de Débito: R$ " . number_format($saldo_cartao_debito, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "- PIX: R$ " . number_format($saldo_pix, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "- Outros: R$ " . number_format($saldo_outros, 2, ',', '.') . "\n";
    $movimentacao['observacoes'] .= "\nTotal do dia: R$ " . number_format($saldo_total, 2, ',', '.');
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capturar dados do formulário
    $movimentacao = [
        'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
        'data_operacao' => $_POST['data_operacao'] ?? date('Y-m-d'),
        'tipo' => $_POST['tipo'] ?? 'entrada',
        'descricao' => limpaString($_POST['descricao'] ?? ''),
        'valor' => str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0')),
        'forma_pagamento' => $_POST['forma_pagamento'] ?? 'dinheiro',
        'orcamento_id' => !empty($_POST['orcamento_id']) ? intval($_POST['orcamento_id']) : null,
        'cliente_id' => !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : null,
        'observacoes' => limpaString($_POST['observacoes'] ?? '')
    ];
    
    // Validações
    if (empty($movimentacao['descricao'])) {
        $erro = 'A descrição é obrigatória.';
    } elseif (empty($movimentacao['valor']) || !is_numeric($movimentacao['valor']) || $movimentacao['valor'] <= 0) {
        $erro = 'O valor é obrigatório e deve ser um número válido maior que zero.';
    } elseif ($movimentacao['orcamento_id'] && $movimentacao['tipo'] == 'entrada') {
        // Verificar se o pagamento ultrapassa o valor total do orçamento
        
        // Buscar valor do orçamento
        $stmt = $db->prepare("SELECT valor_total FROM orcamentos WHERE id = :orcamento_id");
        $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
        $stmt->execute();
        $orcamento_valor = $stmt->fetch(PDO::FETCH_ASSOC);
        $valor_orcamento = floatval($orcamento_valor['valor_total'] ?? 0);
        
        // Verificar pagamentos já realizados
        $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa 
                               WHERE orcamento_id = :orcamento_id AND tipo = 'entrada'");
        $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
        $stmt->execute();
        $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_ja_pago = floatval($pagamentos['total_pago'] ?? 0);
        
        // Garantir que não estamos contando duas vezes o mesmo pagamento em caso de edição
        if ($movimentacao['id'] > 0) {
            // Se for edição, descontar o valor anterior
            $stmt = $db->prepare("SELECT valor FROM caixa WHERE id = :id");
            $stmt->bindParam(':id', $movimentacao['id'], PDO::PARAM_INT);
            $stmt->execute();
            $movimento_atual = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($movimento_atual) {
                $total_ja_pago -= floatval($movimento_atual['valor']);
            }
        }
        
        // Verificar se o valor atual + já pago ultrapassa o valor total do orçamento
        $valor_atual = floatval($movimentacao['valor']);
        $valor_total_apos_pagamento = $total_ja_pago + $valor_atual;
        
        if ($valor_total_apos_pagamento > $valor_orcamento) {
            $valor_maximo_permitido = $valor_orcamento - $total_ja_pago;
            if ($valor_maximo_permitido < 0) $valor_maximo_permitido = 0;
            $erro = "O valor de R$ " . number_format($valor_atual, 2, ',', '.') . " ultrapassa o valor restante do orçamento. O valor máximo permitido é R$ " . number_format($valor_maximo_permitido, 2, ',', '.');
        }
    } else {
        try {
            // Iniciar transação para garantir integridade
            $db->beginTransaction();
            
            // Inserir ou atualizar movimentação
            if ($movimentacao['id'] > 0) {
                // Atualizar
                $stmt = $db->prepare("UPDATE caixa SET 
                    data_operacao = :data_operacao,
                    tipo = :tipo,
                    descricao = :descricao,
                    valor = :valor,
                    forma_pagamento = :forma_pagamento,
                    orcamento_id = :orcamento_id,
                    cliente_id = :cliente_id,
                    observacoes = :observacoes
                    WHERE id = :id");
                $stmt->bindParam(':id', $movimentacao['id'], PDO::PARAM_INT);
                $mensagem = 'atualizado';
            } else {
                // Inserir
                $stmt = $db->prepare("INSERT INTO caixa (
                    data_operacao, tipo, descricao, valor, forma_pagamento, orcamento_id, cliente_id, usuario_id, observacoes
                ) VALUES (
                    :data_operacao, :tipo, :descricao, :valor, :forma_pagamento, :orcamento_id, :cliente_id, :usuario_id, :observacoes
                )");
                $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
                $mensagem = 'cadastrado';
            }
            
            // Bind de parâmetros
            $stmt->bindParam(':data_operacao', $movimentacao['data_operacao']);
            $stmt->bindParam(':tipo', $movimentacao['tipo']);
            $stmt->bindParam(':descricao', $movimentacao['descricao']);
            $stmt->bindParam(':valor', $movimentacao['valor']);
            $stmt->bindParam(':forma_pagamento', $movimentacao['forma_pagamento']);
            $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
            $stmt->bindParam(':cliente_id', $movimentacao['cliente_id'], PDO::PARAM_INT);
            $stmt->bindParam(':observacoes', $movimentacao['observacoes']);
            
            $stmt->execute();
            
            // Se estiver vinculado a um orçamento e for uma entrada, atualizar status do pagamento
            if ($movimentacao['orcamento_id'] && $movimentacao['tipo'] == 'entrada') {
                // Buscar valor do orçamento
                $stmt = $db->prepare("SELECT valor_total FROM orcamentos WHERE id = :orcamento_id");
                $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
                $stmt->execute();
                $orcamento_valor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verificar pagamentos já realizados
                $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa 
                                      WHERE orcamento_id = :orcamento_id AND tipo = 'entrada'");
                $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
                $stmt->execute();
                $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
                // Calcular o total considerando o valor atual sendo registrado
                $total_pago = floatval($pagamentos['total_pago'] ?? 0);
                // Garantir que não estamos contando duas vezes o mesmo pagamento em caso de edição
                if ($movimentacao['id'] > 0) {
                    // Se for edição, descontar o valor anterior
                    $stmt = $db->prepare("SELECT valor FROM caixa WHERE id = :id");
                    $stmt->bindParam(':id', $movimentacao['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $movimento_atual = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($movimento_atual) {
                        $total_pago -= floatval($movimento_atual['valor']);
                    }
                }
                // Adicionar o valor atual sendo registrado
                $total_pago += floatval($movimentacao['valor']);
                
                // Determinar automaticamente se é pagamento total ou parcial
                $valor_orcamento = floatval($orcamento_valor['valor_total'] ?? 0);
                $diferenca = abs($total_pago - $valor_orcamento);
                
                // Calcular a porcentagem paga em relação ao valor total (para evitar problemas com pequenos valores)
                $percentual_pago = ($valor_orcamento > 0) ? ($total_pago / $valor_orcamento) * 100 : 0;
                
                // Verificar valores para debug
                $debug_info = "Orcamento ID: {$movimentacao['orcamento_id']}, Valor Total: {$valor_orcamento}, Total Pago: {$total_pago}, Percentual: {$percentual_pago}%";
                error_log($debug_info);
                
                // Considerar como pago total apenas se o valor pago for pelo menos igual ao valor total ou a diferença for muito pequena (< R$0,10)
                $pagamento_total = ($total_pago >= $valor_orcamento) || ($diferenca < 0.1);
                
                // Configurar status de pagamento como total ou parcial
                $status_pagamento = $pagamento_total ? 'pago_total' : 'pago_parcial';
                
                $stmt = $db->prepare("UPDATE orcamentos SET 
                    status_pagamento = :status_pagamento, 
                    data_pagamento = :data_pagamento 
                    WHERE id = :orcamento_id");
                $stmt->bindParam(':status_pagamento', $status_pagamento);
                $stmt->bindParam(':data_pagamento', $movimentacao['data_operacao']);
                $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Finalizar transação
            $db->commit();
            
            // Verificar se devemos redirecionar de volta para o orçamento
            if (!empty($movimentacao['orcamento_id'])) {
                $orcamento_id_redirect = intval($movimentacao['orcamento_id']);
                // Registrar no log para depuração
                error_log("Redirecionando após salvar pagamento para orçamento ID: {$orcamento_id_redirect}");
                
                // Se o orçamento foi totalmente pago, direcionar para a vizualização do orçamento
                $stmt = $db->prepare("SELECT status_pagamento FROM orcamentos WHERE id = :id");
                $stmt->bindParam(':id', $orcamento_id_redirect, PDO::PARAM_INT);
                $stmt->execute();
                $status_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($status_data && $status_data['status_pagamento'] == 'pago_total') {
                    header("Location: orcamento_visualizar.php?id={$orcamento_id_redirect}&mensagem=pago");
                } else {
                    // Forçar recarregamento completo para atualizar todos os valores calculados
                    header("Location: caixa_form.php?orcamento_id={$orcamento_id_redirect}&mensagem={$mensagem}&ts=".time());
                }
                exit;
            } elseif (isset($_POST['orcamento_id_get']) && !empty($_POST['orcamento_id_get'])) {
                $orcamento_id_redirect = intval($_POST['orcamento_id_get']);
                // Se o orçamento foi totalmente pago, direcionar para a vizualização do orçamento
                $stmt = $db->prepare("SELECT status_pagamento FROM orcamentos WHERE id = :id");
                $stmt->bindParam(':id', $orcamento_id_redirect, PDO::PARAM_INT);
                $stmt->execute();
                $status_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($status_data && $status_data['status_pagamento'] == 'pago_total') {
                    header("Location: orcamento_visualizar.php?id={$orcamento_id_redirect}&mensagem=pago");
                } else {
                    // Forçar recarregamento completo para atualizar todos os valores calculados
                    header("Location: caixa_form.php?orcamento_id={$orcamento_id_redirect}&mensagem={$mensagem}&ts=".time());
                }
                exit;
            } else {
                // Redirecionar para a listagem de movimentações
                header("Location: caixa.php?mensagem={$mensagem}");
                exit;
            }
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $erro = 'Erro ao salvar movimentação: ' . $e->getMessage();
        }
    }
}

// Buscar orçamentos pendentes de pagamento para vincular
$orcamentos_pendentes = [];
if ($movimentacao['id'] == 0 || $movimentacao['orcamento_id'] != null) {
    $sql = "SELECT id, numero, status, forma_pagamento, valor_total, data_criacao, 
           (SELECT nome FROM clientes WHERE id = orcamentos.cliente_id) as cliente_nome,
           status_pagamento
           FROM orcamentos 
           WHERE status = 'aprovado' AND (status_pagamento = 'pendente' OR status_pagamento = 'pago_parcial')
           OR id = :orcamento_id 
           ORDER BY data_criacao DESC";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':orcamento_id', $movimentacao['orcamento_id'], PDO::PARAM_INT);
    $stmt->execute();
    $orcamentos_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar todos os clientes para vincular
$clientes = [];
$sql = "SELECT id, nome, telefone FROM clientes ORDER BY nome ASC";
$stmt = $db->prepare($sql);
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agora podemos incluir o header, depois de qualquer possível redirecionamento
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-money-bill-wave me-2"></i><?php echo $titulo; ?></h1>
    <a href="caixa.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?php echo $sucesso; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-edit me-2"></i>Formulário de Movimentação
    </div>
    <div class="card-body">
        <form method="post" id="formCaixa">
            <input type="hidden" name="id" value="<?php echo $movimentacao['id']; ?>">
            <?php if ($orcamento_id > 0): ?>
            <input type="hidden" name="orcamento_id_get" value="<?php echo $orcamento_id; ?>">
            <?php if (isset($movimentacao['valor_total_orcamento'])): ?>
            <input type="hidden" name="valor_total_orcamento" value="<?php echo $movimentacao['valor_total_orcamento']; ?>">
            <?php endif; ?>
            <?php if (isset($movimentacao['total_ja_pago'])): ?>
            <input type="hidden" name="total_ja_pago" value="<?php echo $movimentacao['total_ja_pago']; ?>">
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if (isset($movimentacao['orcamento_id']) && $movimentacao['orcamento_id'] > 0 && isset($movimentacao['valor_total_orcamento'])): ?>
            <div class="alert alert-info mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Valor Total do Orçamento:</strong> <?php echo formataValor($movimentacao['valor_total_orcamento']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Valor Já Pago:</strong> <?php echo formataValor($movimentacao['total_ja_pago']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Valor Restante:</strong> <?php echo formataValor($movimentacao['valor_total_orcamento'] - $movimentacao['total_ja_pago']); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="data_operacao" class="form-label">Data <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="data_operacao" name="data_operacao" value="<?php echo $movimentacao['data_operacao']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                    <div class="form-control">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_entrada" value="entrada" <?php echo $movimentacao['tipo'] == 'entrada' ? 'checked' : ''; ?> required>
                            <label class="form-check-label" for="tipo_entrada">Entrada</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_saida" value="saida" <?php echo $movimentacao['tipo'] == 'saida' ? 'checked' : ''; ?> required>
                            <label class="form-check-label" for="tipo_saida">Saída</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="forma_pagamento" class="form-label">Forma de Pagamento <span class="text-danger">*</span></label>
                    <select class="form-select" id="forma_pagamento" name="forma_pagamento" required>
                        <option value="dinheiro" <?php echo $movimentacao['forma_pagamento'] == 'dinheiro' ? 'selected' : ''; ?>>Dinheiro</option>
                        <option value="cartao" <?php echo $movimentacao['forma_pagamento'] == 'cartao' ? 'selected' : ''; ?>>Cartão</option>
                        <option value="pix" <?php echo $movimentacao['forma_pagamento'] == 'pix' ? 'selected' : ''; ?>>PIX</option>
                        <option value="transferencia" <?php echo $movimentacao['forma_pagamento'] == 'transferencia' ? 'selected' : ''; ?>>Transferência</option>
                        <option value="outro" <?php echo $movimentacao['forma_pagamento'] == 'outro' ? 'selected' : ''; ?>>Outro</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-8">
                    <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="descricao" name="descricao" value="<?php echo $movimentacao['descricao']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control" id="valor" name="valor" value="<?php echo !empty($movimentacao['valor']) ? number_format((float)$movimentacao['valor'], 2, ',', '.') : ''; ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="orcamento_id" class="form-label">Vincular a Orçamento</label>
                    <select class="form-select" id="orcamento_id" name="orcamento_id">
                        <option value="">Nenhum (Movimentação avulsa)</option>
                        <?php foreach ($orcamentos_pendentes as $orc): ?>
                            <option value="<?php echo $orc['id']; ?>" <?php echo ($movimentacao['orcamento_id'] == $orc['id']) ? 'selected' : ''; ?>
                                    data-valor="<?php echo $orc['valor_total']; ?>">
                                #<?php echo $orc['numero']; ?> - <?php echo $orc['cliente_nome']; ?> - <?php echo formataValor($orc['valor_total']); ?> - <?php echo dataParaBr($orc['data_criacao']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-muted">
                        Ao vincular um orçamento, esta movimentação marcará o orçamento como pago automaticamente (se for uma entrada).
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label">Vincular a Cliente</label>
                    <select class="form-select" id="cliente_id" name="cliente_id">
                        <option value="">Nenhum (Movimentação avulsa)</option>
                        <?php foreach ($clientes as $cli): ?>
                            <option value="<?php echo $cli['id']; ?>" <?php echo ($movimentacao['cliente_id'] == $cli['id']) ? 'selected' : ''; ?>>
                                <?php echo $cli['nome']; ?> - <?php echo $cli['telefone']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-muted">
                        Você pode vincular esta movimentação a um cliente para facilitar o rastreamento de pagamentos.
                    </div>
                </div>
            </div>
            
            <div class="mb-3" id="opcoes-pagamento" style="display:none;">
                <label class="form-label">Tipo de Pagamento</label>
                <div class="form-control">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="pagamento_total" id="pagamento_total_sim" value="1" checked>
                        <label class="form-check-label" for="pagamento_total_sim">Pagamento Total</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="pagamento_total" id="pagamento_total_nao" value="0">
                        <label class="form-check-label" for="pagamento_total_nao">Pagamento Parcial</label>
                    </div>
                </div>
                <div class="form-text text-muted" id="texto-pagamento-parcial" style="display:none;">
                    O orçamento será marcado como parcialmente pago. Você poderá registrar o restante do pagamento posteriormente.
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $movimentacao['observacoes']; ?></textarea>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?php echo $modo == 'cadastrar' ? 'Registrar' : 'Atualizar'; ?> Movimentação
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Formatação de valores monetários
    const valorInput = document.getElementById('valor');
    const form = document.getElementById('formCaixa');
    const orcamentoSelect = document.getElementById('orcamento_id');
    const clienteSelect = document.getElementById('cliente_id');
    const opcoesPagamento = document.getElementById('opcoes-pagamento');
    const pagamentoTotalNao = document.getElementById('pagamento_total_nao');
    const textoPagamentoParcial = document.getElementById('texto-pagamento-parcial');
    
    // Função para mostrar/esconder opções de pagamento
    function verificarOrcamento() {
        const orcamentoId = orcamentoSelect.value;
        if (orcamentoId && orcamentoSelect.options[orcamentoSelect.selectedIndex].text.includes('pendente')) {
            opcoesPagamento.style.display = 'block';
            
            // Se for selecionado um orçamento, preencher o valor automaticamente
            if (valorInput.value === '' && orcamentoSelect.selectedOptions[0].dataset.valor) {
                const valorOrcamento = parseFloat(orcamentoSelect.selectedOptions[0].dataset.valor);
                valorInput.value = valorOrcamento.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            
            // Desabilitar cliente se selecionado um orçamento
            clienteSelect.disabled = true;
            clienteSelect.value = '';
        } else {
            opcoesPagamento.style.display = 'none';
            clienteSelect.disabled = false;
        }
    }
    
    // Função para verificar seleção de cliente
    function verificarCliente() {
        const clienteId = clienteSelect.value;
        if (clienteId) {
            // Desabilitar orçamento se selecionado um cliente
            orcamentoSelect.disabled = true;
            orcamentoSelect.value = '';
            opcoesPagamento.style.display = 'none';
        } else {
            orcamentoSelect.disabled = false;
        }
    }
    
    // Verificar quando o orçamento é selecionado
    orcamentoSelect.addEventListener('change', verificarOrcamento);
    
    // Verificar quando o cliente é selecionado
    clienteSelect.addEventListener('change', verificarCliente);
    
    // Mostrar/esconder texto de pagamento parcial
    pagamentoTotalNao.addEventListener('change', function() {
        textoPagamentoParcial.style.display = this.checked ? 'block' : 'none';
    });
    
    document.getElementById('pagamento_total_sim').addEventListener('change', function() {
        textoPagamentoParcial.style.display = this.checked ? 'none' : 'block';
    });
    
    // Verificar no carregamento da página
    verificarOrcamento();
    verificarCliente();
    
    valorInput.addEventListener('input', function(e) {
        let valor = e.target.value.replace(/\D/g, '');
        
        if (valor.length === 0) {
            e.target.value = '';
            return;
        }
        
        // Converter para formato de moeda
        valor = (parseInt(valor) / 100).toFixed(2);
        e.target.value = valor.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    });

    // Validação do formulário
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
</script>

<?php
require_once('includes/footer.php');
?>