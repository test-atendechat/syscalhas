<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão - apenas administradores podem acessar este relatório
require_once('verificar_permissao.php');
if ($_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: dashboard.php?erro=' . urlencode('Você não tem permissão para acessar esta página.'));
    exit;
}

// Definir período padrão (último mês) se não especificado
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

// Buscar estatísticas dos instaladores
$sql = "SELECT 
            i.id,
            i.nome,
            i.telefone,
            COUNT(a.id) as total_agendamentos,
            COUNT(CASE WHEN a.status = 'concluido' THEN 1 END) as agendamentos_concluidos,
            COUNT(CASE WHEN a.status = 'cancelado' THEN 1 END) as agendamentos_cancelados,
            COUNT(CASE WHEN a.status = 'reagendado' THEN 1 END) as agendamentos_reagendados,
            ROUND(COUNT(CASE WHEN a.status = 'concluido' THEN 1 END) * 100.0 / NULLIF(COUNT(a.id), 0), 2) as taxa_conclusao,
            COALESCE(AVG(CASE WHEN a.status = 'concluido' THEN EXTRACT(EPOCH FROM (a.atualizado_em - a.data_agendamento))/86400 END), 0) as media_dias_conclusao
        FROM 
            instaladores i
        LEFT JOIN 
            agendamento_instaladores ai ON i.id = ai.instalador_id
        LEFT JOIN 
            agendamentos a ON ai.agendamento_id = a.id
            AND a.data_agendamento BETWEEN :data_inicio AND :data_fim
        WHERE 
            i.ativo = true
        GROUP BY 
            i.id, i.nome
        ORDER BY 
            taxa_conclusao DESC, agendamentos_concluidos DESC";

$stmt = $db->prepare($sql);
$stmt->bindParam(':data_inicio', $data_inicio);
$stmt->bindParam(':data_fim', $data_fim);
$stmt->execute();
$instaladores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular a média geral de conclusão para comparação
$media_geral_conclusao = 0;
$total_instaladores = count($instaladores);

if ($total_instaladores > 0) {
    $soma_taxas = 0;
    foreach ($instaladores as $instalador) {
        $soma_taxas += $instalador['taxa_conclusao'];
    }
    $media_geral_conclusao = $soma_taxas / $total_instaladores;
}

// Ranking e classificação dos instaladores
foreach ($instaladores as &$instalador) {
    // Classificar desempenho com base na taxa de conclusão em relação à média
    if ($instalador['taxa_conclusao'] >= ($media_geral_conclusao * 1.2)) {
        // 20% acima da média = excelente
        $instalador['classificacao'] = 'excelente';
        $instalador['classe_css'] = 'bg-success text-white';
    } elseif ($instalador['taxa_conclusao'] >= $media_geral_conclusao) {
        // Acima da média = bom
        $instalador['classificacao'] = 'bom';
        $instalador['classe_css'] = 'bg-info text-white';
    } elseif ($instalador['taxa_conclusao'] >= ($media_geral_conclusao * 0.8)) {
        // Até 20% abaixo da média = regular
        $instalador['classificacao'] = 'regular';
        $instalador['classe_css'] = 'bg-warning';
    } else {
        // Mais de 20% abaixo da média = necessita atenção
        $instalador['classificacao'] = 'necessita atenção';
        $instalador['classe_css'] = 'bg-danger text-white';
    }
}

// Calcular dados para o gráfico
$labels_grafico = [];
$dados_conclusao = [];
$dados_cancelados = [];
$cores_barras = [];

foreach ($instaladores as $instalador) {
    $labels_grafico[] = $instalador['nome'];
    $dados_conclusao[] = $instalador['taxa_conclusao'];
    $dados_cancelados[] = $instalador['agendamentos_cancelados'];
    
    // Definir cores baseadas na classificação
    if ($instalador['classificacao'] === 'excelente') {
        $cores_barras[] = 'rgba(40, 167, 69, 0.8)';
    } elseif ($instalador['classificacao'] === 'bom') {
        $cores_barras[] = 'rgba(23, 162, 184, 0.8)';
    } elseif ($instalador['classificacao'] === 'regular') {
        $cores_barras[] = 'rgba(255, 193, 7, 0.8)';
    } else {
        $cores_barras[] = 'rgba(220, 53, 69, 0.8)';
    }
}

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Relatório de Desempenho dos Instaladores</h1>
    <a href="instaladores.php" class="btn btn-secondary">
        <i class="fas fa-users me-2"></i>Gerenciar Instaladores
    </a>
</div>

<!-- Filtros do Relatório -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-filter me-2"></i>Filtros
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label for="data_inicio" class="form-label">Data Inicial</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
            </div>
            <div class="col-md-4">
                <label for="data_fim" class="form-label">Data Final</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Resumo Geral -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <i class="fas fa-chart-line me-2"></i>Resumo Geral
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Total de Instaladores</h5>
                        <h2 class="display-4"><?php echo $total_instaladores; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Taxa Média de Conclusão</h5>
                        <h2 class="display-4"><?php echo number_format($media_geral_conclusao, 2); ?>%</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-info text-white h-100">
                    <div class="card-body">
                        <h5 class="card-title">Período Analisado</h5>
                        <h4><?php echo date('d/m/Y', strtotime($data_inicio)); ?> até <?php echo date('d/m/Y', strtotime($data_fim)); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico de Desempenho -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <i class="fas fa-chart-bar me-2"></i>Gráfico de Desempenho
    </div>
    <div class="card-body">
        <canvas id="graficoDesempenho" height="300"></canvas>
    </div>
