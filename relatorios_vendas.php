<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

$titulo = "Relatório de Vendas";
require_once('includes/header.php');

// Filtros
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
$categoria_id = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : 0;
$data_inicio = null;
$data_fim = null;
$agrupar_por = isset($_GET['agrupar_por']) ? $_GET['agrupar_por'] : 'dia';

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
        if ($agrupar_por == 'dia') {
            $agrupar_por = 'dia'; // Forçar agrupamento por dia para o mês atual
        }
        break;
    case 'trimestre':
        $data_inicio = date('Y-m-d', strtotime('-3 months'));
        $periodo_texto = 'últimos 3 meses';
        if ($agrupar_por == 'dia') {
            $agrupar_por = 'semana'; // Alterar para semana para períodos longos
        }
        break;
    case 'semestre':
        $data_inicio = date('Y-m-d', strtotime('-6 months'));
        $periodo_texto = 'últimos 6 meses';
        if ($agrupar_por == 'dia') {
            $agrupar_por = 'mes'; // Alterar para mês para períodos mais longos
        }
        break;
    case 'ano':
        $data_inicio = date('Y-01-01');
        $periodo_texto = 'ano atual';
        if ($agrupar_por == 'dia') {
            $agrupar_por = 'mes'; // Alterar para mês para períodos longos
        }
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
        // Calcular diferença de dias para sugerir agrupamento adequado
        $diff = strtotime($data_fim) - strtotime($data_inicio);
        $dias = floor($diff / (60 * 60 * 24));
        
        if ($dias > 60 && $agrupar_por == 'dia') {
            $agrupar_por = 'semana';
        }
        if ($dias > 180 && $agrupar_por == 'semana') {
            $agrupar_por = 'mes';
        }
        break;
}

// Se não for período personalizado, a data_fim será sempre hoje
if ($periodo != 'personalizado') {
    $data_fim = $hoje;
}

// Buscar categorias para o filtro
$stmt = $db->query("SELECT id, nome FROM categorias ORDER BY nome");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir consulta SQL para vendas
// Considerar vendas como orçamentos aprovados e saídas diretas do estoque com motivo "Venda"
$sql_base = "SELECT 
                DATE(m.data_movimentacao) as data,
                SUM(m.valor_total) as valor_total,
                SUM(m.quantidade) as quantidade
            FROM 
                estoque_movimentacoes m
            JOIN 
                produtos p ON m.produto_id = p.id
            LEFT JOIN 
                orcamentos o ON m.orcamento_id = o.id
            WHERE 
                (m.tipo = 'saida' AND (m.observacao LIKE '%Venda%' OR (m.orcamento_id IS NOT NULL AND o.status = 'aprovado')))
                AND DATE(m.data_movimentacao) BETWEEN :data_inicio AND :data_fim";

// Adicionar filtro de categoria se selecionado
if ($categoria_id > 0) {
    $sql_base .= " AND p.categoria_id = :categoria_id";
}

