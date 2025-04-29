<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Inicialização de variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$venda = [
    'id' => 0,
    'numero' => gerarNumeroVenda(),
    'data_venda' => date('Y-m-d'),
    'cliente_id' => null,
    'valor_total' => 0,
    'valor_desconto' => 0,
    'forma_pagamento' => 'dinheiro',
    'status' => 'finalizada',
    'status_pagamento' => 'pendente',
    'data_pagamento' => null,
    'observacoes' => ''
];
$itens = [];
$erro = '';
$sucesso = '';
$titulo = 'Nova Venda Direta';
$modo = 'cadastrar';

// Função para gerar número de venda
function gerarNumeroVenda() {
    global $db;
    
    $ano = date('Y');
    $mes = date('m');
    $prefixo = "V{$ano}{$mes}";
    
    // Buscar o último número de venda com este prefixo
    $stmt = $db->prepare("SELECT numero FROM vendas WHERE numero LIKE :prefixo ORDER BY id DESC LIMIT 1");
    $busca = $prefixo . '%';
    $stmt->bindParam(':prefixo', $busca);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $ultimoNumero = $stmt->fetch(PDO::FETCH_COLUMN);
        $sequencial = intval(substr($ultimoNumero, strlen($prefixo))) + 1;
    } else {
        $sequencial = 1;
    }
    
    // Formatar o sequencial com zeros à esquerda
    $sequencialFormatado = str_pad($sequencial, 4, '0', STR_PAD_LEFT);
    
    return "{$prefixo}{$sequencialFormatado}";
}

