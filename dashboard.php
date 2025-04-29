<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/header.php');

// Obter estatísticas
$clientes_total = 0;
$orcamentos_total = 0;
$orcamentos_pendentes = 0;
$orcamentos_aprovados = 0;
$orcamentos_rejeitados = 0;
$produtos_total = 0;
$estoque_baixo = 0;

// Consulta para total de clientes
$stmt = $db->query("SELECT COUNT(*) as total FROM clientes");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$clientes_total = $result['total'];

// Consulta para total de orçamentos
$stmt = $db->query("SELECT COUNT(*) as total FROM orcamentos");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$orcamentos_total = $result['total'];

// Consulta para orçamentos por status
$stmt = $db->query("SELECT status, COUNT(*) as total FROM orcamentos GROUP BY status");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    switch ($row['status']) {
        case 'pendente':
            $orcamentos_pendentes = $row['total'];
            break;
        case 'aprovado':
            $orcamentos_aprovados = $row['total'];
            break;
        case 'rejeitado':
            $orcamentos_rejeitados = $row['total'];
            break;
    }
}

// Consulta para total de produtos
$stmt = $db->query("SELECT COUNT(*) as total FROM produtos");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$produtos_total = $result['total'];

// Consulta para produtos com estoque baixo
$stmt = $db->query("SELECT COUNT(*) as total FROM produtos WHERE estoque_atual <= estoque_minimo AND estoque_minimo > 0");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$estoque_baixo = $result['total'];

