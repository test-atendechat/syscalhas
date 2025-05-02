<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

$titulo = "Relatório de Orçamentos";
require_once('includes/header.php');

// Filtros
$status = isset($_GET['status']) ? $_GET['status'] : '';
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
$data_inicio = null;
$data_fim = null;

// Definir as datas de início e fim conforme o período selecionado
$hoje = date('Y-m-d');
switch ($periodo) {
    case 'semana':
        $data_inicio = date('Y-m-d', strtotime('-7 days'));
        $periodo_texto = 'últimos 7 dias';
        break;
    case 'mes':
        $data_inicio = date('Y-m-01');
        $periodo_texto = 'mês atual';
        break;
    case 'trimestre':
        $data_inicio = date('Y-m-d', strtotime('-3 months'));
        $periodo_texto = 'últimos 3 meses';
        break;
    case 'semestre':
        $data_inicio = date('Y-m-d', strtotime('-6 months'));
        $periodo_texto = 'últimos 6 meses';
        break;
    case 'ano':
        $data_inicio = date('Y-01-01');
        $periodo_texto = 'ano atual';
        break;
    case 'personalizado':
        if (isset($_GET['data_inicio']) && !empty($_GET['data_inicio'])) {
            $data_inicio = date('Y-m-d', strtotime($_GET['data_inicio']));
        }
        if (isset($_GET['data_fim']) && !empty($_GET['data_fim'])) {
            $data_fim = date('Y-m-d', strtotime($_GET['data_fim']));
        } else {
            $data_fim = $hoje;
        }
        $periodo_texto = 'período personalizado de ' . dataParaBr($data_inicio) . ' até ' . dataParaBr($data_fim);
        break;
}

// Se não for período personalizado, a data_fim será sempre hoje
if ($periodo != 'personalizado') {
    $data_fim = $hoje;
}

// Buscar clientes para o filtro
$stmt = $db->query("SELECT id, nome, cpf_cnpj FROM clientes ORDER BY nome");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir a consulta SQL com base nos filtros
$sql = "SELECT 
            o.id, 
            o.numero, 
            o.status, 
            o.data_criacao, 
            o.data_validade, 
            o.valor_produtos,
            o.valor_mao_obra,
            o.valor_total,
            c.id as cliente_id,
            c.nome as cliente_nome
        FROM 
            orcamentos o
        JOIN 
            clientes c ON o.cliente_id = c.id
        WHERE 
            DATE(o.data_criacao) BETWEEN :data_inicio AND :data_fim";

// Adicionar filtro de status se selecionado
if (!empty($status)) {
    $sql .= " AND o.status = :status";
}

// Adicionar filtro de cliente se selecionado
if ($cliente_id > 0) {
    $sql .= " AND o.cliente_id = :cliente_id";
}

$sql .= " ORDER BY o.data_criacao DESC";

$stmt = $db->prepare($sql);
$stmt->bindParam(':data_inicio', $data_inicio);
$stmt->bindParam(':data_fim', $data_fim);

if (!empty($status)) {
    $stmt->bindParam(':status', $status);
}

if ($cliente_id > 0) {
    $stmt->bindParam(':cliente_id', $cliente_id);
}

$stmt->execute();
$orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totais e estatísticas
$total_orcamentos = count($orcamentos);
$total_valor = 0;
$total_aprovados = 0;
$total_rejeitados = 0;
$total_pendentes = 0;
$valor_aprovados = 0;
$valor_rejeitados = 0;
$valor_pendentes = 0;

foreach ($orcamentos as $orcamento) {
    $total_valor += $orcamento['valor_total'];
    
    switch ($orcamento['status']) {
        case 'aprovado':
            $total_aprovados++;
            $valor_aprovados += $orcamento['valor_total'];
            break;
        case 'rejeitado':
            $total_rejeitados++;
            $valor_rejeitados += $orcamento['valor_total'];
            break;
        case 'pendente':
            $total_pendentes++;
            $valor_pendentes += $orcamento['valor_total'];
            break;
    }
}

// Calcular percentuais
$percentual_aprovados = ($total_orcamentos > 0) ? ($total_aprovados / $total_orcamentos) * 100 : 0;
$percentual_rejeitados = ($total_orcamentos > 0) ? ($total_rejeitados / $total_orcamentos) * 100 : 0;
$percentual_pendentes = ($total_orcamentos > 0) ? ($total_pendentes / $total_orcamentos) * 100 : 0;

