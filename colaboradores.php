<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão
if (!verificarPermissao('gerenciar_colaboradores') && $_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: dashboard.php?erro=sempermissao');
    exit;
}

// Inicialização de variáveis
$mensagem = '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_status = isset($_GET['status']) ? $_GET['status'] : 'ativo';

// Processar exclusão se for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir']) && $_POST['excluir'] == 1) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // Verificar se o colaborador está associado a orçamentos
    $stmt = $db->prepare("SELECT COUNT(*) FROM orcamentos WHERE colaborador_id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        // Não excluir, apenas marcar como inativo
        $stmt = $db->prepare("UPDATE colaboradores SET status = 'inativo' WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $mensagem = alerta('Colaborador possui orçamentos associados. Status alterado para inativo.', 'warning');
    } else {
        // Remover associações de equipe
        $stmt = $db->prepare("DELETE FROM colaborador_equipe WHERE instalador_id = :id OR auxiliar_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Excluir colaborador
        $stmt = $db->prepare("DELETE FROM colaboradores WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Colaborador excluído com sucesso!', 'success');
        } else {
            $mensagem = alerta('Erro ao excluir colaborador.', 'danger');
        }
    }
}

// Consultar lista de colaboradores com filtros
$sql = "SELECT * FROM colaboradores WHERE 1=1";
if ($filtro_tipo) {
    $sql .= " AND tipo = :tipo";
}
if ($filtro_status) {
    $sql .= " AND status = :status";
}
$sql .= " ORDER BY nome ASC";

$stmt = $db->prepare($sql);
if ($filtro_tipo) {
    $stmt->bindParam(':tipo', $filtro_tipo);
}
if ($filtro_status) {
    $stmt->bindParam(':status', $filtro_status);
}
$stmt->execute();
$colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contagem de auxiliares por instalador
$auxiliares_por_instalador = [];
if (!empty($colaboradores)) {
    $stmt = $db->prepare("SELECT instalador_id, COUNT(*) as total FROM colaborador_equipe 
                         WHERE data_fim IS NULL GROUP BY instalador_id");
    $stmt->execute();
    $contar_auxiliares = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    foreach ($contar_auxiliares as $instalador_id => $total) {
        $auxiliares_por_instalador[$instalador_id] = $total;
    }
}

// Buscar estatísticas
$stmt = $db->prepare("SELECT COUNT(*) FROM colaboradores WHERE tipo = 'instalador' AND status = 'ativo'");
$stmt->execute();
$total_instaladores = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM colaboradores WHERE tipo = 'auxiliar' AND status = 'ativo'");
$stmt->execute();
$total_auxiliares = $stmt->fetchColumn();

// Definir título da página
$titulo = "Colaboradores";

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-hard-hat me-2"></i>Colaboradores</h1>
    <a href="colaborador_form.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Novo Colaborador
    </a>
</div>

<?php echo $mensagem; ?>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title mb-0">Total de Colaboradores</h6>
                    <h2 class="mt-2 mb-0"><?php echo count($colaboradores); ?></h2>
                </div>
                <div>
                    <i class="fas fa-users fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title mb-0">Instaladores Ativos</h6>
                    <h2 class="mt-2 mb-0"><?php echo $total_instaladores; ?></h2>
                </div>
                <div>
                    <i class="fas fa-user-hard-hat fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title mb-0">Auxiliares Ativos</h6>
                    <h2 class="mt-2 mb-0"><?php echo $total_auxiliares; ?></h2>
                </div>
                <div>
                    <i class="fas fa-toolbox fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
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
                    <label for="tipo" class="form-label">Tipo de Colaborador</label>
                    <select name="tipo" id="tipo" class="form-select" onchange="document.getElementById('filtroForm').submit();">
                        <option value="" <?php echo $filtro_tipo === '' ? 'selected' : ''; ?>>Todos</option>
                        <option value="instalador" <?php echo $filtro_tipo === 'instalador' ? 'selected' : ''; ?>>Instaladores</option>
                        <option value="auxiliar" <?php echo $filtro_tipo === 'auxiliar' ? 'selected' : ''; ?>>Auxiliares</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select" onchange="document.getElementById('filtroForm').submit();">
                        <option value="" <?php echo $filtro_status === '' ? 'selected' : ''; ?>>Todos</option>
                        <option value="ativo" <?php echo $filtro_status === 'ativo' ? 'selected' : ''; ?>>Ativos</option>
                        <option value="inativo" <?php echo $filtro_status === 'inativo' ? 'selected' : ''; ?>>Inativos</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end mb-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-2"></i>Filtrar
                    </button>
                    <a href="colaboradores.php" class="btn btn-secondary">
                        <i class="fas fa-eraser me-2"></i>Limpar Filtros
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Colaboradores</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Telefone</th>
                        <th>Status</th>
                        <th>Admissão</th>
                        <th>Auxiliares</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($colaboradores) > 0): ?>
                        <?php foreach ($colaboradores as $colaborador): ?>
                            <tr>
                                <td><?php echo $colaborador['nome']; ?></td>
                                <td>
                                    <?php if ($colaborador['tipo'] == 'instalador'): ?>
                                        <span class="badge bg-success">Instalador</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Auxiliar</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $colaborador['telefone'] ?? '-'; ?></td>
                                <td>
                                    <?php if ($colaborador['status'] == 'ativo'): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo dataParaBr($colaborador['data_admissao']); ?></td>
                                <td>
                                    <?php if ($colaborador['tipo'] == 'instalador'): ?>
                                        <?php echo isset($auxiliares_por_instalador[$colaborador['id']]) ? $auxiliares_por_instalador[$colaborador['id']] : 0; ?>
                                        <a href="colaborador_equipe.php?instalador_id=<?php echo $colaborador['id']; ?>" class="btn btn-sm btn-outline-primary ms-2" title="Gerenciar Auxiliares">
                                            <i class="fas fa-users-cog"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="colaborador_form.php?id=<?php echo $colaborador['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" title="Excluir" 
                                            onclick="confirmarExclusao(<?php echo $colaborador['id']; ?>, '<?php echo $colaborador['nome']; ?>', 'colaboradores.php')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-3">Nenhum colaborador encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once('includes/footer.php');
?>