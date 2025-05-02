<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
verificarAutenticacao();

// Inicializar variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$data_estorno = date('Y-m-d');
$observacoes = '';
$registrar_caixa = true;
$mensagem = '';
$conta = null;
$forma_pagamento = '';

// Verificar se a conta existe e é válida para estorno
if ($id > 0) {
    $stmt = $db->prepare("SELECT c.*, p.valor as valor_ultimo_pagamento, p.forma_pagamento, p.data_pagamento, p.caixa_id 
                          FROM contas_pagar c 
                          LEFT JOIN pagamentos_contas p ON p.conta_id = c.id 
                          WHERE c.id = ? AND (c.status = 'pago' OR c.status = 'pago_parcial') 
                          ORDER BY p.id DESC LIMIT 1");
    $stmt->execute([$id]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conta) {
        $mensagem = alerta('Conta não encontrada ou não está paga.', 'danger');
    }
} else {
    header('Location: contas_pagar.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $conta) {
    $data_estorno = $_POST['data_estorno'];
    $observacoes = trim($_POST['observacoes']);
    $registrar_caixa = isset($_POST['registrar_caixa']);
    // Usar o valor do último pagamento registrado para o estorno
    $valor_estorno = floatval($conta['valor_ultimo_pagamento']);
    $forma_pagamento = $conta['forma_pagamento'];
    
    // Validar campos obrigatórios
    if (empty($data_estorno)) {
        $mensagem = alerta('Por favor, preencha a data do estorno.', 'danger');
    } else {
        try {
            $db->beginTransaction();
            
            // 1. Atualizar o status da conta para pendente
            $stmt = $db->prepare("UPDATE contas_pagar SET 
                status = 'pendente', 
                data_pagamento = NULL, 
                valor_pago = 0,
                forma_pagamento = NULL,
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?");
                
            $stmt->execute([$id]);
            
            // 2. Registrar o estorno na tabela de estornos
            $stmt = $db->prepare("INSERT INTO estornos_pagamentos 
                (conta_id, data_estorno, valor, observacoes, usuario_id) 
                VALUES (?, ?, ?, ?, ?)");
                
            $stmt->execute([
                $id,
                $data_estorno,
                $valor_estorno,
                $observacoes,
                $_SESSION['usuario_id']
            ]);
            
            $estorno_id = $db->lastInsertId();
            
            // 3. Registrar entrada no caixa, se solicitado (estorno = entrada)
            if ($registrar_caixa) {
                $descricao_caixa = "Estorno de pagamento da conta: {$conta['descricao']}";
                if (!empty($conta['fornecedor'])) {
                    $descricao_caixa .= " - Fornecedor: {$conta['fornecedor']}";
                }
                
                $stmt = $db->prepare("INSERT INTO caixa 
                    (tipo, descricao, valor, data_operacao, forma_pagamento, observacoes, conta_pagar_id, estorno_id, usuario_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                $stmt->execute([
                    'entrada',
                    $descricao_caixa,
                    $valor_estorno,
                    $data_estorno,
                    $forma_pagamento,
                    $observacoes,
                    $id,
                    $estorno_id,
                    $_SESSION['usuario_id']
                ]);
            }
            
            $db->commit();
            
            // Redirecionar para a lista de contas
            header('Location: contas_pagar.php?mensagem=estornado');
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $mensagem = alerta('Erro ao estornar pagamento: ' . $e->getMessage(), 'danger');
        }
    }
}

// Incluir o cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Estornar Pagamento de Conta</h1>
    <a href="contas_pagar.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<?php if ($conta): ?>
<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Atenção: Estorno de Pagamento</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>Você está prestes a estornar o pagamento da conta abaixo. Isto irá marcar a conta como pendente novamente e, se selecionado, registrará uma entrada no caixa.
        </div>
        
        <h5 class="mt-4 mb-3">Detalhes da Conta</h5>
        <div class="row mb-4">
            <div class="col-md-6">
                <p><strong>Descrição:</strong> <?php echo $conta['descricao']; ?></p>
                <p><strong>Fornecedor:</strong> <?php echo $conta['fornecedor'] ?: 'Não informado'; ?></p>
                <p><strong>Valor Original:</strong> <?php echo formataValor($conta['valor']); ?></p>
                <p><strong>Valor Pago Total:</strong> <?php echo formataValor($conta['valor_pago']); ?></p>
                <p><strong>Valor do Estorno:</strong> <?php echo formataValor($conta['valor_ultimo_pagamento']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Vencimento:</strong> <?php echo dataParaBr($conta['data_vencimento']); ?></p>
                <p><strong>Data de Pagamento:</strong> <?php echo dataParaBr($conta['data_pagamento']); ?></p>
                <p><strong>Forma de Pagamento:</strong> <?php echo formaPagamentoParaTexto($conta['forma_pagamento']); ?></p>
                <p><strong>Categoria:</strong> <?php echo $conta['categoria'] ?: 'Não categorizado'; ?></p>
            </div>
        </div>
        
        <form method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="data_estorno" class="form-label">Data do Estorno <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="data_estorno" name="data_estorno" value="<?php echo $data_estorno; ?>" required>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="registrar_caixa" name="registrar_caixa" value="1" <?php echo $registrar_caixa ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="registrar_caixa">
                            Registrar entrada no caixa (recomendado)
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3" id="forma-pagamento-container" style="display:<?php echo $registrar_caixa ? 'flex' : 'none'; ?>">
                <div class="col-md-6">
                    <label for="forma_pagamento" class="form-label">Forma de Pagamento do Estorno <span class="text-danger">*</span></label>
                    <select class="form-select" id="forma_pagamento" name="forma_pagamento" <?php echo $registrar_caixa ? 'required' : ''; ?>>
                        <option value="">Selecione</option>
                        <option value="dinheiro" <?php echo ($forma_pagamento == 'dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                        <option value="pix" <?php echo ($forma_pagamento == 'pix') ? 'selected' : ''; ?>>PIX</option>
                        <option value="transferencia" <?php echo ($forma_pagamento == 'transferencia') ? 'selected' : ''; ?>>Transferência</option>
                        <option value="cartao" <?php echo ($forma_pagamento == 'cartao') ? 'selected' : ''; ?>>Cartão</option>
                        <option value="cheque" <?php echo ($forma_pagamento == 'cheque') ? 'selected' : ''; ?>>Cheque</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Motivo do Estorno / Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $observacoes; ?></textarea>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="contas_pagar.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja estornar este pagamento?')">
                    <i class="fas fa-undo-alt me-2"></i>Confirmar Estorno
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const registrarCaixaCheckbox = document.getElementById('registrar_caixa');
    const formaPagamentoContainer = document.getElementById('forma-pagamento-container');
    const formaPagamentoSelect = document.getElementById('forma_pagamento');
    
    // Atualizar o required e a exibição quando o checkbox mudar
    registrarCaixaCheckbox.addEventListener('change', function() {
        if (this.checked) {
            formaPagamentoContainer.style.display = 'flex';
            formaPagamentoSelect.required = true;
        } else {
            formaPagamentoContainer.style.display = 'none';
            formaPagamentoSelect.required = false;
        }
    });
});
</script>

<?php require_once('includes/footer.php'); ?>