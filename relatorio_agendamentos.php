<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão
if ($_SESSION['usuario']['nivel'] !== 'admin' && !verificarPermissao('visualizar_relatorios')) {
    $_SESSION['mensagem'] = "Você não tem permissão para acessar esta funcionalidade.";
    $_SESSION['tipo_mensagem'] = 'danger';
    header('Location: index.php');
    exit;
}

require_once('includes/header.php');

// Obter parâmetros de filtro
$colaborador_id = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d');
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d', strtotime('+7 days'));
$filtro_status = isset($_GET['status']) ? $_GET['status'] : '';

// Se o relatório está sendo chamado com id específico, mostrar apenas esse instalador
$titulo_relatorio = "Relatório de Agendamentos por Período";
if ($colaborador_id > 0) {
    // Buscar nome do colaborador
    $stmt = $db->prepare("SELECT nome FROM colaboradores WHERE id = :id");
    $stmt->bindParam(':id', $colaborador_id, PDO::PARAM_INT);
    $stmt->execute();
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($colaborador) {
        $titulo_relatorio = "Agenda do Instalador: " . $colaborador['nome'];
    }
}

// Filtros para o SQL
$sql_filtros = [];
$params = [];

// Filtrar por colaborador
if ($colaborador_id > 0) {
    $sql_filtros[] = "a.instalador_id = :colaborador_id";
    $params[':colaborador_id'] = $colaborador_id;
}

// Filtrar por período
$sql_filtros[] = "(a.data_inicio BETWEEN :data_inicio AND :data_fim OR a.data_fim BETWEEN :data_inicio AND :data_fim)";
$params[':data_inicio'] = $filtro_data_inicio . ' 00:00:00';
$params[':data_fim'] = $filtro_data_fim . ' 23:59:59';

// Filtrar por status
if (!empty($filtro_status)) {
    $sql_filtros[] = "a.status = :status";
    $params[':status'] = $filtro_status;
}

// Construir SQL completo
$where = '';
if (!empty($sql_filtros)) {
    $where = 'WHERE ' . implode(' AND ', $sql_filtros);
}

// Consulta de agendamentos
$sql = "SELECT a.*, o.numero as codigo_orcamento, 
        (SELECT nome FROM clientes WHERE id = o.cliente_id) as cliente_nome, 
        c.nome as colaborador_nome 
        FROM agendamentos a 
        LEFT JOIN orcamentos o ON a.orcamento_id = o.id 
        LEFT JOIN colaboradores c ON a.instalador_id = c.id 
        $where 
        ORDER BY a.data_inicio ASC";

$stmt = $db->prepare($sql);
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}
$stmt->execute();
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar status disponíveis
$stmt_status = $db->query("SELECT DISTINCT status FROM agendamentos ORDER BY status");
$status_disponiveis = $stmt_status->fetchAll(PDO::FETCH_COLUMN);

// Buscar colaboradores
$stmt_colaboradores = $db->query("SELECT id, nome FROM colaboradores WHERE tipo = 'instalador' ORDER BY nome");
$colaboradores = $stmt_colaboradores->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-calendar-alt me-2"></i><?php echo $titulo_relatorio; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Relatório de Agendamentos</li>
            </ol>
        </nav>
    </div>
    
    <!-- Filtros do relatório -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-filter me-2"></i>Filtros
        </div>
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="colaborador_id" class="form-label">Instalador</label>
                    <select name="colaborador_id" id="colaborador_id" class="form-select">
                        <option value="0">Todos os instaladores</option>
                        <?php foreach ($colaboradores as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $colaborador_id == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo $c['nome']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo $filtro_data_inicio; ?>">
                </div>
                <div class="col-md-3">
                    <label for="data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo $filtro_data_fim; ?>">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($status_disponiveis as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $filtro_status == $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filtrar
                    </button>
                    <a href="relatorio_agendamentos.php" class="btn btn-secondary">
                        <i class="fas fa-undo me-2"></i>Limpar Filtros
                    </a>
                    <?php if ($colaborador_id > 0): ?>
                        <a href="colaborador_agenda.php?id=<?php echo $colaborador_id; ?>" class="btn btn-info float-end">
                            <i class="fas fa-calendar-plus me-2"></i>Gerenciar Agenda
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Totalizadores -->
    <div class="row mb-4">
        <?php
        $status_counts = [];
        foreach ($agendamentos as $agendamento) {
            $status = $agendamento['status'] ?? 'desconhecido';
            if (!isset($status_counts[$status])) {
                $status_counts[$status] = 0;
            }
            $status_counts[$status]++;
        }
        
        $status_badges = [
            'agendado' => 'primary',
            'em_andamento' => 'info',
            'concluido' => 'success',
            'cancelado' => 'danger',
            'reagendado' => 'warning'
        ];
        
        foreach ($status_counts as $status => $count):
            $badge_class = $status_badges[$status] ?? 'secondary';
        ?>
        <div class="col-md-3 mb-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title">
                        <span class="badge bg-<?php echo $badge_class; ?> mb-2"><?php echo ucfirst($status); ?></span>
                    </h5>
                    <h3 class="card-text"><?php echo $count; ?></h3>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title">Total</h5>
                    <h3 class="card-text"><?php echo count($agendamentos); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabela de agendamentos -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-table me-2"></i>Agendamentos
        </div>
        <div class="card-body">
            <?php if (empty($agendamentos)): ?>
                <div class="alert alert-info">Nenhum agendamento encontrado para os filtros selecionados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Data/Hora Início</th>
                                <th>Data/Hora Fim</th>
                                <th>Instalador</th>
                                <th>Cliente</th>
                                <th>Orçamento</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agendamentos as $agendamento): 
                                $status = $agendamento['status'] ?? 'desconhecido';
                                $badge_class = $status_badges[$status] ?? 'secondary';
                                
                                // Formatar datas
                                $data_inicio = new DateTime($agendamento['data_inicio']);
                                $data_fim = new DateTime($agendamento['data_fim']);
                            ?>
                            <tr>
                                <td><?php echo $data_inicio->format('d/m/Y H:i'); ?></td>
                                <td><?php echo $data_fim->format('d/m/Y H:i'); ?></td>
                                <td><?php echo $agendamento['colaborador_nome']; ?></td>
                                <td><?php echo $agendamento['cliente_nome']; ?></td>
                                <td>
                                    <?php if (!empty($agendamento['codigo_orcamento'])): ?>
                                        <a href="orcamento_visualizar.php?id=<?php echo $agendamento['orcamento_id']; ?>" class="badge bg-primary">
                                            <?php echo $agendamento['codigo_orcamento']; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">N/D</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($status != 'cancelado' && $status != 'concluido'): ?>
                                        <a href="agendamento.php?id=<?php echo $agendamento['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Gráfico de agendamentos por dia (opcional se quiser implementar) -->
    <div class="card mb-4 d-none">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-chart-bar me-2"></i>Gráfico de Agendamentos por Dia
        </div>
        <div class="card-body">
            <canvas id="agendamentosChart"></canvas>
        </div>
    </div>
</div>

<?php require_once('includes/footer.php'); ?>
