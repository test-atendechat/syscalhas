<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão
require_once('verificar_permissao.php');
if (!verificarPermissao('gerenciar_agendamentos') && $_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: dashboard.php?erro=' . urlencode('Você não tem permissão para acessar esta página.'));
    exit;
}

// Processar formulário - Adicionar/Editar Instalador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_instalador'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nome = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $email = trim($_POST['email']);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $observacoes = trim($_POST['observacoes']);
    
    // Validar campos obrigatórios
    if (empty($nome)) {
        $mensagem = alerta('O nome do instalador é obrigatório.', 'danger');
    } else {
        // Verificar se é edição ou adição
        if ($id > 0) {
            // Atualizar instalador existente
            $stmt = $db->prepare("UPDATE instaladores SET 
                                 nome = :nome,
                                 telefone = :telefone,
                                 email = :email,
                                 ativo = :ativo,
                                 observacoes = :observacoes
                                 WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $operacao = 'atualizado';
        } else {
            // Inserir novo instalador
            $stmt = $db->prepare("INSERT INTO instaladores 
                                (nome, telefone, email, ativo, observacoes) 
                                VALUES 
                                (:nome, :telefone, :email, :ativo, :observacoes)");
            $operacao = 'adicionado';
        }
        
        // Bind parameters comuns
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':telefone', $telefone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':ativo', $ativo, PDO::PARAM_BOOL);
        $stmt->bindParam(':observacoes', $observacoes);
        
        if ($stmt->execute()) {
            $mensagem = alerta("Instalador {$operacao} com sucesso!", 'success');
        } else {
            $mensagem = alerta("Erro ao {$operacao} instalador.", 'danger');
        }
    }
}

// Processar formulário - Adicionar/Editar Auxiliar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_auxiliar'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nome = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $instalador_id = intval($_POST['instalador_id']);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $observacoes = trim($_POST['observacoes']);
    
    // Validar campos obrigatórios
    if (empty($nome)) {
        $mensagem = alerta('O nome do auxiliar é obrigatório.', 'danger');
    } elseif ($instalador_id <= 0) {
        $mensagem = alerta('Selecione um instalador para o auxiliar.', 'danger');
    } else {
        // Verificar se é edição ou adição
        if ($id > 0) {
            // Atualizar auxiliar existente
            $stmt = $db->prepare("UPDATE auxiliares SET 
                                 nome = :nome,
                                 telefone = :telefone,
                                 instalador_id = :instalador_id,
                                 ativo = :ativo,
                                 observacoes = :observacoes
                                 WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $operacao = 'atualizado';
        } else {
            // Inserir novo auxiliar
            $stmt = $db->prepare("INSERT INTO auxiliares 
                                (nome, telefone, instalador_id, ativo, observacoes) 
                                VALUES 
                                (:nome, :telefone, :instalador_id, :ativo, :observacoes)");
            $operacao = 'adicionado';
        }
        
        // Bind parameters comuns
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':telefone', $telefone);
        $stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
        $stmt->bindParam(':ativo', $ativo, PDO::PARAM_BOOL);
        $stmt->bindParam(':observacoes', $observacoes);
        
        if ($stmt->execute()) {
            $mensagem = alerta("Auxiliar {$operacao} com sucesso!", 'success');
        } else {
            $mensagem = alerta("Erro ao {$operacao} auxiliar.", 'danger');
        }
    }
}

// Processar exclusão de instalador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_instalador'])) {
    $id = intval($_POST['id']);
    
    // Verificar se há agendamentos com este instalador
    $stmt = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE instalador_id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $mensagem = alerta("Não é possível excluir o instalador pois existem {$count} agendamento(s) associados a ele.", 'danger');
    } else {
        // Verificar se há auxiliares vinculados a este instalador
        $stmt = $db->prepare("SELECT COUNT(*) FROM auxiliares WHERE instalador_id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $mensagem = alerta("Não é possível excluir o instalador pois existem {$count} auxiliar(es) vinculados a ele.", 'danger');
        } else {
            // Excluir instalador
            $stmt = $db->prepare("DELETE FROM instaladores WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $mensagem = alerta("Instalador excluído com sucesso!", 'success');
            } else {
                $mensagem = alerta("Erro ao excluir instalador.", 'danger');
            }
        }
    }
}

// Processar exclusão de auxiliar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_auxiliar'])) {
    $id = intval($_POST['id']);
    
    // Verificar se há agendamentos com este auxiliar
    $stmt = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE auxiliar_id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $mensagem = alerta("Não é possível excluir o auxiliar pois existem {$count} agendamento(s) associados a ele.", 'danger');
    } else {
        // Excluir auxiliar
        $stmt = $db->prepare("DELETE FROM auxiliares WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta("Auxiliar excluído com sucesso!", 'success');
        } else {
            $mensagem = alerta("Erro ao excluir auxiliar.", 'danger');
        }
    }
}

