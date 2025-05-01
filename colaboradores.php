<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão
if (!verificarPermissao('gerenciar_colaboradores')) {
    header('Location: dashboard.php?erro=sempermissao');
    exit;
}

// Inicialização de variáveis
$mensagem = '';
$filtro_nome = '';
$filtro_tipo = 'todos';
$filtro_status = 'ativo';

// Processar ações do usuário
if (isset($_GET['acao'])) {
    $acao = $_GET['acao'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    // Excluir colaborador (marca como inativo)
    if ($acao === 'excluir' && $id > 0) {
        try {
            $stmt = $db->prepare("UPDATE colaboradores SET status = 'inativo', ultima_atualizacao = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $mensagem = alerta('Colaborador marcado como inativo com sucesso!', 'success');
        } catch (PDOException $e) {
            $mensagem = alerta('Erro ao excluir colaborador: ' . $e->getMessage(), 'danger');
        }
    }
    
    // Reativar colaborador
    if ($acao === 'reativar' && $id > 0) {
        try {
            $stmt = $db->prepare("UPDATE colaboradores SET status = 'ativo', ultima_atualizacao = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $mensagem = alerta('Colaborador reativado com sucesso!', 'success');
        } catch (PDOException $e) {
            $mensagem = alerta('Erro ao reativar colaborador: ' . $e->getMessage(), 'danger');
        }
    }
}

// Processar filtros
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['filtrar'])) {
    $filtro_nome = isset($_GET['nome']) ? limpaString($_GET['nome']) : '';
    $filtro_tipo = isset($_GET['tipo']) ? limpaString($_GET['tipo']) : 'todos';
    $filtro_status = isset($_GET['status']) ? limpaString($_GET['status']) : 'ativo';
}

// Buscar colaboradores com filtros
$sql = "SELECT * FROM colaboradores WHERE 1=1";
$params = [];

if (!empty($filtro_nome)) {
    $sql .= " AND nome ILIKE :nome";
    $params[':nome'] = "%{$filtro_nome}%";
}

if ($filtro_tipo !== 'todos') {
    $sql .= " AND tipo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

if ($filtro_status !== 'todos') {
    $sql .= " AND status = :status";
    $params[':status'] = $filtro_status;
}

$sql .= " ORDER BY nome ASC";

try {
    $stmt = $db->prepare($sql);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = alerta('Erro ao buscar colaboradores: ' . $e->getMessage(), 'danger');
    $colaboradores = [];
}

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-hard-hat me-2"></i>Colaboradores</h1>
    <a href="colaborador_form.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Novo Colaborador
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0"><i class="fas fa-filter me-2"></i>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label for="nome" class="form-label">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $filtro_nome; ?>">
            </div>
            <div class="col-md-3">
                <label for="tipo" class="form-label">Tipo</label>
                <select class="form-select" id="tipo" name="tipo">
                    <option value="todos" <?php echo $filtro_tipo === 'todos' ? 'selected' : ''; ?>>Todos</option>
                    <option value="instalador" <?php echo $filtro_tipo === 'instalador' ? 'selected' : ''; ?>>Instalador</option>
                    <option value="auxiliar" <?php echo $filtro_tipo === 'auxiliar' ? 'selected' : ''; ?>>Auxiliar</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="ativo" <?php echo $filtro_status === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                    <option value="inativo" <?php echo $filtro_status === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                    <option value="todos" <?php echo $filtro_status === 'todos' ? 'selected' : ''; ?>>Todos</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" name="filtrar" value="1" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Lista de Colaboradores</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>CPF</th>
                        <th>Telefone</th>
                        <th>Data Admissão</th>
                        <th>Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($colaboradores) > 0): ?>
                        <?php foreach ($colaboradores as $colaborador): ?>
                            <tr>
                                <td><?php echo $colaborador['id']; ?></td>
                                <td><?php echo $colaborador['nome']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $colaborador['tipo'] === 'instalador' ? 'primary' : 'info'; ?>">
                                        <?php echo ucfirst($colaborador['tipo']); ?>
                                    </span>
                                </td>
                                <td><?php echo $colaborador['cpf'] ?? '-'; ?></td>
                                <td><?php echo $colaborador['telefone'] ?? '-'; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($colaborador['data_admissao'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $colaborador['status'] === 'ativo' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($colaborador['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="colaborador_form.php?id=<?php echo $colaborador['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($colaborador['tipo'] === 'instalador'): ?>
                                        <a href="colaborador_equipe.php?id=<?php echo $colaborador['id']; ?>" class="btn btn-sm btn-info" title="Gerenciar Equipe">
                                            <i class="fas fa-users"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($colaborador['status'] === 'ativo'): ?>
                                        <a href="javascript:void(0)" onclick="confirmarExclusao(<?php echo $colaborador['id']; ?>, '<?php echo addslashes($colaborador['nome']); ?>')" class="btn btn-sm btn-danger" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php else: ?>
                                        <a href="javascript:void(0)" onclick="confirmarReativacao(<?php echo $colaborador['id']; ?>, '<?php echo addslashes($colaborador['nome']); ?>')" class="btn btn-sm btn-success" title="Reativar">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhum colaborador encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal fade" id="modalConfirmacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja marcar o colaborador <strong id="nomeColaborador"></strong> como inativo?</p>
                <p class="text-muted small">O colaborador não será excluído permanentemente, apenas marcado como inativo.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarExclusao" class="btn btn-danger">Confirmar</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmação de reativação -->
<div class="modal fade" id="modalReativacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Confirmar Reativação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja reativar o colaborador <strong id="nomeColaboradorReativar"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btnConfirmarReativacao" class="btn btn-success">Confirmar</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarExclusao(id, nome) {
    document.getElementById('nomeColaborador').textContent = nome;
    document.getElementById('btnConfirmarExclusao').href = 'colaboradores.php?acao=excluir&id=' + id;
    
    var modal = new bootstrap.Modal(document.getElementById('modalConfirmacao'));
    modal.show();
}

function confirmarReativacao(id, nome) {
    document.getElementById('nomeColaboradorReativar').textContent = nome;
    document.getElementById('btnConfirmarReativacao').href = 'colaboradores.php?acao=reativar&id=' + id;
    
    var modal = new bootstrap.Modal(document.getElementById('modalReativacao'));
    modal.show();
}
</script>

<?php
require_once('includes/footer.php');
?>