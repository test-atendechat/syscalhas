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