// Obter últimos orçamentos
$stmt = $db->query("SELECT o.*, c.nome as cliente_nome 
                     FROM orcamentos o
                     LEFT JOIN clientes c ON o.cliente_id = c.id
                     ORDER BY o.data_criacao DESC
                     LIMIT 5");
$ultimos_orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter produtos mais vendidos (baseado nas movimentações de estoque)
$stmt = $db->query("SELECT p.id, p.descricao, p.unidade, SUM(m.quantidade) as total_vendido, 
                   COUNT(DISTINCT m.orcamento_id) as total_orcamentos 
                   FROM estoque_movimentacoes m
                   JOIN produtos p ON m.produto_id = p.id
                   WHERE m.tipo = 'saida' AND m.orcamento_id IS NOT NULL
                   GROUP BY p.id, p.descricao, p.unidade
                   ORDER BY total_vendido DESC
                   LIMIT 5");
$produtos_mais_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-12">
        <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
        <p class="text-muted">Bem-vindo ao sistema de gestão de orçamentos para calhas.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card h-100">
            <div class="card-body text-center">
                <i class="fas fa-users dashboard-icon mb-3"></i>
                <h5 class="card-title">Clientes</h5>
                <h3 class="card-text"><?php echo $clientes_total; ?></h3>
                <p class="card-text text-muted">Clientes cadastrados</p>
                <a href="clientes.php" class="btn btn-outline-primary btn-sm">Gerenciar Clientes</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card h-100">
            <div class="card-body text-center">
                <i class="fas fa-file-invoice-dollar dashboard-icon mb-3"></i>
                <h5 class="card-title">Orçamentos</h5>
                <h3 class="card-text"><?php echo $orcamentos_total; ?></h3>
                <p class="card-text text-muted">
                    <span class="text-warning"><?php echo $orcamentos_pendentes; ?> pendentes</span> | 
                    <span class="text-success"><?php echo $orcamentos_aprovados; ?> aprovados</span>
                </p>
                <a href="orcamentos.php" class="btn btn-outline-primary btn-sm">Gerenciar Orçamentos</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card h-100">
            <div class="card-body text-center">
                <i class="fas fa-boxes dashboard-icon mb-3"></i>
                <h5 class="card-title">Produtos</h5>
                <h3 class="card-text"><?php echo $produtos_total; ?></h3>
                <?php if ($estoque_baixo > 0): ?>
                    <p class="card-text text-danger"><?php echo $estoque_baixo; ?> produtos com estoque baixo</p>
                <?php else: ?>
                    <p class="card-text text-muted">Todos os estoques estão OK</p>
                <?php endif; ?>
                <a href="produtos.php" class="btn btn-outline-primary btn-sm">Gerenciar Produtos</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card h-100">
            <div class="card-body text-center">
                <i class="fas fa-warehouse dashboard-icon mb-3"></i>
                <h5 class="card-title">Estoque</h5>
                <p class="card-text">Gerencie entradas e saídas do estoque</p>
                <div class="btn-group w-100">
                    <a href="estoque.php" class="btn btn-outline-primary btn-sm">Visualizar</a>
                    <a href="estoque_movimentacoes.php" class="btn btn-outline-secondary btn-sm">Movimentações</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-file-invoice-dollar me-1"></i> Últimos Orçamentos
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Data</th>
                                <th>Valor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ultimos_orcamentos) > 0): ?>
                                <?php foreach ($ultimos_orcamentos as $orcamento): ?>
                                    <tr>
                                        <td><a href="orcamento_visualizar.php?id=<?php echo $orcamento['id']; ?>"><?php echo $orcamento['numero']; ?></a></td>
                                        <td><?php echo $orcamento['cliente_nome']; ?></td>
                                        <td><?php echo dataParaBr($orcamento['data_criacao']); ?></td>
                                        <td><?php echo formataValor($orcamento['valor_total']); ?></td>
                                        <td>
                                            <span class="status-box status-<?php echo $orcamento['status']; ?>">
                                                <?php echo ucfirst($orcamento['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Nenhum orçamento cadastrado ainda.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="orcamentos.php" class="btn btn-sm btn-outline-primary">Ver todos os orçamentos</a>
                <a href="orcamento_form.php" class="btn btn-sm btn-primary float-end">
                    <i class="fas fa-plus-circle me-1"></i> Novo Orçamento
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-exclamation-triangle me-1"></i> Produtos com Estoque Baixo
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Estoque Atual</th>
                                <th>Estoque Mínimo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $db->query("SELECT * FROM produtos 
                                               WHERE estoque_atual <= estoque_minimo 
                                               AND estoque_minimo > 0
                                               ORDER BY (estoque_atual / CASE WHEN estoque_minimo = 0 THEN 1 ELSE estoque_minimo END) ASC
                                               LIMIT 5");
                            $produtos_estoque_baixo = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (count($produtos_estoque_baixo) > 0):
                            ?>
                                <?php foreach ($produtos_estoque_baixo as $produto): ?>
                                    <tr class="<?php echo ($produto['estoque_atual'] <= $produto['estoque_minimo'] / 2) ? 'estoque-critico' : 'estoque-baixo'; ?>">
                                        <td><?php echo $produto['descricao']; ?></td>
                                        <td><?php echo $produto['estoque_atual'] . ' ' . $produto['unidade']; ?></td>
                                        <td><?php echo $produto['estoque_minimo'] . ' ' . $produto['unidade']; ?></td>
                                        <td>
                                            <a href="estoque_entrada.php?produto_id=<?php echo $produto['id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-plus-circle"></i> Entrada
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">Não há produtos com estoque baixo.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="relatorios_estoque_baixo.php" class="btn btn-sm btn-outline-primary">Ver relatório completo</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-chart-line me-1"></i> Produtos Mais Vendidos
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Quantidade</th>
                                <th>Orçamentos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($produtos_mais_vendidos) > 0): ?>
                                <?php foreach ($produtos_mais_vendidos as $produto): ?>
                                    <tr>
                                        <td><?php echo $produto['descricao']; ?></td>
                                        <td><?php echo $produto['total_vendido'] . ' ' . $produto['unidade']; ?></td>
                                        <td><?php echo $produto['total_orcamentos']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">Não há dados de produtos vendidos.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="relatorios_produtos_vendidos.php" class="btn btn-sm btn-outline-primary">Ver relatório completo</a>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-tools me-1"></i> Ações Rápidas
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <a href="orcamento_form.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle me-1"></i> Novo Orçamento
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="cliente_form.php" class="btn btn-success w-100">
                            <i class="fas fa-user-plus me-1"></i> Novo Cliente
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="produto_form.php" class="btn btn-info text-white w-100">
                            <i class="fas fa-box-open me-1"></i> Novo Produto
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="estoque_entrada.php" class="btn btn-warning text-dark w-100">
                            <i class="fas fa-arrow-alt-circle-down me-1"></i> Entrada no Estoque
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once('includes/footer.php'); ?>