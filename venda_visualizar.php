<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Inicialização de variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$venda = null;
$itens = [];
$cliente = null;
$mensagem = '';

// Verificar mensagens
if (isset($_GET['mensagem'])) {
    if ($_GET['mensagem'] == 'pagamento_registrado') {
        $mensagem = alerta('Pagamento registrado com sucesso!', 'success');
    }
}

// Verificar erros
if (isset($_GET['erro'])) {
    $mensagem = alerta(urldecode($_GET['erro']), 'danger');
}

if ($id > 0) {
    // Buscar dados da venda
    $stmt = $db->prepare("SELECT v.*, u.nome as usuario_nome 
                         FROM vendas v 
                         LEFT JOIN usuarios u ON v.usuario_id = u.id 
                         WHERE v.id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $venda = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Buscar itens da venda
        $stmt = $db->prepare("SELECT * FROM vendas_itens WHERE venda_id = :venda_id");
        $stmt->bindParam(':venda_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar cliente se existir
        if ($venda['cliente_id']) {
            $stmt = $db->prepare("SELECT * FROM clientes WHERE id = :id");
            $stmt->bindParam(':id', $venda['cliente_id'], PDO::PARAM_INT);
            $stmt->execute();
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        $mensagem = alerta('Venda não encontrada!', 'danger');
    }
} else {
    header('Location: vendas.php');
    exit;
}

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-shopping-cart me-2"></i>Venda #<?php echo $venda ? $venda['numero'] : 'Não encontrada'; ?></h1>
    <a href="vendas.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<?php if ($venda): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-info-circle me-2"></i>Informações da Venda
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <h5>Dados da Venda</h5>
                    <p class="mb-1"><strong>Número:</strong> <?php echo $venda['numero']; ?></p>
                    <p class="mb-1"><strong>Data:</strong> <?php echo dataParaBr($venda['data_venda']); ?></p>
                    <p class="mb-1"><strong>Forma de Pagamento:</strong> <?php echo ucfirst($venda['forma_pagamento']); ?></p>
                    <p class="mb-1"><strong>Status:</strong> <span class="badge bg-success">Finalizada</span></p>
                    <p class="mb-1"><strong>Pagamento:</strong> 
                        <?php if (isset($venda['status_pagamento']) && $venda['status_pagamento'] == 'pago_total'): ?>
                            <span class="status-box status-pago">Pago Total</span>
                        <?php elseif (isset($venda['status_pagamento']) && $venda['status_pagamento'] == 'pago_parcial'): ?>
                            <span class="status-box status-pago-parcial">Pago Parcial</span>
                        <?php else: ?>
                            <span class="status-box status-pendente">Pendente</span>
                        <?php endif; ?>
                    </p>
                    <p class="mb-1"><strong>Vendedor:</strong> <?php echo $venda['usuario_nome']; ?></p>
                </div>
                
                <?php if ($cliente): ?>
                <div class="col-md-4">
                    <h5>Dados do Cliente</h5>
                    <p class="mb-1"><strong>Nome:</strong> <?php echo $cliente['nome']; ?></p>
                    <?php if (!empty($cliente['cpf_cnpj'])): ?>
                        <p class="mb-1"><strong>CPF/CNPJ:</strong> <?php echo $cliente['cpf_cnpj']; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($cliente['telefone'])): ?>
                        <p class="mb-1"><strong>Telefone:</strong> <?php echo $cliente['telefone']; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($cliente['email'])): ?>
                        <p class="mb-1"><strong>Email:</strong> <?php echo $cliente['email']; ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="col-md-4 text-md-end">
                    <h5>Resumo Financeiro</h5>
                    <p class="mb-1"><strong>Valor Total:</strong> <span class="fs-4 text-success"><?php echo formataValor($venda['valor_total']); ?></span></p>
                    
                    <?php
                    // Calcular valor pago e restante
                    $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa WHERE venda_id = :venda_id AND tipo = 'entrada'");
                    $stmt->bindParam(':venda_id', $venda['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
                    $total_pago = floatval($pagamentos['total_pago'] ?? 0);
                    $valor_restante = max(0, $venda['valor_total'] - $total_pago);
                    
                    // Verificar e atualizar o status de pagamento se necessário
                    $valor_total = floatval($venda['valor_total']);
                    $diferenca = abs($total_pago - $valor_total);
                    $pago_total_ou_acima = $total_pago >= $valor_total;
                    
                    // Verificar a porcentagem paga em relação ao valor total (apenas para debug)
                    $percentual_pago = ($valor_total > 0) ? ($total_pago / $valor_total) * 100 : 0;

                    // Verificar valores para debug
                    $debug_info = "Venda ID: {$venda['id']}, Valor Total: {$valor_total}, Total Pago: {$total_pago}, Percentual: {$percentual_pago}%, Diferença: {$diferenca}";
                    error_log($debug_info);
                    
                    // Verificar se o pagamento deve ser considerado como total
                    // - Pagamento total apenas quando valor pago é maior ou igual ao valor total
                    // - Sem nenhuma tolerância para pagamentos parciais
                    $pagamento_total = $pago_total_ou_acima;
                    
                    // Se o status atual não corresponde ao status real calculado, atualizar
                    $status_atual = $venda['status_pagamento'] ?? 'pendente';
                    $status_calculado = $pagamento_total ? 'pago_total' : ($total_pago > 0 ? 'pago_parcial' : 'pendente');
                    
                    if ($status_atual != $status_calculado) {
                        // Atualizar status de pagamento
                        $stmt_update = $db->prepare("UPDATE vendas SET status_pagamento = :status WHERE id = :id");
                        $stmt_update->bindParam(':status', $status_calculado);
                        $stmt_update->bindParam(':id', $venda['id'], PDO::PARAM_INT);
                        $stmt_update->execute();
                        
                        // Atualizar o status na venda atual para não precisar recarregar a página
                        $venda['status_pagamento'] = $status_calculado;
                        
                        // Log da atualização do status
                        error_log("Atualizando status de pagamento da venda ID {$venda['id']} de {$status_atual} para {$status_calculado}");
                    }
                    
                    if ($venda['status_pagamento'] == 'pago_parcial'):
                    ?>
                        <p class="mb-1"><strong>Valor Pago:</strong> <span class="text-primary"><?php echo formataValor($total_pago); ?></span></p>
                        <p class="mb-1"><strong>Valor Restante:</strong> <span class="text-danger"><?php echo formataValor($valor_restante); ?></span></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($venda['observacoes'])): ?>
            <div class="mt-3 pt-3 border-top">
                <h5>Observações</h5>
                <p class="mb-0"><?php echo nl2br($venda['observacoes']); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-boxes me-2"></i>Itens da Venda
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th class="text-center">Unidade</th>
                            <th class="text-center">Quantidade</th>
                            <th class="text-end">Valor Unitário</th>
                            <th class="text-end">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($itens) > 0): ?>
                            <?php foreach ($itens as $item): ?>
                                <tr>
                                    <td><?php echo $item['descricao']; ?></td>
                                    <td class="text-center"><?php echo $item['unidade']; ?></td>
                                    <td class="text-center"><?php echo number_format($item['quantidade'], 2, ',', '.'); ?></td>
                                    <td class="text-end"><?php echo formataValor($item['valor_unitario']); ?></td>
                                    <td class="text-end"><?php echo formataValor($item['valor_total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-3">Nenhum item encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Valor Total:</td>
                            <td class="text-end fw-bold"><?php echo formataValor($venda['valor_total']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Seção para registrar pagamento se pendente ou parcial e ainda há valor a pagar -->
    <?php
    // Calcular valor restante a pagar
    $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa WHERE venda_id = :venda_id AND tipo = 'entrada'");
    $stmt->bindParam(':venda_id', $venda['id'], PDO::PARAM_INT);
    $stmt->execute();
    $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_pago = floatval($pagamentos['total_pago'] ?? 0);
    $valor_restante = max(0, $venda['valor_total'] - $total_pago);
    
    // Mostrar opção de pagamento apenas se houver valor restante maior que zero
    if (($valor_restante > 0) && (!isset($venda['status_pagamento']) || $venda['status_pagamento'] == 'pendente' || $venda['status_pagamento'] == 'pago_parcial') && !empty($venda['cliente_id'])): ?>
    <div class="card mt-4 mb-4">
        <div class="card-header bg-warning text-dark">
            <i class="fas fa-money-bill-wave me-2"></i>Registrar Pagamento
        </div>
        <div class="card-body">
            <form method="post" action="registrar_pagamento.php">
                <input type="hidden" name="venda_id" value="<?php echo $venda['id']; ?>">
                <input type="hidden" name="venda_numero" value="<?php echo $venda['numero']; ?>">
                <input type="hidden" name="valor_total" value="<?php echo $venda['valor_total']; ?>">
                <input type="hidden" name="cliente_id" value="<?php echo $venda['cliente_id']; ?>">
                
                <?php
                // Calcular valor restante a pagar
                $stmt = $db->prepare("SELECT SUM(valor) as total_pago FROM caixa WHERE venda_id = :venda_id AND tipo = 'entrada'");
                $stmt->bindParam(':venda_id', $venda['id'], PDO::PARAM_INT);
                $stmt->execute();
                $pagamentos = $stmt->fetch(PDO::FETCH_ASSOC);
                $total_pago = floatval($pagamentos['total_pago'] ?? 0);
                $valor_restante = max(0, $venda['valor_total'] - $total_pago);
                ?>
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="forma_pagamento" name="forma_pagamento" required>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="cartao">Cartão de Crédito/Débito</option>
                            <option value="pix">PIX</option>
                            <option value="transferencia">Transferência</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="valor_pago" class="form-label">Valor Restante a Pagar</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control" id="valor_pago" name="valor_pago" value="<?php echo number_format($valor_restante, 2, ',', '.'); ?>" data-max-valor="<?php echo $valor_restante; ?>" required>
                        </div>
                        <div class="form-text text-muted">Valor máximo: R$ <?php echo number_format($valor_restante, 2, ',', '.'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <button type="submit" class="btn btn-success w-100" id="botao-registrar-pagamento">
                            <i class="fas fa-check-circle me-2"></i>Registrar Pagamento
                        </button>
                    </div>
                </div>
                <div class="form-text text-muted">Ao registrar o pagamento, será criada uma entrada no caixa e a venda será marcada como paga.</div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between mt-4">
        <a href="vendas.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar para Vendas
        </a>
        <div>
            <button class="btn btn-success" onclick="window.print();">
                <i class="fas fa-print me-2"></i>Imprimir
            </button>
            <a href="#" class="btn btn-danger ms-2" onclick="confirmarExclusao(<?php echo $venda['id']; ?>, '<?php echo $venda['numero']; ?>', 'vendas.php'); return false;">
                <i class="fas fa-trash me-2"></i>Excluir Venda
            </a>
        </div>
    </div>
<?php endif; ?>

<?php
require_once('includes/footer.php');
?>

<script>
// Validar o campo de valor pago para que não exceda o valor restante
document.addEventListener('DOMContentLoaded', function() {
    const formPagamento = document.querySelector('form[action="registrar_pagamento.php"]');
    
    if (formPagamento) {
        const valorPagoInput = document.getElementById('valor_pago');
        const maxValor = parseFloat(valorPagoInput.getAttribute('data-max-valor') || 0);
        
        // Formatar o valor quando o usuário digitar
        valorPagoInput.addEventListener('input', function(e) {
            let valor = e.target.value.replace(/\D/g, '');
            
            if (valor.length === 0) {
                e.target.value = '';
                return;
            }
            
            // Converter para formato de moeda
            valor = (parseInt(valor) / 100).toFixed(2);
            e.target.value = valor.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        });
        
        // Validar antes de enviar o formulário
        formPagamento.addEventListener('submit', function(e) {
            const valorPago = parseFloat(valorPagoInput.value.replace('.', '').replace(',', '.'));
            
            if (isNaN(valorPago) || valorPago <= 0) {
                e.preventDefault();
                alert('Por favor, insira um valor válido maior que zero.');
                return;
            }
            
            if (valorPago > maxValor) {
                e.preventDefault();
                alert(`O valor inserido (R$ ${valorPago.toFixed(2).replace('.', ',')}) é maior que o valor restante a pagar (R$ ${maxValor.toFixed(2).replace('.', ',')}).`);
                return;
            }
        });
    }
});
</script>