// Agrupar dados conforme selecionado
switch ($agrupar_por) {
    case 'dia':
        $sql_agrupamento = " GROUP BY DATE(m.data_criacao) ORDER BY DATE(m.data_criacao)";
        $rotulo_data = "dataParaBr";
        break;
    case 'semana':
        // PostgreSQL usa EXTRACT(WEEK FROM date) e EXTRACT(YEAR FROM date)
        $sql_agrupamento = " GROUP BY EXTRACT(YEAR FROM m.data_criacao), EXTRACT(WEEK FROM m.data_criacao) 
                             ORDER BY EXTRACT(YEAR FROM m.data_criacao), EXTRACT(WEEK FROM m.data_criacao)";
        $rotulo_data = "Semana";
        // Modificar a consulta para incluir as informações de semana/ano
        $sql_base = "SELECT 
                        EXTRACT(YEAR FROM m.data_criacao) as ano,
                        EXTRACT(WEEK FROM m.data_criacao) as semana,
                        MIN(DATE(m.data_criacao)) as data,
                        SUM(m.valor_total) as valor_total,
                        SUM(m.quantidade) as quantidade
                    FROM 
                        estoque_movimentacoes m
                    JOIN 
                        produtos p ON m.produto_id = p.id
                    LEFT JOIN 
                        orcamentos o ON m.orcamento_id = o.id
                    WHERE 
                        (m.tipo = 'saida' AND (m.observacao LIKE '%Venda%' OR (m.orcamento_id IS NOT NULL AND o.status = 'aprovado')))
                        AND DATE(m.data_criacao) BETWEEN :data_inicio AND :data_fim";
        
        if ($categoria_id > 0) {
            $sql_base .= " AND p.categoria_id = :categoria_id";
        }
        
        $sql_agrupamento = " GROUP BY EXTRACT(YEAR FROM m.data_criacao), EXTRACT(WEEK FROM m.data_criacao) 
                             ORDER BY EXTRACT(YEAR FROM m.data_criacao), EXTRACT(WEEK FROM m.data_criacao)";
        break;
    case 'mes':
        // PostgreSQL usa EXTRACT(MONTH FROM date) e EXTRACT(YEAR FROM date)
        $sql_agrupamento = " GROUP BY EXTRACT(YEAR FROM m.data_criacao), EXTRACT(MONTH FROM m.data_criacao) 
                             ORDER BY EXTRACT(YEAR FROM m.data_criacao), EXTRACT(MONTH FROM m.data_criacao)";
        $rotulo_data = "Mês";
        // Modificar a consulta para incluir as informações de mês/ano
        $sql_base = "SELECT 
                        EXTRACT(YEAR FROM m.data_criacao) as ano,
                        EXTRACT(MONTH FROM m.data_criacao) as mes,
                        MIN(DATE(m.data_criacao)) as data,
                        SUM(m.valor_total) as valor_total,
                        SUM(m.quantidade) as quantidade
                    FROM 
                        estoque_movimentacoes m
                    JOIN 
                        produtos p ON m.produto_id = p.id
                    LEFT JOIN 
                        orcamentos o ON m.orcamento_id = o.id
                    WHERE 
                        (m.tipo = 'saida' AND (m.observacao LIKE '%Venda%' OR (m.orcamento_id IS NOT NULL AND o.status = 'aprovado')))
                        AND DATE(m.data_criacao) BETWEEN :data_inicio AND :data_fim";
        
        if ($categoria_id > 0) {
            $sql_base .= " AND p.categoria_id = :categoria_id";
        }
        
        $sql_agrupamento = " GROUP BY EXTRACT(YEAR FROM m.data_criacao), EXTRACT(MONTH FROM m.data_criacao) 
                             ORDER BY EXTRACT(YEAR FROM m.data_criacao), EXTRACT(MONTH FROM m.data_criacao)";
        break;
}

// Consulta SQL final
$sql = $sql_base . $sql_agrupamento;

$stmt = $db->prepare($sql);
$stmt->bindParam(':data_inicio', $data_inicio);
$stmt->bindParam(':data_fim', $data_fim);

if ($categoria_id > 0) {
    $stmt->bindParam(':categoria_id', $categoria_id);
}

$stmt->execute();
$vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar dados para o gráfico
$grafico_labels = [];
$grafico_valores = [];
$total_vendas = 0;
$media_diaria = 0;
$maior_valor = 0;
$menor_valor = PHP_FLOAT_MAX;
$dia_maior_venda = '';
$dia_menor_venda = '';

foreach ($vendas as $venda) {
    $total_vendas += $venda['valor_total'];
    
    // Identificar maior e menor venda
    if ($venda['valor_total'] > $maior_valor) {
        $maior_valor = $venda['valor_total'];
        $dia_maior_venda = $venda['data'];
    }
    
    if ($venda['valor_total'] > 0 && $venda['valor_total'] < $menor_valor) {
        $menor_valor = $venda['valor_total'];
        $dia_menor_venda = $venda['data'];
    }
    
    // Gerar rótulos adequados conforme agrupamento
    if ($agrupar_por == 'dia') {
        $grafico_labels[] = dataParaBr($venda['data']);
    } elseif ($agrupar_por == 'semana') {
        $grafico_labels[] = 'Semana ' . $venda['semana'] . '/' . $venda['ano'];
    } elseif ($agrupar_por == 'mes') {
        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $grafico_labels[] = $meses[intval($venda['mes']) - 1] . '/' . $venda['ano'];
    }
    
    $grafico_valores[] = $venda['valor_total'];
}

