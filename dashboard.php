<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Definir fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Função para obter a data formatada em português
function getDataFormatadaPtBr() {
    $dias = array('Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado');
    $meses = array('Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro');
    
    $hoje = new DateTime();
    $dia_semana = $dias[$hoje->format('w')];
    $dia = $hoje->format('d');
    $mes = $meses[(int)$hoje->format('m') - 1];
    $ano = $hoje->format('Y');
    
    return "$dia_semana, $dia de $mes de $ano";
}

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

// Consulta para contas a pagar vencidas
$stmt = $db->query("SELECT COUNT(*) as total FROM contas_pagar WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$contas_vencidas = $result['total'];

// Consulta para contas a pagar a vencer nos próximos 7 dias
$stmt = $db->query("SELECT COUNT(*) as total FROM contas_pagar WHERE status = 'pendente' AND data_vencimento BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '7 days'");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$contas_a_vencer = $result['total'];

// Consulta para orçamentos que precisam de acompanhamento (pendentes há mais de 5 dias)
$stmt = $db->query("SELECT COUNT(*) as total FROM orcamentos WHERE status = 'pendente' AND data_criacao < CURRENT_DATE - INTERVAL '5 days'");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$orcamentos_sem_retorno = $result['total'];

// Consulta para resumo financeiro
// Código para resumo financeiro removido conforme solicitado

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

<div class="dashboard-welcome">
    <div class="row align-items-center">
        <div class="col-md-7">
            <h1><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
            <p>Bem-vindo ao sistema de gestão de orçamentos e vendas</p>
            <div class="user-welcome">
                <i class="fas fa-user-circle me-1"></i> Olá, <?php echo $_SESSION['usuario']['nome']; ?> | 
                <span class="badge bg-light text-dark">
                    <?php echo ucfirst($_SESSION['usuario']['nivel']); ?>
                </span>
            </div>
        </div>
        <div class="col-md-5">
            <div class="date-info">
                <h3 id="relogio">00:00:00</h3>
                <div class="current-date"><?php echo getDataFormatadaPtBr(); ?></div>
                
                <script>
                function atualizarRelogio() {
                    const agora = new Date();
                    const horas = agora.getHours().toString().padStart(2, '0');
                    const minutos = agora.getMinutes().toString().padStart(2, '0');
                    const segundos = agora.getSeconds().toString().padStart(2, '0');
                    document.getElementById('relogio').textContent = `${horas}:${minutos}:${segundos}`;
                }
                
                // Executar imediatamente e depois a cada segundo
                atualizarRelogio();
                setInterval(atualizarRelogio, 1000);
                </script>
            </div>
        </div>
    </div>
</div>

<!-- Painel de Alertas -->
<?php if ($contas_vencidas > 0 || $contas_a_vencer > 0 || $orcamentos_sem_retorno > 0 || $estoque_baixo > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert-card card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Alertas Importantes</h5>
            </div>
            <div class="card-body py-4">
                <div class="row">
                    <?php if ($contas_vencidas > 0): ?>
                    <div class="col-md-6 mb-3">
                        <div class="alert-item alert-item-critical">
                            <h5><i class="fas fa-exclamation-circle me-2"></i>Contas Vencidas</h5>
                            <p>Você tem <strong><?php echo $contas_vencidas; ?> conta(s)</strong> vencida(s) aguardando pagamento.</p>
                            <a href="contas_pagar.php?status=pendente&vencidas=1" class="btn btn-sm btn-outline-danger mt-2">
                                <i class="fas fa-money-bill-wave me-1"></i>Ver contas vencidas
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($contas_a_vencer > 0): ?>
                    <div class="col-md-6 mb-3">
                        <div class="alert-item alert-item-warning">
                            <h5><i class="fas fa-clock me-2"></i>Contas a Vencer</h5>
                            <p>Você tem <strong><?php echo $contas_a_vencer; ?> conta(s)</strong> a vencer nos próximos 7 dias.</p>
                            <a href="contas_pagar.php?status=pendente&a_vencer=1" class="btn btn-sm btn-outline-warning mt-2">
                                <i class="fas fa-money-bill-wave me-1"></i>Ver contas a vencer
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($orcamentos_sem_retorno > 0): ?>
                    <div class="col-md-6 mb-3">
                        <div class="alert-item alert-item-info">
                            <h5><i class="fas fa-file-invoice-dollar me-2"></i>Orçamentos sem Retorno</h5>
                            <p>Você tem <strong><?php echo $orcamentos_sem_retorno; ?> orçamento(s)</strong> pendente(s) há mais de 5 dias.</p>
                            <a href="orcamentos.php?status=pendente&antigos=1" class="btn btn-sm btn-outline-info mt-2">
                                <i class="fas fa-search me-1"></i>Ver orçamentos pendentes
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($estoque_baixo > 0): ?>
                    <div class="col-md-6 mb-3">
                        <div class="alert-item alert-item-neutral">
                            <h5><i class="fas fa-boxes me-2"></i>Estoque Baixo</h5>
                            <p>Você tem <strong><?php echo $estoque_baixo; ?> produto(s)</strong> com estoque abaixo do mínimo.</p>
                            <a href="relatorios_estoque_baixo.php" class="btn btn-sm btn-outline-secondary mt-2">
                                <i class="fas fa-search me-1"></i>Ver produtos com estoque baixo
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Painel de Acesso Rápido -->
<div class="row mb-4">
    <div class="col-12">
        <div class="quick-access-card card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Acesso Rápido</h5>
            </div>
            <div class="card-body py-4">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-4">
                        <a href="orcamento_form.php" class="quick-access-button bg-primary text-white">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>Novo Orçamento</span>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <a href="venda_form.php" class="quick-access-button bg-success text-white">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Nova Venda Direta</span>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <a href="caixa_form.php" class="quick-access-button bg-info text-white">
                            <i class="fas fa-cash-register"></i>
                            <span>Registrar no Caixa</span>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <a href="cliente_form.php" class="quick-access-button bg-secondary text-white">
                            <i class="fas fa-user-plus"></i>
                            <span>Novo Cliente</span>
                        </a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-4">
                        <a href="conta_pagar_form.php" class="quick-access-button bg-danger text-white">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Nova Conta a Pagar</span>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <a href="estoque_entrada.php" class="quick-access-button bg-warning text-dark">
                            <i class="fas fa-truck-loading"></i>
                            <span>Entrada de Estoque</span>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <a href="relatorios_vendas.php" class="quick-access-button bg-dark text-white">
                            <i class="fas fa-chart-line"></i>
                            <span>Relatório de Vendas</span>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <a href="produto_form.php" class="quick-access-button bg-light text-dark border">
                            <i class="fas fa-box"></i>
                            <span>Novo Produto</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stat-card">
            <i class="fas fa-users stat-icon"></i>
            <div class="card-body">
                <div class="stat-title">Clientes</div>
                <div class="stat-value"><?php echo $clientes_total; ?></div>
                <div class="stat-description">Total de clientes cadastrados</div>
                <a href="clientes.php" class="stat-link btn btn-sm btn-outline-primary">
                    <i class="fas fa-user-cog me-1"></i>Gerenciar
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stat-card">
            <i class="fas fa-file-invoice-dollar stat-icon"></i>
            <div class="card-body">
                <div class="stat-title">Orçamentos</div>
                <div class="stat-value"><?php echo $orcamentos_total; ?></div>
                <div class="stat-description">
                    <?php if ($orcamentos_pendentes > 0): ?>
                        <span class="badge bg-warning text-dark me-1"><?php echo $orcamentos_pendentes; ?> pendentes</span>
                    <?php endif; ?>
                    <?php if ($orcamentos_aprovados > 0): ?>
                        <span class="badge bg-success"><?php echo $orcamentos_aprovados; ?> aprovados</span>
                    <?php endif; ?>
                </div>
                <a href="orcamentos.php" class="stat-link btn btn-sm btn-outline-primary">
                    <i class="fas fa-list me-1"></i>Gerenciar
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stat-card">
            <i class="fas fa-boxes stat-icon"></i>
            <div class="card-body">
                <div class="stat-title">Produtos</div>
                <div class="stat-value"><?php echo $produtos_total; ?></div>
                <div class="stat-description">
                    <?php if ($estoque_baixo > 0): ?>
                        <span class="badge bg-danger"><?php echo $estoque_baixo; ?> com estoque baixo</span>
                    <?php else: ?>
                        <span class="badge bg-success">Estoque regular</span>
                    <?php endif; ?>
                </div>
                <a href="produtos.php" class="stat-link btn btn-sm btn-outline-primary">
                    <i class="fas fa-box me-1"></i>Gerenciar
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-4">
        <div class="stat-card">
            <i class="fas fa-warehouse stat-icon"></i>
            <div class="card-body">
                <div class="stat-title">Gestão de Estoque</div>
                <div class="stat-description mb-2">Controle de entradas e saídas</div>
                <div class="d-grid gap-2">
                    <a href="estoque.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>Visualizar
                    </a>
                    <a href="estoque_movimentacoes.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-exchange-alt me-1"></i>Movimentações
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resumo Financeiro removido conforme solicitado pelo cliente -->

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="data-table-card">
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
                                        <td><a href="orcamento_visualizar.php?id=<?php echo $orcamento['id']; ?>" class="fw-medium"><?php echo $orcamento['numero']; ?></a></td>
                                        <td><?php echo $orcamento['cliente_nome']; ?></td>
                                        <td><span class="text-muted"><?php echo dataParaBr($orcamento['data_criacao']); ?></span></td>
                                        <td class="fw-bold"><?php echo formataValor($orcamento['valor_total']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $orcamento['status']; ?>">
                                                <?php echo ucfirst($orcamento['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3">
                                        <div class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Nenhum orçamento cadastrado ainda.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="orcamentos.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list me-1"></i> Ver todos os orçamentos
                    </a>
                    <a href="orcamento_form.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle me-1"></i> Novo Orçamento
                    </a>
                </div>
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