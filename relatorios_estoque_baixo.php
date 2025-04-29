<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$titulo = "Relatório de Estoque Baixo";
require_once('includes/header.php');

// Buscar produtos com estoque abaixo do mínimo configurado
$sql = "SELECT 
            p.id, 
            p.codigo, 
            p.descricao, 
            p.unidade, 
            p.estoque_atual, 
            p.estoque_minimo, 
            p.valor_unitario,
            p.custo_unitario,
            (p.valor_unitario - p.custo_unitario) as lucro,
            CASE WHEN p.custo_unitario > 0 
                THEN ((p.valor_unitario - p.custo_unitario) / p.custo_unitario) * 100 
                ELSE 0 END as margem_lucro,
            c.nome as categoria,
            (p.estoque_minimo - p.estoque_atual) as deficit
        FROM 
            produtos p
        LEFT JOIN 
            categorias c ON p.categoria_id = c.id
        WHERE 
            p.estoque_atual < p.estoque_minimo AND p.estoque_minimo > 0
        ORDER BY 
            (p.estoque_atual / p.estoque_minimo) ASC,
            p.descricao ASC";

$stmt = $db->prepare($sql);
$stmt->execute();
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-exclamation-triangle me-2"></i>Relatório de Estoque Baixo</h1>
    <div>
        <button class="btn btn-outline-secondary me-2" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Imprimir
        </button>
        <a href="dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>
</div>

<div class="alert alert-warning mb-4">
    <i class="fas fa-info-circle me-2"></i>Este relatório exibe produtos com estoque abaixo do mínimo definido para cada produto.
</div>

<?php if (count($produtos) > 0): ?>
    <div class="card mb-4">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Produtos com Estoque Baixo</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Unidade</th>
                            <th class="text-center">Estoque Atual</th>
                            <th class="text-center">Estoque Mínimo</th>
                            <th class="text-end">Valor Unitário</th>
                            <th class="text-center">Status</th>
                            <th class="text-center no-print">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): ?>
                            <?php 
                            // Calcular percentual em relação ao mínimo
                            $percentual = ($produto['estoque_minimo'] > 0) 
                                ? ($produto['estoque_atual'] / $produto['estoque_minimo']) * 100 
                                : 0;
                            
                            // Definir classe de status
                            if ($produto['estoque_atual'] <= 0) {
                                $status_class = 'danger';
                                $status_text = 'Esgotado';
                                $status_icon = 'fa-times-circle';
                            } elseif ($percentual <= 30) {
                                $status_class = 'danger';
                                $status_text = 'Crítico';
                                $status_icon = 'fa-exclamation-circle';
                            } elseif ($percentual <= 70) {
                                $status_class = 'warning';
                                $status_text = 'Baixo';
                                $status_icon = 'fa-exclamation-triangle';
                            } else {
                                $status_class = 'warning';
                                $status_text = 'Atenção';
                                $status_icon = 'fa-exclamation';
                            }
                            ?>
                            <tr>
                                <td><?php echo $produto['codigo']; ?></td>
                                <td><?php echo $produto['descricao']; ?></td>
                                <td><?php echo $produto['categoria'] ?? 'Sem categoria'; ?></td>
                                <td><?php echo $produto['unidade']; ?></td>
                                <td class="text-center"><?php echo number_format($produto['estoque_atual'], 2, ',', '.'); ?></td>
                                <td class="text-center"><?php echo number_format($produto['estoque_minimo'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo formataValor($produto['valor_unitario']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?> me-1"></i><?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td class="text-center no-print">
                                    <a href="estoque_entrada.php?produto_id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-success" title="Registrar Entrada">
                                        <i class="fas fa-plus-circle"></i>
                                    </a>
                                    <a href="produto_form.php?id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-primary" title="Editar Produto">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <span><strong>Total de produtos com estoque baixo:</strong> <?php echo count($produtos); ?></span>
                <a href="estoque_entrada.php" class="btn btn-success">
                    <i class="fas fa-arrow-alt-circle-down me-2"></i>Registrar Entrada de Estoque
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>Não há produtos com estoque baixo no momento.
    </div>
<?php endif; ?>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    .navbar, footer, .btn {
        display: none !important;
    }
    body {
        padding: 0;
        margin: 0;
    }
    .container {
        max-width: 100%;
        width: 100%;
        padding: 0;
        margin: 0;
    }
    .card {
        border: none !important;
    }
    .card-header {
        background-color: #f8f9fa !important;
        color: #000 !important;
        border-bottom: 1px solid #dee2e6 !important;
    }
    .badge {
        border: 1px solid #000;
        color: #000 !important;
        background-color: transparent !important;
    }
}
</style>

<?php require_once('includes/footer.php'); ?>