// Buscar dados para edição (se necessário)
$instalador_edicao = null;
$auxiliar_edicao = null;

if (isset($_GET['editar_instalador']) && !empty($_GET['editar_instalador'])) {
    $id = intval($_GET['editar_instalador']);
    
    $stmt = $db->prepare("SELECT * FROM instaladores WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $instalador_edicao = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (isset($_GET['editar_auxiliar']) && !empty($_GET['editar_auxiliar'])) {
    $id = intval($_GET['editar_auxiliar']);
    
    $stmt = $db->prepare("SELECT * FROM auxiliares WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $auxiliar_edicao = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Buscar todos os instaladores
$stmt = $db->prepare("SELECT * FROM instaladores ORDER BY nome ASC");
$stmt->execute();
$instaladores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar todos os auxiliares com nome do instalador
$stmt = $db->prepare("SELECT a.*, i.nome as instalador_nome 
                     FROM auxiliares a 
                     LEFT JOIN instaladores i ON a.instalador_id = i.id 
                     ORDER BY a.nome ASC");
$stmt->execute();
$auxiliares = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Definir título da página
$titulo = "Gerenciar Instaladores e Auxiliares";
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-hard-hat me-2"></i>Gerenciar Instaladores e Auxiliares</h1>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdicionarInstalador">
            <i class="fas fa-plus-circle me-2"></i>Novo Instalador
        </button>
        <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#modalAdicionarAuxiliar">
            <i class="fas fa-plus-circle me-2"></i>Novo Auxiliar
        </button>
        <a href="agendamentos.php" class="btn btn-secondary ms-2">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>
</div>

<?php echo isset($mensagem) ? $mensagem : ''; ?>

<!-- Instruções -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i><strong>Como funciona?</strong>
    <p class="mb-0">Cadastre os instaladores e seus auxiliares para atribuí-los aos agendamentos. Cada instalador pode ter vários auxiliares.</p>
</div>

<!-- Instaladores -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-hard-hat me-2"></i>Instaladores Cadastrados
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Email</th>
                        <th>Auxiliares</th>
                        <th>Status</th>
                        <th>Observações</th>
                        <th width="120">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($instaladores) > 0): ?>
                        <?php foreach ($instaladores as $instalador): 
                            // Contar quantos auxiliares este instalador possui
                            $stmt = $db->prepare("SELECT COUNT(*) FROM auxiliares WHERE instalador_id = :id");
                            $stmt->bindParam(':id', $instalador['id'], PDO::PARAM_INT);
                            $stmt->execute();
                            $auxiliares_count = $stmt->fetchColumn();
                        ?>
                            <tr>
                                <td><?php echo $instalador['id']; ?></td>
                                <td><?php echo htmlspecialchars($instalador['nome']); ?></td>
                                <td><?php echo htmlspecialchars($instalador['telefone']); ?></td>
                                <td><?php echo htmlspecialchars($instalador['email']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $auxiliares_count; ?> auxiliar(es)</span>
                                </td>
                                <td>
                                    <?php if ($instalador['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($instalador['observacoes']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?editar_instalador=<?php echo $instalador['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmarExclusaoInstalador(<?php echo $instalador['id']; ?>, '<?php echo addslashes($instalador['nome']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-3">Nenhum instalador cadastrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Auxiliares -->
<div class="card">
    <div class="card-header bg-success text-white">
        <i class="fas fa-user-hard-hat me-2"></i>Auxiliares Cadastrados
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Instalador</th>
                        <th>Status</th>
                        <th>Observações</th>
                        <th width="120">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($auxiliares) > 0): ?>
                        <?php foreach ($auxiliares as $auxiliar): ?>
                            <tr>
                                <td><?php echo $auxiliar['id']; ?></td>
                                <td><?php echo htmlspecialchars($auxiliar['nome']); ?></td>
                                <td><?php echo htmlspecialchars($auxiliar['telefone']); ?></td>
                                <td><?php echo htmlspecialchars($auxiliar['instalador_nome']); ?></td>
                                <td>
                                    <?php if ($auxiliar['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($auxiliar['observacoes']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?editar_auxiliar=<?php echo $auxiliar['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmarExclusaoAuxiliar(<?php echo $auxiliar['id']; ?>, '<?php echo addslashes($auxiliar['nome']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-3">Nenhum auxiliar cadastrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Adicionar/Editar Instalador -->
<div class="modal fade" id="modalAdicionarInstalador" tabindex="-1" aria-labelledby="modalAdicionarInstaladorLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?php if ($instalador_edicao): ?>
                    <input type="hidden" name="id" value="<?php echo $instalador_edicao['id']; ?>">
                <?php endif; ?>
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalAdicionarInstaladorLabel">
                        <i class="fas <?php echo $instalador_edicao ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                        <?php echo $instalador_edicao ? 'Editar Instalador' : 'Adicionar Novo Instalador'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nome" class="form-label required">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo $instalador_edicao ? htmlspecialchars($instalador_edicao['nome']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo $instalador_edicao ? htmlspecialchars($instalador_edicao['telefone']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $instalador_edicao ? htmlspecialchars($instalador_edicao['email']) : ''; ?>">
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" <?php echo (!$instalador_edicao || $instalador_edicao['ativo']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ativo">Ativo</label>
                    </div>
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $instalador_edicao ? htmlspecialchars($instalador_edicao['observacoes']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="salvar_instalador" value="1" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Adicionar/Editar Auxiliar -->
<div class="modal fade" id="modalAdicionarAuxiliar" tabindex="-1" aria-labelledby="modalAdicionarAuxiliarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?php if ($auxiliar_edicao): ?>
                    <input type="hidden" name="id" value="<?php echo $auxiliar_edicao['id']; ?>">
                <?php endif; ?>
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalAdicionarAuxiliarLabel">
                        <i class="fas <?php echo $auxiliar_edicao ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                        <?php echo $auxiliar_edicao ? 'Editar Auxiliar' : 'Adicionar Novo Auxiliar'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nome_auxiliar" class="form-label required">Nome</label>
                        <input type="text" class="form-control" id="nome_auxiliar" name="nome" value="<?php echo $auxiliar_edicao ? htmlspecialchars($auxiliar_edicao['nome']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="telefone_auxiliar" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone_auxiliar" name="telefone" value="<?php echo $auxiliar_edicao ? htmlspecialchars($auxiliar_edicao['telefone']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="instalador_id" class="form-label required">Instalador</label>
                        <select class="form-select" id="instalador_id" name="instalador_id" required>
                            <option value="">Selecione um instalador...</option>
                            <?php foreach ($instaladores as $instalador): ?>
                                <option value="<?php echo $instalador['id']; ?>" <?php echo ($auxiliar_edicao && $auxiliar_edicao['instalador_id'] == $instalador['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($instalador['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="ativo_auxiliar" name="ativo" value="1" <?php echo (!$auxiliar_edicao || $auxiliar_edicao['ativo']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ativo_auxiliar">Ativo</label>
                    </div>
                    <div class="mb-3">
                        <label for="observacoes_auxiliar" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes_auxiliar" name="observacoes" rows="3"><?php echo $auxiliar_edicao ? htmlspecialchars($auxiliar_edicao['observacoes']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="salvar_auxiliar" value="1" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusão de Instalador -->
<div class="modal fade" id="modalConfirmarExclusaoInstalador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" id="instalador_id_exclusao" name="id" value="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Exclusão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir o instalador <strong id="instalador_nome_exclusao"></strong>?</p>
                    <p class="text-danger">Esta ação não pode ser desfeita. Não será possível excluir um instalador que possui auxiliares ou agendamentos associados.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="excluir_instalador" value="1" class="btn btn-danger">Excluir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusão de Auxiliar -->
<div class="modal fade" id="modalConfirmarExclusaoAuxiliar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" id="auxiliar_id_exclusao" name="id" value="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Exclusão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir o auxiliar <strong id="auxiliar_nome_exclusao"></strong>?</p>
                    <p class="text-danger">Esta ação não pode ser desfeita. Não será possível excluir um auxiliar que possui agendamentos associados.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="excluir_auxiliar" value="1" class="btn btn-danger">Excluir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once('includes/footer.php'); ?>

<script>
// Script para abrir modal de edição automaticamente
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($instalador_edicao): ?>
    new bootstrap.Modal(document.getElementById('modalAdicionarInstalador')).show();
    <?php endif; ?>
    
    <?php if ($auxiliar_edicao): ?>
    new bootstrap.Modal(document.getElementById('modalAdicionarAuxiliar')).show();
    <?php endif; ?>
});

// Função para confirmar exclusão de instalador
function confirmarExclusaoInstalador(id, nome) {
    document.getElementById('instalador_id_exclusao').value = id;
    document.getElementById('instalador_nome_exclusao').textContent = nome;
    
    const modal = new bootstrap.Modal(document.getElementById('modalConfirmarExclusaoInstalador'));
    modal.show();
}

// Função para confirmar exclusão de auxiliar
function confirmarExclusaoAuxiliar(id, nome) {
    document.getElementById('auxiliar_id_exclusao').value = id;
    document.getElementById('auxiliar_nome_exclusao').textContent = nome;
    
    const modal = new bootstrap.Modal(document.getElementById('modalConfirmarExclusaoAuxiliar'));
    modal.show();
}
</script>