// Calcular média diária (se houver vendas)
if (count($vendas) > 0) {
    // Diferença em dias entre data_inicio e data_fim
    $diff = strtotime($data_fim) - strtotime($data_inicio);
    $total_dias = floor($diff / (60 * 60 * 24)) + 1; // +1 para incluir o dia atual
    $media_diaria = $total_vendas / $total_dias;
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-chart-line me-2"></i>Relatório de Vendas</h1>
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
            <div class="col-md-3 mb-3">
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
            <div class="col-md-2 mb-3">
                <label for="categoria_id" class="form-label">Categoria</label>
                <select name="categoria_id" id="categoria_id" class="form-select">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?php echo $categoria['id']; ?>" <?php echo $categoria_id == $categoria['id'] ? 'selected' : ''; ?>>
                            <?php echo $categoria['nome']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mb-3">
                <label for="agrupar_por" class="form-label">Agrupar por</label>
                <select name="agrupar_por" id="agrupar_por" class="form-select">
                    <option value="dia" <?php echo $agrupar_por == 'dia' ? 'selected' : ''; ?>>Dia</option>
                    <option value="semana" <?php echo $agrupar_por == 'semana' ? 'selected' : ''; ?>>Semana</option>
                    <option value="mes" <?php echo $agrupar_por == 'mes' ? 'selected' : ''; ?>>Mês</option>
                </select>
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
    Relatório de vendas do <?php echo $periodo_texto; ?>, agrupado por <?php echo $agrupar_por; ?>.
    <?php if ($categoria_id > 0): ?>
        <?php foreach ($categorias as $categoria): ?>
            <?php if ($categoria['id'] == $categoria_id): ?>
                Categoria: <strong><?php echo $categoria['nome']; ?></strong>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <h5>Total de Vendas</h5>
                <h2 class="mb-0"><?php echo formataValor($total_vendas); ?></h2>
                <div class="text-white-50">no período</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <h5>Média Diária</h5>
                <h2 class="mb-0"><?php echo formataValor($media_diaria); ?></h2>
                <div class="text-white-50">por dia no período</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center">
                <h5>Maior Venda</h5>
                <h2 class="mb-0"><?php echo formataValor($maior_valor); ?></h2>
                <div class="text-white-50"><?php echo !empty($dia_maior_venda) ? dataParaBr($dia_maior_venda) : ''; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning h-100">
            <div class="card-body text-center">
                <h5>Menor Venda</h5>
                <h2 class="mb-0"><?php echo $menor_valor < PHP_FLOAT_MAX ? formataValor($menor_valor) : '0,00'; ?></h2>
                <div class="text-muted"><?php echo !empty($dia_menor_venda) ? dataParaBr($dia_menor_venda) : ''; ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (count($vendas) > 0): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Evolução de Vendas</h5>
        </div>
        <div class="card-body">
            <canvas id="vendasChart" height="300"></canvas>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Detalhamento de Vendas</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th class="text-center">Quantidade</th>
                            <th class="text-end">Valor Total</th>
                            <th class="text-end">% do Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendas as $index => $venda): ?>
                            <tr>
                                <td>
                                    <?php if ($agrupar_por == 'dia'): ?>
                                        <?php echo dataParaBr($venda['data']); ?>
                                    <?php elseif ($agrupar_por == 'semana'): ?>
                                        Semana <?php echo $venda['semana']; ?>/<?php echo $venda['ano']; ?>
                                    <?php elseif ($agrupar_por == 'mes'): ?>
                                        <?php 
                                            $meses = [
                                                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 
                                                4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
                                                7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
                                                10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
                                            ];
                                            echo $meses[intval($venda['mes'])] . ' de ' . $venda['ano'];
                                        ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo number_format($venda['quantidade'], 2, ',', '.'); ?></td>
                                <td class="text-end"><?php echo formataValor($venda['valor_total']); ?></td>
                                <td class="text-end">
                                    <?php 
                                        $percentual = ($total_vendas > 0) ? ($venda['valor_total'] / $total_vendas) * 100 : 0;
                                        echo number_format($percentual, 2, ',', '.') . '%';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th class="text-center">
                                <?php 
                                    $total_quantidade = array_sum(array_column($vendas, 'quantidade'));
                                    echo number_format($total_quantidade, 2, ',', '.'); 
                                ?>
                            </th>
                            <th class="text-end"><?php echo formataValor($total_vendas); ?></th>
                            <th class="text-end">100,00%</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>Não há registros de vendas para o período selecionado.
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
    .bg-primary, .bg-success, .bg-info, .bg-warning {
        background-color: #f8f9fa !important;
        color: #000 !important;
    }
    .text-white, .text-white-50 {
        color: #000 !important;
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
    <?php if (count($vendas) > 0): ?>
    // Dados para o gráfico de vendas
    const labels = <?php echo json_encode($grafico_labels); ?>;
    const valores = <?php echo json_encode($grafico_valores); ?>;
    
    // Criar gráfico de vendas
    const ctx = document.getElementById('vendasChart').getContext('2d');
    const vendasChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Valor de Vendas (R$)',
                data: valores,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                tension: 0.1,
                fill: true,
                pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
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