</div>

<!-- Tabela de Instaladores -->
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <i class="fas fa-table me-2"></i>Ranking de Instaladores
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Ranking</th>
                        <th>Instalador</th>
                        <th>Total Agendamentos</th>
                        <th>Concluídos</th>
                        <th>Cancelados</th>
                        <th>Reagendados</th>
                        <th>Taxa de Conclusão</th>
                        <th>Tempo Médio</th>
                        <th>Classificação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instaladores as $index => $instalador): ?>
                        <tr class="<?php echo $instalador['classe_css']; ?>">
                            <td>#<?php echo $index + 1; ?></td>
                            <td><?php echo $instalador['nome']; ?></td>
                            <td><?php echo $instalador['total_agendamentos']; ?></td>
                            <td><?php echo $instalador['agendamentos_concluidos']; ?></td>
                            <td><?php echo $instalador['agendamentos_cancelados']; ?></td>
                            <td><?php echo $instalador['agendamentos_reagendados']; ?></td>
                            <td><strong><?php echo number_format($instalador['taxa_conclusao'], 2); ?>%</strong></td>
                            <td><?php echo round($instalador['media_dias_conclusao'], 1); ?> dias</td>
                            <td>
                                <span class="badge rounded-pill bg-<?php echo classificacaoParaCor($instalador['classificacao']); ?>">
                                    <?php echo ucfirst($instalador['classificacao']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="instaladores.php?id=<?php echo $instalador['id']; ?>" class="btn btn-sm btn-primary" title="Editar Instalador">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="btn btn-sm btn-info btn-historico" data-id="<?php echo $instalador['id']; ?>" title="Ver Histórico">
                                    <i class="fas fa-history"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para Histórico do Instalador -->
<div class="modal fade" id="historicoModal" tabindex="-1" aria-labelledby="historicoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="historicoModalLabel">Histórico do Instalador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="historico-conteudo">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p>Carregando histórico...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Rodapé -->
<?php require_once('includes/footer.php'); ?>

<!-- Script para o gráfico -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dados para o gráfico
const labelsGrafico = <?php echo json_encode($labels_grafico); ?>;
const dadosConclusao = <?php echo json_encode($dados_conclusao); ?>;
const dadosCancelados = <?php echo json_encode($dados_cancelados); ?>;
const coresBarras = <?php echo json_encode($cores_barras); ?>;

// Configuração do gráfico
const ctx = document.getElementById('graficoDesempenho').getContext('2d');
const graficoDesempenho = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labelsGrafico,
        datasets: [
            {
                label: 'Taxa de Conclusão (%)',
                data: dadosConclusao,
                backgroundColor: coresBarras,
                borderColor: coresBarras.map(cor => cor.replace('0.8', '1')),
                borderWidth: 1
            },
            {
                label: 'Cancelamentos',
                data: dadosCancelados,
                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 1,
                type: 'line'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Taxa de Conclusão (%)'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Instaladores'
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            },
            title: {
                display: true,
                text: 'Desempenho dos Instaladores'
            }
        }
    }
});

// Manipulação do modal de histórico
document.querySelectorAll('.btn-historico').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const instaladorId = this.getAttribute('data-id');
        const modal = new bootstrap.Modal(document.getElementById('historicoModal'));
        
        // Aqui você pode carregar os dados do histórico via AJAX
        // Em uma implementação real, isso faria uma requisição para buscar os detalhes
        
        // Simulação simplificada
        setTimeout(() => {
            const historicoHTML = `
                <h5>Últimas Instalações</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Cliente</th>
                                <th>Status</th>
                                <th>Observação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>${formatarData(new Date())}</td>
                                <td>Cliente Exemplo</td>
                                <td><span class="badge bg-success">Concluído</span></td>
                                <td>Instalação realizada com sucesso</td>
                            </tr>
                            <tr>
                                <td>${formatarData(new Date(Date.now() - 86400000))}</td>
                                <td>Outro Cliente</td>
                                <td><span class="badge bg-warning">Reagendado</span></td>
                                <td>Cliente solicitou reagendamento</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Para implementação completa, este modal exibirá o histórico detalhado do instalador, incluindo todas as instalações, avaliações de clientes e métricas de desempenho.
                </div>
            `;
            document.getElementById('historico-conteudo').innerHTML = historicoHTML;
        }, 500);
        
        modal.show();
    });
});

// Função para formatar datas
function formatarData(data) {
    return data.toLocaleDateString('pt-BR');
}

// Função para converter classificação em classe de cor
function classificacaoParaCor(classificacao) {
    switch(classificacao.toLowerCase()) {
        case 'excelente': return 'success';
        case 'bom': return 'info';
        case 'regular': return 'warning';
        default: return 'danger';
    }
}
</script>

<?php
// Função auxiliar para converter classificação em cor
function classificacaoParaCor($classificacao) {
    switch(strtolower($classificacao)) {
        case 'excelente': return 'success';
        case 'bom': return 'info';
        case 'regular': return 'warning';
        default: return 'danger';
    }
}
?>
