<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão - apenas admin pode ver este relatório
if ($_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: dashboard.php?erro=sempermissao');
    exit;
}

// Inicialização de variáveis
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'todos';
$data_inicio = '';
$data_fim = date('Y-m-d');

// Definir período com base na seleção
switch ($periodo) {
    case 'hoje':
        $data_inicio = date('Y-m-d');
        break;
    case 'semana':
        $data_inicio = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'mes':
        $data_inicio = date('Y-m-d', strtotime('-1 month'));
        break;
    case 'trimestre':
        $data_inicio = date('Y-m-d', strtotime('-3 months'));
        break;
    case 'semestre':
        $data_inicio = date('Y-m-d', strtotime('-6 months'));
        break;
    case 'ano':
        $data_inicio = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'personalizado':
        $data_inicio = isset($_GET['data_inicio']) ? limpaString($_GET['data_inicio']) : date('Y-m-d', strtotime('-1 month'));
        $data_fim = isset($_GET['data_fim']) ? limpaString($_GET['data_fim']) : date('Y-m-d');
        break;
    default: // todos
        $data_inicio = ''; // sem limite inferior
        break;
}

// Definir título da página
$titulo = "Ranking de Colaboradores";

// Buscar colaboradores (instaladores) ativos
$stmt = $db->prepare("SELECT id, nome, tipo FROM colaboradores WHERE tipo = 'instalador' AND status = 'ativo'");
$stmt->execute();
$instaladores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar array para os resultados do ranking
$ranking = [];

// Para cada instalador, calcular total de serviços
foreach ($instaladores as $instalador) {
    // Contar orçamentos aprovados e finalizados
    $sql = "SELECT COUNT(*) as total_servicos, COALESCE(SUM(valor_total), 0) as valor_total 
            FROM orcamentos 
            WHERE status = 'aprovado' AND status_execucao = 'finalizado'";
    
    // Adicionar filtro de período se especificado
    if (!empty($data_inicio)) {
        $sql .= " AND data_finalizacao >= :data_inicio";
    }
    if (!empty($data_fim)) {
        $sql .= " AND data_finalizacao <= :data_fim";
    }
    
    $stmt = $db->prepare($sql);
    
    // Vincular parâmetros de datas se necessário
    if (!empty($data_inicio)) {
        $stmt->bindParam(':data_inicio', $data_inicio);
    }
    if (!empty($data_fim)) {
        $stmt->bindParam(':data_fim', $data_fim);
    }
    
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Buscar auxiliares atualmente na equipe deste instalador
    $stmt = $db->prepare("SELECT c.nome 
                         FROM colaborador_equipe ce
                         JOIN colaboradores c ON ce.auxiliar_id = c.id
                         WHERE ce.instalador_id = :instalador_id AND ce.data_fim IS NULL
                         ORDER BY c.nome ASC");
    $stmt->bindParam(':instalador_id', $instalador['id'], PDO::PARAM_INT);
    $stmt->execute();
    $auxiliares = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Adicionar ao ranking
    $ranking[] = [
        'id' => $instalador['id'],
        'nome' => $instalador['nome'],
        'total_servicos' => $resultado['total_servicos'],
        'valor_total' => $resultado['valor_total'],
        'auxiliares' => $auxiliares
    ];
}

// Ordenar o ranking por total de serviços (decrescente)
usort($ranking, function($a, $b) {
    return $b['total_servicos'] <=> $a['total_servicos'];
});

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-trophy me-2"></i>Ranking de Colaboradores</h1>
    <div>
        <button onclick="window.print();" class="btn btn-outline-primary me-2">
            <i class="fas fa-print me-2"></i>Imprimir
        </button>
        <a href="relatorios_vendas.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="get" id="filtroForm">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="periodo" class="form-label">Período</label>
                    <select name="periodo" id="periodo" class="form-select" onchange="toggleDataPersonalizada()">
                        <option value="todos" <?php echo $periodo === 'todos' ? 'selected' : ''; ?>>Todos os Tempos</option>
                        <option value="hoje" <?php echo $periodo === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                        <option value="semana" <?php echo $periodo === 'semana' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                        <option value="mes" <?php echo $periodo === 'mes' ? 'selected' : ''; ?>>Último Mês</option>
                        <option value="trimestre" <?php echo $periodo === 'trimestre' ? 'selected' : ''; ?>>Últimos 3 Meses</option>
                        <option value="semestre" <?php echo $periodo === 'semestre' ? 'selected' : ''; ?>>Últimos 6 Meses</option>
                        <option value="ano" <?php echo $periodo === 'ano' ? 'selected' : ''; ?>>Último Ano</option>
                        <option value="personalizado" <?php echo $periodo === 'personalizado' ? 'selected' : ''; ?>>Período Personalizado</option>
                    </select>
                </div>
                
                <div id="divDataPersonalizada" class="col-md-6 mb-3" style="display: <?php echo $periodo === 'personalizado' ? 'flex' : 'none'; ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="data_inicio" class="form-label">Data Inicial</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="data_fim" class="form-label">Data Final</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="<?php echo $periodo === 'personalizado' ? 'col-md-2' : 'col-md-8'; ?> d-flex align-items-end mb-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-2"></i>Filtrar
                    </button>
                    <a href="relatorio_colaboradores.php" class="btn btn-secondary">
                        <i class="fas fa-eraser me-2"></i>Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Ranking de Produtividade</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 80px;">Posição</th>
                        <th>Instalador</th>
                        <th>Auxiliares</th>
                        <th class="text-center">Serviços</th>
                        <th class="text-end">Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ranking) > 0): ?>
                        <?php foreach ($ranking as $posicao => $instalador): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($posicao === 0): ?>
                                        <div class="medal gold"><i class="fas fa-trophy text-warning fa-2x"></i></div>
                                    <?php elseif ($posicao === 1): ?>
                                        <div class="medal silver"><i class="fas fa-trophy text-secondary fa-2x"></i></div>
                                    <?php elseif ($posicao === 2): ?>
                                        <div class="medal bronze"><i class="fas fa-trophy text-danger fa-2x"></i></div>
                                    <?php else: ?>
                                        <?php echo $posicao + 1; ?>º
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $instalador['nome']; ?></td>
                                <td>
                                    <?php if (count($instalador['auxiliares']) > 0): ?>
                                        <?php echo implode(', ', $instalador['auxiliares']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Nenhum auxiliar</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo $instalador['total_servicos']; ?></td>
                                <td class="text-end"><?php echo formataValor($instalador['valor_total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-3">Nenhum resultado encontrado no período selecionado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleDataPersonalizada() {
    const periodo = document.getElementById('periodo').value;
    const divDataPersonalizada = document.getElementById('divDataPersonalizada');
    
    if (periodo === 'personalizado') {
        divDataPersonalizada.style.display = 'flex';
    } else {
        divDataPersonalizada.style.display = 'none';
    }
}
</script>

<style>
.medal {
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>

<?php
require_once('includes/footer.php');
?>