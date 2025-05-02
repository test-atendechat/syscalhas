<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

$titulo = "Relatório de Produtos Mais Vendidos";
require_once('includes/header.php');

// Definir período de análise
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
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

// Consultar produtos mais vendidos (baseado em movimentações do tipo 'saida' não vinculadas a orçamentos rejeitados)
$sql = "SELECT 
            p.id,
            p.codigo,
            p.descricao,
            p.unidade,
            p.valor_unitario,
            c.nome as categoria,
            SUM(m.quantidade) as quantidade_total,
            SUM(m.valor_total) as valor_total,
            COUNT(m.id) as total_movimentacoes
        FROM 
            estoque_movimentacoes m
        JOIN 
            produtos p ON m.produto_id = p.id
        LEFT JOIN 
            categorias c ON p.categoria_id = c.id
        LEFT JOIN 
            orcamentos o ON m.orcamento_id = o.id
        WHERE 
            m.tipo = 'saida'
            AND (m.orcamento_id IS NULL OR o.status != 'rejeitado')
            AND DATE(m.data_movimentacao) BETWEEN :data_inicio AND :data_fim
        GROUP BY 
            p.id, p.codigo, p.descricao, p.unidade, p.valor_unitario, c.nome
        ORDER BY 
            quantidade_total DESC
        LIMIT 20";

$stmt = $db->prepare($sql);
$stmt->bindParam(':data_inicio', $data_inicio);
$stmt->bindParam(':data_fim', $data_fim);
$stmt->execute();
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dados para o gráfico
$labels = [];
$data = [];
$colors = [];

foreach (array_slice($produtos, 0, 10) as $index => $produto) {
    $labels[] = $produto['descricao'];
    $data[] = $produto['quantidade_total'];
    
    // Array de cores para o gráfico
    $baseColors = [
        'rgba(75, 192, 192, 0.6)',
        'rgba(54, 162, 235, 0.6)',
        'rgba(153, 102, 255, 0.6)',
        'rgba(255, 159, 64, 0.6)',
        'rgba(255, 99, 132, 0.6)',
        'rgba(255, 206, 86, 0.6)',
        'rgba(199, 199, 199, 0.6)',
        'rgba(83, 102, 255, 0.6)',
        'rgba(40, 159, 64, 0.6)',
        'rgba(210, 99, 132, 0.6)'
    ];
    
    $colors[] = $baseColors[$index % count($baseColors)];
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-trophy me-2"></i>Relatório de Produtos Mais Vendidos</h1>
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
            <div class="col-md-3 mb-3 custom-date" style="display: <?php echo $periodo == 'personalizado' ? 'block' : 'none'; ?>">
                <label for="data_inicio" class="form-label">Data Inicial</label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control" value="<?php echo $data_inicio; ?>">
            </div>
            <div class="col-md-3 mb-3 custom-date" style="display: <?php echo $periodo == 'personalizado' ? 'block' : 'none'; ?>">
                <label for="data_fim" class="form-label">Data Final</label>
                <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?php echo $data_fim; ?>">
            </div>
            <div class="col-md-3 mb-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>Este relatório mostra os produtos mais vendidos no <?php echo $periodo_texto; ?> (<?php echo dataParaBr($data_inicio); ?> até <?php echo dataParaBr($data_fim); ?>).
</div>

<?php if (count($produtos) > 0): ?>
    <div class="row mb-4">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Top 10 Produtos Mais Vendidos</h5>
                </div>
                <div class="card-body">
                    <canvas id="produtosChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Ranking de Produtos</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center">Posição</th>
                                    <th>Produto</th>
                                    <th>Categoria</th>
                                    <th class="text-center">Unidade</th>
                                    <th class="text-center">Qtd. Vendida</th>
                                    <th class="text-end">Valor Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $index => $produto): ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php 
                                            if ($index < 3) {
                                                $medalhaClass = ['gold', 'silver', 'bronze'][$index];
                                                echo "<span class='medal $medalhaClass'>" . ($index + 1) . "</span>";
                                            } else {
                                                echo ($index + 1);
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo "{$produto['codigo']} - {$produto['descricao']}"; ?></td>
                                        <td><?php echo $produto['categoria'] ?? 'Sem categoria'; ?></td>
                                        <td class="text-center"><?php echo $produto['unidade']; ?></td>
                                        <td class="text-center"><?php echo number_format($produto['quantidade_total'], 2, ',', '.'); ?></td>
                                        <td class="text-end"><?php echo formataValor($produto['valor_total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>Não há registros de vendas para o período selecionado.
    </div>
<?php endif; ?>

<style>
.medal {
    display: inline-block;
    width: 25px;
    height: 25px;
    line-height: 25px;
    text-align: center;
    border-radius: 50%;
    color: white;
    font-weight: bold;
}
.medal.gold {
    background-color: #FFD700;
    box-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
}
.medal.silver {
    background-color: #C0C0C0;
    box-shadow: 0 0 10px rgba(192, 192, 192, 0.7);
}
.medal.bronze {
    background-color: #CD7F32;
    box-shadow: 0 0 10px rgba(205, 127, 50, 0.7);
}

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

// Inicializar gráfico quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($produtos) > 0): ?>
    // Dados para o gráfico
    const labels = <?php echo json_encode($labels); ?>;
    const data = <?php echo json_encode($data); ?>;
    const colors = <?php echo json_encode($colors); ?>;
    
    // Criar gráfico
    const ctx = document.getElementById('produtosChart').getContext('2d');
    const produtosChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Quantidade Vendida',
                data: data,
                backgroundColor: colors,
                borderColor: colors.map(color => color.replace('0.6', '1')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php require_once('includes/footer.php'); ?>