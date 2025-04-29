<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/header.php');

// Obter estatísticas básicas
$db_conn = $db->query("SELECT 
    (SELECT COUNT(*) FROM clientes) as total_clientes,
    (SELECT COUNT(*) FROM produtos) as total_produtos,
    (SELECT COUNT(*) FROM orcamentos) as total_orcamentos,
    (SELECT COUNT(*) FROM orcamentos WHERE status = 'pendente') as orcamentos_pendentes,
    (SELECT COUNT(*) FROM orcamentos WHERE status = 'aprovado') as orcamentos_aprovados,
    (SELECT COUNT(*) FROM orcamentos WHERE status = 'rejeitado') as orcamentos_rejeitados");

$estatisticas = $db_conn->fetch(PDO::FETCH_ASSOC);

// Obter orçamentos recentes
$orcamentos = $db->query("SELECT o.*, c.nome as cliente_nome 
                          FROM orcamentos o 
                          LEFT JOIN clientes c ON o.cliente_id = c.id 
                          ORDER BY o.data_criacao DESC 
                          LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="display-5"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
        <p class="lead">Bem-vindo ao sistema de orçamentos para calhas.</p>
        <hr>
    </div>
</div>

<!-- Cards de estatísticas -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h5 class="card-title text-primary"><i class="fas fa-users fa-2x"></i></h5>
                <h2 class="mb-0"><?php echo $estatisticas['total_clientes']; ?></h2>
                <p class="card-text">Clientes</p>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="clientes.php" class="btn btn-sm btn-outline-primary">Ver clientes</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h5 class="card-title text-primary"><i class="fas fa-boxes fa-2x"></i></h5>
                <h2 class="mb-0"><?php echo $estatisticas['total_produtos']; ?></h2>
                <p class="card-text">Produtos</p>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="produtos.php" class="btn btn-sm btn-outline-primary">Ver produtos</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h5 class="card-title text-success"><i class="fas fa-check-circle fa-2x"></i></h5>
                <h2 class="mb-0"><?php echo $estatisticas['orcamentos_aprovados']; ?></h2>
                <p class="card-text">Orçamentos Aprovados</p>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="orcamentos.php?status=aprovado" class="btn btn-sm btn-outline-success">Ver aprovados</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card h-100 text-center">
            <div class="card-body">
                <h5 class="card-title text-warning"><i class="fas fa-clock fa-2x"></i></h5>
                <h2 class="mb-0"><?php echo $estatisticas['orcamentos_pendentes']; ?></h2>
                <p class="card-text">Orçamentos Pendentes</p>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="orcamentos.php?status=pendente" class="btn btn-sm btn-outline-warning">Ver pendentes</a>
            </div>
        </div>
    </div>
</div>

<!-- Ações rápidas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-bolt me-2"></i>Ações Rápidas
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <a href="cliente_form.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-user-plus me-2"></i>Novo Cliente
                        </a>
                    </div>
                    <div class="col-md-4 mb-2">
                        <a href="produto_form.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-box-open me-2"></i>Novo Produto
                        </a>
                    </div>
                    <div class="col-md-4 mb-2">
                        <a href="orcamento_form.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Novo Orçamento
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Orçamentos recentes -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-clock me-2"></i>Orçamentos Recentes
            </div>
            <div class="card-body">
                <?php if (count($orcamentos) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Cliente</th>
                                    <th>Data</th>
                                    <th>Valor Total</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orcamentos as $orcamento): ?>
                                    <tr>
                                        <td><?php echo $orcamento['numero']; ?></td>
                                        <td><?php echo $orcamento['cliente_nome']; ?></td>
                                        <td><?php echo dataParaBr($orcamento['data_criacao']); ?></td>
                                        <td><?php echo formataValor($orcamento['valor_total']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $orcamento['status'] == 'aprovado' ? 'bg-success' : 
                                                    ($orcamento['status'] == 'rejeitado' ? 'bg-danger' : 'bg-warning'); 
                                            ?>">
                                                <?php echo ucfirst($orcamento['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="orcamento_form.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="orcamento_pdf.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">Nenhum orçamento cadastrado.</p>
                <?php endif; ?>
                
                <div class="text-end mt-3">
                    <a href="orcamentos.php" class="btn btn-primary">
                        <i class="fas fa-list me-2"></i>Ver Todos os Orçamentos
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once('includes/footer.php');
?>
