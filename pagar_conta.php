<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
verificarAutenticacao();

// Inicializar variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$data_pagamento = date('Y-m-d');
$valor = 0;
$forma_pagamento = 'dinheiro';
$observacoes = '';
$registrar_caixa = true;
$mensagem = '';
$conta = null;

// Verificar se a conta existe e é válida para pagamento
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM contas_pagar WHERE id = ? AND status = 'pendente'");
    $stmt->execute([$id]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conta) {
        $mensagem = alerta('Conta não encontrada ou já está paga/cancelada.', 'danger');
    } else {
        $valor = $conta['valor'];
    }
} else {
    header('Location: contas_pagar.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $conta) {
    $data_pagamento = $_POST['data_pagamento'];
    $valor = floatval(str_replace(['.', ','], ['', '.'], $_POST['valor']));
    $forma_pagamento = $_POST['forma_pagamento'];
    $observacoes = trim($_POST['observacoes']);
    $registrar_caixa = isset($_POST['registrar_caixa']);
    
    // Validar campos obrigatórios
    if (empty($data_pagamento) || $valor <= 0) {
        $mensagem = alerta('Por favor, preencha todos os campos obrigatórios.', 'danger');
    } else {
        try {
            $db->beginTransaction();
            
            // 1. Registrar o pagamento na tabela de pagamentos
            $stmt = $db->prepare("INSERT INTO pagamentos_contas 
                (conta_id, data_pagamento, valor, forma_pagamento, observacoes, usuario_id) 
                VALUES (?, ?, ?, ?, ?, ?)");
                
            $stmt->execute([
                $id,
                $data_pagamento,
                $valor,
                $forma_pagamento,
                $observacoes,
                $_SESSION['usuario_id']
            ]);
            
            $pagamento_id = $db->lastInsertId();
            
            // 2. Atualizar o status da conta para pago
            $stmt = $db->prepare("UPDATE contas_pagar SET 
                status = 'pago', 
                data_pagamento = ?, 
                valor_pago = ?,
                forma_pagamento = ?,
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?");
                
            $stmt->execute([
                $data_pagamento,
                $valor,
                $forma_pagamento,
                $id
            ]);
            
            // 3. Registrar saída no caixa, se solicitado
            if ($registrar_caixa) {
                $descricao_caixa = "Pagamento de conta: {$conta['descricao']}";
                if (!empty($conta['fornecedor'])) {
                    $descricao_caixa .= " - Fornecedor: {$conta['fornecedor']}";
                }
                
                $stmt = $db->prepare("INSERT INTO caixa 
                    (tipo, descricao, valor, data_operacao, forma_pagamento, observacoes, conta_pagar_id, usuario_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                $stmt->execute([
                    'saida',
                    $descricao_caixa,
                    $valor,
                    $data_pagamento,
                    $forma_pagamento,
                    $observacoes,
                    $id,
                    $_SESSION['usuario_id']
                ]);
                
                $caixa_id = $db->lastInsertId();
                
                // Associar o registro de caixa ao pagamento
                $stmt = $db->prepare("UPDATE pagamentos_contas SET caixa_id = ? WHERE id = ?");
                $stmt->execute([$caixa_id, $pagamento_id]);
            }
            
            $db->commit();
            
            // Redirecionar para a lista de contas
            header('Location: contas_pagar.php?mensagem=pago');
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $mensagem = alerta('Erro ao registrar pagamento: ' . $e->getMessage(), 'danger');
        }
    }
}

// Incluir o cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Registrar Pagamento de Conta</h1>
    <a href="contas_pagar.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<?php if ($conta): ?>
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informações da Conta</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Descrição:</strong> <?php echo htmlspecialchars($conta['descricao']); ?></p>
                        <p><strong>Fornecedor:</strong> <?php echo htmlspecialchars($conta['fornecedor'] ?? '-'); ?></p>
                        <p><strong>Categoria:</strong> <?php echo htmlspecialchars($conta['categoria'] ?? '-'); ?></p>
                        <p><strong>Documento:</strong> <?php echo htmlspecialchars($conta['documento'] ?? '-'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Data de Emissão:</strong> <?php echo dataParaBr($conta['data_emissao']); ?></p>
                        <p><strong>Data de Vencimento:</strong> <?php echo dataParaBr($conta['data_vencimento']); ?></p>
                        <p><strong>Valor:</strong> <?php echo formataValor($conta['valor']); ?></p>
                        <p>
                            <strong>Status:</strong> 
                            <span class="badge bg-warning text-dark">Pendente</span>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($conta['observacoes'])): ?>
                <div class="alert alert-secondary">
                    <strong>Observações:</strong><br>
                    <?php echo nl2br(htmlspecialchars($conta['observacoes'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Registrar Pagamento</h5>
            </div>
            <div class="card-body">
                <form method="post" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="data_pagamento" class="form-label required-field">Data de Pagamento</label>
                        <input type="date" class="form-control" id="data_pagamento" name="data_pagamento" value="<?php echo $data_pagamento; ?>" required>
                        <div class="invalid-feedback">Por favor, informe a data de pagamento.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="valor" class="form-label required-field">Valor Pago</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control monetary-input" id="valor" name="valor" value="<?php echo number_format($valor, 2, ',', '.'); ?>" required>
                        </div>
                        <div class="invalid-feedback">Por favor, informe o valor pago.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="forma_pagamento" name="forma_pagamento">
                            <option value="dinheiro" <?php echo $forma_pagamento == 'dinheiro' ? 'selected' : ''; ?>>Dinheiro</option>
                            <option value="pix" <?php echo $forma_pagamento == 'pix' ? 'selected' : ''; ?>>PIX</option>
                            <option value="debito" <?php echo $forma_pagamento == 'debito' ? 'selected' : ''; ?>>Cartão de Débito</option>
                            <option value="credito" <?php echo $forma_pagamento == 'credito' ? 'selected' : ''; ?>>Cartão de Crédito</option>
                            <option value="transferencia" <?php echo $forma_pagamento == 'transferencia' ? 'selected' : ''; ?>>Transferência Bancária</option>
                            <option value="boleto" <?php echo $forma_pagamento == 'boleto' ? 'selected' : ''; ?>>Boleto</option>
                            <option value="cheque" <?php echo $forma_pagamento == 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                            <option value="outro" <?php echo $forma_pagamento == 'outro' ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="2"><?php echo htmlspecialchars($observacoes); ?></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="registrar_caixa" name="registrar_caixa" <?php echo $registrar_caixa ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="registrar_caixa">Registrar saída no caixa</label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-money-bill-wave me-2"></i>Confirmar Pagamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once('includes/footer.php'); ?>