// Se for edição, buscar dados da venda
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM vendas WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $venda = $stmt->fetch(PDO::FETCH_ASSOC);
        $titulo = 'Editar Venda #' . $venda['numero'];
        $modo = 'editar';
        
        // Buscar itens da venda
        $stmt = $db->prepare("SELECT * FROM vendas_itens WHERE venda_id = :venda_id");
        $stmt->bindParam(':venda_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $erro = 'Venda não encontrada';
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Capturar dados do formulário
        $venda = [
            'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
            'numero' => $_POST['numero'] ?? gerarNumeroVenda(),
            'data_venda' => $_POST['data_venda'] ?? date('Y-m-d'),
            'cliente_id' => !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : null,
            'valor_total' => 0, // Será calculado com base nos itens
            'forma_pagamento' => $_POST['forma_pagamento'] ?? 'dinheiro',
            'status' => 'finalizada',
            'status_pagamento' => isset($_POST['pagamento_prazo']) && $_POST['pagamento_prazo'] == '2' ? 'pendente' : 'pago_total', // A prazo (2) fica como pendente, cartão (1) e dinheiro (0) ficam como pago_total
            'data_pagamento' => $_POST['data_venda'],
            'observacoes' => limpaString($_POST['observacoes'] ?? '')
        ];
        
        // Itens da venda
        $itens = [];
        $subtotal_venda = 0;
        
        if (isset($_POST['produto_id']) && is_array($_POST['produto_id'])) {
            for ($i = 0; $i < count($_POST['produto_id']); $i++) {
                if (empty($_POST['produto_id'][$i]) || empty($_POST['quantidade'][$i])) continue;
                
                $produto_id = intval($_POST['produto_id'][$i]);
                $quantidade = floatval(str_replace(',', '.', $_POST['quantidade'][$i]));
                $valor_unitario = floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor_unitario'][$i])));
                $valor_total = $quantidade * $valor_unitario;
                $subtotal_venda += $valor_total;
                
                // Buscar informações do produto
                $stmt = $db->prepare("SELECT descricao, unidade FROM produtos WHERE id = :id");
                $stmt->bindParam(':id', $produto_id, PDO::PARAM_INT);
                $stmt->execute();
                $produto = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $itens[] = [
                    'produto_id' => $produto_id,
                    'descricao' => $produto['descricao'],
                    'unidade' => $produto['unidade'],
                    'quantidade' => $quantidade,
                    'valor_unitario' => $valor_unitario,
                    'valor_total' => $valor_total
                ];
            }
        }
        
        // Aplicar desconto se for pagamento à vista
        $total_venda = $subtotal_venda;
        if (isset($_POST['pagamento_prazo']) && $_POST['pagamento_prazo'] == '0' && isset($_POST['desconto_vista'])) {
            $desconto_percentual = floatval($_POST['desconto_vista']);
            if ($desconto_percentual > 0) {
                $valor_desconto = $subtotal_venda * ($desconto_percentual / 100);
                $total_venda = $subtotal_venda - $valor_desconto;
            }
        }
        
        $venda['valor_total'] = $total_venda;
        $venda['valor_desconto'] = isset($valor_desconto) ? $valor_desconto : 0;
        
        // Validar se há pelo menos um item
        if (count($itens) == 0) {
            throw new Exception('Adicione pelo menos um item à venda.');
        }
        
        // Iniciar transação
        $db->beginTransaction();
        
        // Inserir ou atualizar venda
        if ($venda['id'] > 0) {
            // Atualizar venda
            $stmt = $db->prepare("UPDATE vendas SET 
                numero = :numero,
                data_venda = :data_venda,
                cliente_id = :cliente_id,
                valor_total = :valor_total,
                valor_desconto = :valor_desconto,
                forma_pagamento = :forma_pagamento,
                status = :status,
                status_pagamento = :status_pagamento,
                data_pagamento = :data_pagamento,
                observacoes = :observacoes
                WHERE id = :id");
            $stmt->bindParam(':id', $venda['id'], PDO::PARAM_INT);
            $mensagem = 'atualizado';
        } else {
            // Inserir nova venda
            $stmt = $db->prepare("INSERT INTO vendas (
                numero, data_venda, cliente_id, valor_total, valor_desconto, forma_pagamento, status, status_pagamento, 
                data_pagamento, usuario_id, observacoes
            ) VALUES (
                :numero, :data_venda, :cliente_id, :valor_total, :valor_desconto, :forma_pagamento, :status, :status_pagamento, 
                :data_pagamento, :usuario_id, :observacoes
            ) RETURNING id");
            $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
            $mensagem = 'cadastrado';
        }
        
        // Bind de parâmetros comuns
        $stmt->bindParam(':numero', $venda['numero']);
        $stmt->bindParam(':data_venda', $venda['data_venda']);
        $stmt->bindParam(':cliente_id', $venda['cliente_id'], PDO::PARAM_INT);
        $stmt->bindParam(':valor_total', $venda['valor_total']);
        $stmt->bindParam(':valor_desconto', $venda['valor_desconto']);
        $stmt->bindParam(':forma_pagamento', $venda['forma_pagamento']);
        $stmt->bindParam(':status', $venda['status']);
        $stmt->bindParam(':status_pagamento', $venda['status_pagamento']);
        $stmt->bindParam(':data_pagamento', $venda['data_pagamento']);
        $stmt->bindParam(':observacoes', $venda['observacoes']);
        $stmt->execute();
        
        // Se for inserção, capturar o ID gerado
        if ($venda['id'] == 0) {
            $venda['id'] = $db->lastInsertId('vendas_id_seq');
        } else {
            // Se for atualização, excluir itens antigos
            $stmt = $db->prepare("DELETE FROM vendas_itens WHERE venda_id = :venda_id");
            $stmt->bindParam(':venda_id', $venda['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Inserir itens da venda
        foreach ($itens as $item) {
            $stmt = $db->prepare("INSERT INTO vendas_itens (
                venda_id, produto_id, descricao, unidade, quantidade, valor_unitario, valor_total
            ) VALUES (
                :venda_id, :produto_id, :descricao, :unidade, :quantidade, :valor_unitario, :valor_total
            )");
            
            $stmt->bindParam(':venda_id', $venda['id'], PDO::PARAM_INT);
            $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
            $stmt->bindParam(':descricao', $item['descricao']);
            $stmt->bindParam(':unidade', $item['unidade']);
            $stmt->bindParam(':quantidade', $item['quantidade']);
            $stmt->bindParam(':valor_unitario', $item['valor_unitario']);
            $stmt->bindParam(':valor_total', $item['valor_total']);
            $stmt->execute();
            
            // Atualizar estoque do produto
            $stmt = $db->prepare("UPDATE produtos SET estoque_atual = estoque_atual - :quantidade WHERE id = :produto_id");
            $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
            $stmt->bindParam(':quantidade', $item['quantidade']);
            $stmt->execute();
            
            // Registrar movimentação no estoque
            $stmt = $db->prepare("INSERT INTO estoque_movimentacoes 
                            (produto_id, tipo, quantidade, valor_unitario, valor_total, observacao)
                            VALUES 
                            (:produto_id, 'saida', :quantidade, :valor_unitario, :valor_total, :observacao)");
            $stmt->bindParam(':produto_id', $item['produto_id'], PDO::PARAM_INT);
            $stmt->bindParam(':quantidade', $item['quantidade']);
            $stmt->bindParam(':valor_unitario', $item['valor_unitario']);
            $stmt->bindParam(':valor_total', $item['valor_total']);
            $observacao = "Saída automática da venda #{$venda['numero']}";
            $stmt->bindParam(':observacao', $observacao);
            $stmt->execute();
        }
        
        // Registrar movimentação no caixa (apenas para vendas à vista)
        if ($venda['status_pagamento'] == 'pago_total') {
            $stmt = $db->prepare("INSERT INTO caixa 
                            (data_operacao, tipo, descricao, valor, forma_pagamento, usuario_id, observacoes, venda_id)
                            VALUES 
                            (:data_operacao, :tipo, :descricao, :valor, :forma_pagamento, :usuario_id, :observacoes, :venda_id)");
            $stmt->bindParam(':data_operacao', $venda['data_venda']);
            $tipo = 'entrada';
            $stmt->bindParam(':tipo', $tipo);
            $descricao = "Venda direta #{$venda['numero']}";
            $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':valor', $venda['valor_total']);
            $stmt->bindParam(':forma_pagamento', $venda['forma_pagamento']);
            $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
            $observacoes = !empty($venda['observacoes']) ? $venda['observacoes'] : "Venda direta";
            $stmt->bindParam(':observacoes', $observacoes);
            $stmt->bindParam(':venda_id', $venda['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Finalizar transação
        $db->commit();
        
        // Redirecionar para a listagem de vendas
        header("Location: vendas.php?mensagem={$mensagem}");
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $erro = 'Erro ao salvar venda: ' . $e->getMessage();
    }
}

// Buscar clientes para o select
$clientes = [];
$stmt = $db->prepare("SELECT id, nome, cpf_cnpj FROM clientes ORDER BY nome");
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar produtos para o select
$produtos = [];
$stmt = $db->prepare("SELECT id, descricao, unidade, valor_unitario, estoque_atual FROM produtos ORDER BY descricao");
$stmt->execute();
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-shopping-cart me-2"></i><?php echo $titulo; ?></h1>
    <a href="vendas.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?php echo $sucesso; ?></div>
<?php endif; ?>

<form method="post" action="venda_form.php" id="formVenda">
    <input type="hidden" name="id" value="<?php echo $venda['id']; ?>">
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-info-circle me-2"></i>Informações da Venda
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="numero" class="form-label">Número da Venda</label>
                    <input type="text" class="form-control" id="numero" name="numero" value="<?php echo $venda['numero']; ?>" readonly>
                </div>
                <div class="col-md-4">
                    <label for="data_venda" class="form-label">Data <span class="required-field"></span></label>
                    <input type="date" class="form-control" id="data_venda" name="data_venda" value="<?php echo $venda['data_venda']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="forma_pagamento" class="form-label">Forma de Pagamento <span class="required-field"></span></label>
                    <select class="form-select" id="forma_pagamento" name="forma_pagamento" required>
                        <option value="dinheiro" <?php echo $venda['forma_pagamento'] == 'dinheiro' ? 'selected' : ''; ?>>Dinheiro</option>
                        <option value="cartao" <?php echo $venda['forma_pagamento'] == 'cartao' ? 'selected' : ''; ?>>Cartão de Crédito/Débito</option>
                        <option value="pix" <?php echo $venda['forma_pagamento'] == 'pix' ? 'selected' : ''; ?>>PIX</option>
                        <option value="transferencia" <?php echo $venda['forma_pagamento'] == 'transferencia' ? 'selected' : ''; ?>>Transferência</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-8">
                    <label for="cliente_id" class="form-label">Cliente (opcional)</label>
                    <select class="form-select" id="cliente_id" name="cliente_id">
                        <option value="">Selecione um cliente (opcional)</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>" <?php echo ($venda['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                <?php echo $cliente['nome']; ?> <?php echo !empty($cliente['cpf_cnpj']) ? '(' . $cliente['cpf_cnpj'] . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Selecione um cliente para vendas a prazo ou crédito.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pagamento</label>
                    <div class="form-control">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="pagamento_prazo" id="pagamento_vista" value="0" <?php echo $venda['status_pagamento'] == 'pago_total' ? 'checked' : ''; ?> onclick="toggleClienteRequired(false); toggleDesconto(true);">
                            <label class="form-check-label" for="pagamento_vista">À Vista</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="pagamento_prazo" id="pagamento_cartao" value="1" <?php echo $venda['status_pagamento'] == 'pago_total' && $venda['forma_pagamento'] == 'cartao' ? 'checked' : ''; ?> onclick="toggleClienteRequired(false); toggleDesconto(false);">
                            <label class="form-check-label" for="pagamento_cartao">Cartão (Até 12x Sem Juros)</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="pagamento_prazo" id="pagamento_prazo" value="2" <?php echo $venda['status_pagamento'] == 'pendente' ? 'checked' : ''; ?> onclick="toggleClienteRequired(true); toggleDesconto(false);">
                            <label class="form-check-label" for="pagamento_prazo">A Prazo</label>
                        </div>
                    </div>
                    <?php
                    // Buscar desconto de pagamento à vista das configurações
                    $desconto_pagamento_vista = 10; // Valor padrão
                    $stmt_config = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'desconto_pagamento_vista'");
                    $stmt_config->execute();
                    $config_desconto = $stmt_config->fetch(PDO::FETCH_ASSOC);
                    if ($config_desconto) {
                        $desconto_pagamento_vista = floatval($config_desconto['valor']);
                    }
                    ?>
                    <div id="desconto-info" class="alert alert-success mt-2 p-2" style="display: <?php echo $venda['status_pagamento'] == 'pago_total' ? 'block' : 'none'; ?>">
                        Desconto de <?php echo number_format($desconto_pagamento_vista, 2, ',', '.'); ?>% aplicado no pagamento à vista!
                        <input type="hidden" name="desconto_vista" id="desconto_vista" value="<?php echo $desconto_pagamento_vista; ?>">
                    </div>

                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="2"><?php echo $venda['observacoes']; ?></textarea>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-boxes me-2"></i>Itens da Venda
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="tabela-itens">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Produto</th>
                            <th style="width: 15%;">Unidade</th>
                            <th style="width: 15%;">Quantidade</th>
                            <th style="width: 15%;">Valor Unitário</th>
                            <th style="width: 15%;">Valor Total</th>
                            <th style="width: 5%;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($itens) > 0): ?>
                            <?php foreach ($itens as $index => $item): ?>
                                <tr>
                                    <td>
                                        <select class="form-select produto-select" name="produto_id[]" required>
                                            <option value="">Selecione um produto</option>
                                            <?php foreach ($produtos as $produto): ?>
                                                <option value="<?php echo $produto['id']; ?>" 
                                                        data-unidade="<?php echo $produto['unidade']; ?>"
                                                        data-preco="<?php echo number_format($produto['valor_unitario'], 2, ',', '.'); ?>"
                                                        data-estoque="<?php echo $produto['estoque_atual']; ?>"
                                                        <?php echo ($item['produto_id'] == $produto['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $produto['descricao']; ?> (Estoque: <?php echo $produto['estoque_atual']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control unidade-item" value="<?php echo $item['unidade']; ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control quantidade-item" name="quantidade[]" value="<?php echo number_format($item['quantidade'], 2, ',', '.'); ?>" required>
                                    </td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" class="form-control valor-unitario" name="valor_unitario[]" value="<?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?>" required>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" class="form-control valor-total" value="<?php echo number_format($item['valor_total'], 2, ',', '.'); ?>" readonly>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm remover-item">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td>
                                    <select class="form-select produto-select" name="produto_id[]" required>
                                        <option value="">Selecione um produto</option>
                                        <?php foreach ($produtos as $produto): ?>
                                            <option value="<?php echo $produto['id']; ?>" 
                                                    data-unidade="<?php echo $produto['unidade']; ?>"
                                                    data-preco="<?php echo number_format($produto['valor_unitario'], 2, ',', '.'); ?>"
                                                    data-estoque="<?php echo $produto['estoque_atual']; ?>">
                                                <?php echo $produto['descricao']; ?> (Estoque: <?php echo $produto['estoque_atual']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control unidade-item" readonly>
                                </td>
                                <td>
                                    <input type="text" class="form-control quantidade-item" name="quantidade[]" required>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" class="form-control valor-unitario" name="valor_unitario[]" required>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" class="form-control valor-total" readonly>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm remover-item">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6">
                                <button type="button" class="btn btn-success" id="adicionar-item">
                                    <i class="fas fa-plus-circle me-2"></i>Adicionar Item
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="d-flex justify-content-end mt-3">
                <div class="border p-3 bg-light rounded" style="width: 300px;">
                    <h5 class="mb-3">Resumo da Venda</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <strong>Subtotal:</strong>
                        <span id="subtotal-venda"><?php echo formataValor($venda['valor_total']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2" id="linha-desconto" style="display: <?php echo $venda['status_pagamento'] == 'pago_total' ? 'flex' : 'none'; ?>; color: green;">
                        <strong>Desconto:</strong>
                        <span id="desconto-venda">- R$ 0,00</span>
                    </div>
                    <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                        <strong>Total:</strong>
                        <span id="total-venda" class="fw-bold"><?php echo formataValor($venda['valor_total']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-end mb-4">
        <a href="vendas.php" class="btn btn-secondary me-2">
            <i class="fas fa-times me-2"></i>Cancelar
        </a>
        <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-2"></i>Salvar Venda
        </button>
    </div>
</form>

<!-- Template para adicionar nova linha -->
<template id="linha-item-template">
    <tr>
        <td>
            <select class="form-select produto-select" name="produto_id[]" required>
                <option value="">Selecione um produto</option>
                <?php foreach ($produtos as $produto): ?>
                    <option value="<?php echo $produto['id']; ?>" 
                            data-unidade="<?php echo $produto['unidade']; ?>"
                            data-preco="<?php echo number_format($produto['valor_unitario'], 2, ',', '.'); ?>"
                            data-estoque="<?php echo $produto['estoque_atual']; ?>">
                        <?php echo $produto['descricao']; ?> (Estoque: <?php echo $produto['estoque_atual']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" class="form-control unidade-item" readonly>
        </td>
        <td>
            <input type="text" class="form-control quantidade-item" name="quantidade[]" required>
        </td>
        <td>
            <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" class="form-control valor-unitario" name="valor_unitario[]" required>
            </div>
        </td>
        <td>
            <div class="input-group">
                <span class="input-group-text">R$</span>
                <input type="text" class="form-control valor-total" readonly>
            </div>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm remover-item">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Função para formatar números como moeda
    function formatarMoeda(valor) {
        return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    
    // Função para converter string formatada em número
    function converterParaNumero(valor) {
        if (!valor) return 0;
        return parseFloat(valor.replace('.', '').replace(',', '.'));
    }
    
    // Função para calcular o total de um item
    function calcularTotalItem(linha) {
        const quantidade = converterParaNumero(linha.querySelector('.quantidade-item').value);
        const valorUnitario = converterParaNumero(linha.querySelector('.valor-unitario').value);
        const total = quantidade * valorUnitario;
        
        linha.querySelector('.valor-total').value = formatarMoeda(total);
        return total;
    }
    
    // Função para calcular o total da venda
    function calcularTotalVenda() {
        let subtotal = 0;
        document.querySelectorAll('#tabela-itens tbody tr').forEach(linha => {
            subtotal += calcularTotalItem(linha);
        });
        
        // Atualizar subtotal
        document.getElementById('subtotal-venda').textContent = 'R$ ' + formatarMoeda(subtotal);
        
        // Inicializar total com o subtotal
        let total = subtotal;
        let valorDesconto = 0;
        
        // Verificar se pagamento é à vista para aplicar desconto
        if (document.getElementById('pagamento_vista').checked) {
            const descontoPercent = parseFloat(document.getElementById('desconto_vista').value);
            if (!isNaN(descontoPercent) && descontoPercent > 0) {
                valorDesconto = subtotal * (descontoPercent / 100);
                total = subtotal - valorDesconto;
                
                // Mostrar o desconto aplicado na linha de info
                document.getElementById('desconto-info').innerText = 
                    `Desconto de ${descontoPercent.toFixed(2).replace('.', ',')}% aplicado no pagamento à vista`;
                
                // Mostrar o desconto no resumo da venda
                document.getElementById('linha-desconto').style.display = 'flex';
                document.getElementById('desconto-venda').textContent = '- R$ ' + formatarMoeda(valorDesconto);
            }
        } else {
            // Esconder a linha de desconto no resumo da venda
            document.getElementById('linha-desconto').style.display = 'none';
        }
        
        // Atualizar o total final
        document.getElementById('total-venda').textContent = 'R$ ' + formatarMoeda(total);
    }
    
    // Função para mostrar/esconder informações de desconto
    function toggleDesconto(mostrar) {
        const descontoInfo = document.getElementById('desconto-info');
        
        if (mostrar) {
            descontoInfo.style.display = 'block';
            // Mostrar o valor atual do desconto
            const descontoPercent = parseFloat(document.getElementById('desconto_vista').value);
            if (!isNaN(descontoPercent)) {
                descontoInfo.innerHTML = `Desconto de ${descontoPercent.toFixed(2).replace('.', ',')}% aplicado no pagamento à vista!`;
            }
        } else {
            descontoInfo.style.display = 'none';
        }
        
        // Recalcular total com ou sem desconto
        calcularTotalVenda();
    }
    
    // Formatando campos de valor ao carregar a página
    document.querySelectorAll('.valor-unitario, .quantidade-item').forEach(campo => {
        campo.addEventListener('blur', function() {
            const valor = converterParaNumero(this.value);
            this.value = formatarMoeda(valor);
            calcularTotalVenda();
        });
    });
    
    // Aplicar máscara aos campos de valor ao digitar
    document.querySelectorAll('.valor-unitario, .quantidade-item').forEach(campo => {
        campo.addEventListener('input', function() {
            calcularTotalVenda();
        });
    });
    
    // Manipular mudança de produto selecionado
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('produto-select')) {
            const linha = e.target.closest('tr');
            const option = e.target.options[e.target.selectedIndex];
            
            if (option.value) {
                linha.querySelector('.unidade-item').value = option.dataset.unidade;
                linha.querySelector('.valor-unitario').value = option.dataset.preco;
                // Definir quantidade padrão como 1 se estiver vazio
                if (!linha.querySelector('.quantidade-item').value) {
                    linha.querySelector('.quantidade-item').value = '1,00';
                }
                calcularTotalItem(linha);
                calcularTotalVenda();
            } else {
                linha.querySelector('.unidade-item').value = '';
                linha.querySelector('.valor-unitario').value = '';
                linha.querySelector('.quantidade-item').value = '';
                linha.querySelector('.valor-total').value = '';
                calcularTotalVenda();
            }
        }
    });
    
    // Adicionar novo item
    document.getElementById('adicionar-item').addEventListener('click', function() {
        const template = document.getElementById('linha-item-template');
        const clone = template.content.cloneNode(true);
        document.querySelector('#tabela-itens tbody').appendChild(clone);
    });
    
    // Remover item
    document.addEventListener('click', function(e) {
        if (e.target && e.target.closest('.remover-item')) {
            const button = e.target.closest('.remover-item');
            const linha = button.closest('tr');
            
            // Se for a única linha, apenas limpar os campos
            if (document.querySelectorAll('#tabela-itens tbody tr').length > 1) {
                linha.remove();
            } else {
                linha.querySelector('.produto-select').value = '';
                linha.querySelector('.unidade-item').value = '';
                linha.querySelector('.quantidade-item').value = '';
                linha.querySelector('.valor-unitario').value = '';
                linha.querySelector('.valor-total').value = '';
            }
            
            calcularTotalVenda();
        }
    });
    
    // Calcular totais iniciais
    calcularTotalVenda();
    
    // Função para verificar e atualizar a obrigatoriedade do cliente
    function toggleClienteRequired(required) {
        const clienteSelect = document.getElementById('cliente_id');
        
        if (required) {
            clienteSelect.setAttribute('required', 'required');
        } else {
            clienteSelect.removeAttribute('required');
        }
    }
    
    // Verificar cliente quando pagamento a prazo é selecionado
    document.getElementById('cliente_id').addEventListener('change', function() {
        if (document.getElementById('pagamento_prazo').checked) {
            toggleClienteRequired(true);
        }
    });
    
    // Verificar inicial
    if (document.getElementById('pagamento_prazo').checked) {
        toggleClienteRequired(true);
    }
    
    // Validar formulário antes de enviar
    document.getElementById('formVenda').addEventListener('submit', function(e) {
        if (document.getElementById('pagamento_prazo').checked && !document.getElementById('cliente_id').value) {
            e.preventDefault();
            alert('Para vendas a prazo, é necessário selecionar um cliente!');
            toggleClienteRequired(true);
            document.getElementById('cliente_id').focus();
        }
    });
    
    // Identificar o tipo de pagamento corretamente
    document.querySelector('input[name="pagamento_prazo"]:checked').addEventListener('change', function() {
        console.log('Mudou forma de pagamento para: ' + this.value);
    });
});
</script>

<?php
require_once('includes/footer.php');
?>