// Dados para os gráficos
$status_labels = ['Aprovados', 'Rejeitados', 'Pendentes'];
$status_data = [$total_aprovados, $total_rejeitados, $total_pendentes];
$status_colors = ['rgba(40, 167, 69, 0.6)', 'rgba(220, 53, 69, 0.6)', 'rgba(255, 193, 7, 0.6)'];
$status_borders = ['rgba(40, 167, 69, 1)', 'rgba(220, 53, 69, 1)', 'rgba(255, 193, 7, 1)'];

$valor_labels = ['Aprovados', 'Rejeitados', 'Pendentes'];
$valor_data = [$valor_aprovados, $valor_rejeitados, $valor_pendentes];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-file-invoice me-2"></i>Relatório de Orçamentos</h1>
    <div>
        <button class="btn btn-outline-secondary me-2" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Imprimir
        </button>
        <a href="dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>
</div>

<div class="card mb-4 no-print">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row">
            <div class="col-md-2 mb-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendente" <?php echo $status == 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                    <option value="aprovado" <?php echo $status == 'aprovado' ? 'selected' : ''; ?>>Aprovados</option>
                    <option value="rejeitado" <?php echo $status == 'rejeitado' ? 'selected' : ''; ?>>Rejeitados</option>
                </select>
            </div>
            <div class="col-md-3 mb-3">
                <label for="cliente_id" class="form-label">Cliente</label>
                <select name="cliente_id" id="cliente_id" class="form-select">
                    <option value="">Todos os clientes</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?php echo $cliente['id']; ?>" <?php echo $cliente_id == $cliente['id'] ? 'selected' : ''; ?>>
                            <?php echo $cliente['nome']; ?>
                            <?php if (!empty($cliente['cpf_cnpj'])): ?>
                                (<?php echo $cliente['cpf_cnpj']; ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-3">
                <label for="periodo" class="form-label">Período</label>
                <select name="periodo" id="periodo" class="form-select" onchange="toggleCustomDate()">
                    <option value="semana" <?php echo $periodo == 'semana' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                    <option value="mes" <?php echo $periodo == 'mes' ? 'selected' : ''; ?>>Mês Atual</option>
                    <option value="trimestre" <?php echo $periodo == 'trimestre' ? 'selected' : ''; ?>>Últimos 3 meses</option>
                    <option value="semestre" <?php echo $periodo == 'semestre' ? 'selected' : ''; ?>>Últimos 6 meses</option>
                    <option value="ano" <?php echo $periodo == 'ano' ? 'selected' : ''; ?>>Ano Atual</option>
                    <option value="personalizado" <?php echo $periodo == 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                </select>
            </div>
            <div class="col-md-2 mb-3 custom-date" style="display: <?php echo $periodo == 'personalizado' ? 'block' : 'none'; ?>">
                <label for="data_inicio" class="form-label">Data Inicial</label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="<?php echo $data_inicio; ?>">
            </div>
            <div class="col-md-2 mb-3 custom-date" style="display: <?php echo $periodo == 'personalizado' ? 'block' : 'none'; ?>">
                <label for="data_fim" class="form-label">Data Final</label>
                <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?php echo $data_fim; ?>">
            </div>
            <div class="col-md-1 mb-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    Mostrando orçamentos do <?php echo $periodo_texto; ?> 
    <?php if (!empty($status)): ?>
        com status <strong><?php echo ucfirst($status); ?></strong>
    <?php endif; ?>
    <?php if ($cliente_id > 0): ?>
        <?php foreach ($clientes as $cliente): ?>
            <?php if ($cliente['id'] == $cliente_id): ?>
                para o cliente <strong><?php echo $cliente['nome']; ?></strong>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-light h-100">
            <div class="card-body text-center">
                <h5>Total de Orçamentos</h5>
                <h2 class="mb-0"><?php echo $total_orcamentos; ?></h2>
                <div class="text-muted">no período</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <h5>Aprovados</h5>
                <h2 class="mb-0"><?php echo $total_aprovados; ?></h2>
                <div><?php echo number_format($percentual_aprovados, 1, ',', '.'); ?>% do total</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center">
                <h5>Rejeitados</h5>
                <h2 class="mb-0"><?php echo $total_rejeitados; ?></h2>
                <div><?php echo number_format($percentual_rejeitados, 1, ',', '.'); ?>% do total</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning h-100">
            <div class="card-body text-center">
                <h5>Pendentes</h5>
                <h2 class="mb-0"><?php echo $total_pendentes; ?></h2>
                <div><?php echo number_format($percentual_pendentes, 1, ',', '.'); ?>% do total</div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribuição por Status</h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" height="300"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Valor Total por Status</h5>
            </div>
            <div class="card-body">
                <canvas id="valorChart" height="300"></canvas>
            </div>
            <div class="card-footer">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="text-success fw-bold">Aprovados</div>
                        <div><?php echo formataValor($valor_aprovados); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-danger fw-bold">Rejeitados</div>
                        <div><?php echo formataValor($valor_rejeitados); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-warning fw-bold">Pendentes</div>
                        <div><?php echo formataValor($valor_pendentes); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (count($orcamentos) > 0): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Orçamentos</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Validade</th>
                            <th class="text-end">Valor Produtos</th>
                            <th class="text-end">Mão de Obra</th>
                            <th class="text-end">Valor Total</th>
                            <th class="text-center">Status</th>
                            <th class="text-center no-print">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orcamentos as $orcamento): ?>
                            <?php
                            switch ($orcamento['status']) {
                                case 'aprovado':
                                    $badge_class = 'success';
                                    $status_text = 'Aprovado';
                                    break;
                                case 'rejeitado':
                                    $badge_class = 'danger';
                                    $status_text = 'Rejeitado';
                                    break;
                                default:
                                    $badge_class = 'warning';
                                    $status_text = 'Pendente';
                            }
                            ?>
                            <tr>
                                <td><?php echo $orcamento['numero']; ?></td>
                                <td><?php echo $orcamento['cliente_nome']; ?></td>
                                <td><?php echo dataParaBr($orcamento['data_criacao']); ?></td>
                                <td><?php echo dataParaBr($orcamento['data_validade']); ?></td>
                                <td class="text-end"><?php echo formataValor($orcamento['valor_produtos']); ?></td>
                                <td class="text-end"><?php echo formataValor($orcamento['valor_mao_obra']); ?></td>
                                <td class="text-end"><?php echo formataValor($orcamento['valor_total']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td class="text-center no-print">
                                    <a href="orcamento_visualizar.php?id=<?php echo $orcamento['id']; ?>" class="btn btn-sm btn-primary" title="Visualizar">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="6" class="text-end">Total Geral:</th>
                            <th class="text-end"><?php echo formataValor($total_valor); ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>Não há orçamentos para os filtros selecionados.
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
    .bg-success, .bg-danger, .bg-warning, .bg-primary {
        background-color: #f8f9fa !important;
        color: #000 !important;
    }
    .text-white {
        color: #000 !important;
    }
    .badge {
        border: 1px solid #000;
        color: #000 !important;
        background-color: transparent !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Função para alternar a visibilidade dos campos de data personalizada
function toggleCustomDate() {
    const periodo = document.getElementById('periodo').value;
    const customDateInputs = document.querySelectorAll('.custom-date');
    
    customDateInputs.forEach(input => {
        input.style.display = (periodo === 'personalizado') ? 'block' : 'none';
    });
}

// Inicializar gráficos quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($total_orcamentos > 0): ?>
    // Dados para o gráfico de status
    const statusLabels = <?php echo json_encode($status_labels); ?>;
    const statusData = <?php echo json_encode($status_data); ?>;
    const statusColors = <?php echo json_encode($status_colors); ?>;
    const statusBorders = <?php echo json_encode($status_borders); ?>;
    
    // Criar gráfico de status
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: statusColors,
                borderColor: statusBorders,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const value = context.raw;
                            const percentage = Math.round((value / total) * 100);
                            return `${context.label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Dados para o gráfico de valores
    const valorLabels = <?php echo json_encode($valor_labels); ?>;
    const valorData = <?php echo json_encode($valor_data); ?>;
    
    // Criar gráfico de valores
    const valorCtx = document.getElementById('valorChart').getContext('2d');
    const valorChart = new Chart(valorCtx, {
        type: 'bar',
        data: {
            labels: valorLabels,
            datasets: [{
                label: 'Valor Total (R$)',
                data: valorData,
                backgroundColor: statusColors,
                borderColor: statusBorders,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            return `R$ ${value.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php require_once('includes/footer.php'); ?>