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
$instalador_id = isset($_GET['instalador_id']) ? intval($_GET['instalador_id']) : 0;
$instalador = [];
$auxiliares_disponiveis = [];
$auxiliares_equipe = [];
$mensagem = '';

// Verificar se o instalador existe
if ($instalador_id > 0) {
    $stmt = $db->prepare("SELECT * FROM colaboradores WHERE id = :id AND tipo = 'instalador'");
    $stmt->bindParam(':id', $instalador_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $instalador = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $mensagem = alerta('Instalador não encontrado!', 'danger');
        header('Location: colaboradores.php');
        exit;
    }
} else {
    header('Location: colaboradores.php');
    exit;
}

// Processar adição de auxiliar à equipe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_auxiliar'])) {
    $auxiliar_id = intval($_POST['auxiliar_id'] ?? 0);
    
    if ($auxiliar_id > 0) {
        // Verificar se o auxiliar já está na equipe
        $stmt = $db->prepare("SELECT COUNT(*) FROM colaborador_equipe 
                             WHERE instalador_id = :instalador_id AND auxiliar_id = :auxiliar_id
                             AND data_fim IS NULL");
        $stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
        $stmt->bindParam(':auxiliar_id', $auxiliar_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            // Adicionar auxiliar à equipe
            $stmt = $db->prepare("INSERT INTO colaborador_equipe 
                                (instalador_id, auxiliar_id, data_inicio) 
                                VALUES 
                                (:instalador_id, :auxiliar_id, CURRENT_DATE)");
            $stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
            $stmt->bindParam(':auxiliar_id', $auxiliar_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $mensagem = alerta('Auxiliar adicionado à equipe com sucesso!', 'success');
            } else {
                $mensagem = alerta('Erro ao adicionar auxiliar à equipe.', 'danger');
            }
        } else {
            $mensagem = alerta('Este auxiliar já está na equipe.', 'warning');
        }
    } else {
        $mensagem = alerta('Selecione um auxiliar válido.', 'warning');
    }
}

// Processar remoção de auxiliar da equipe
if (isset($_GET['remover']) && isset($_GET['auxiliar_id'])) {
    $auxiliar_id = intval($_GET['auxiliar_id']);
    
    // Remover auxiliar da equipe (marcar data_fim)
    $stmt = $db->prepare("UPDATE colaborador_equipe SET data_fim = CURRENT_DATE 
                         WHERE instalador_id = :instalador_id AND auxiliar_id = :auxiliar_id
                         AND data_fim IS NULL");
    $stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
    $stmt->bindParam(':auxiliar_id', $auxiliar_id, PDO::PARAM_INT);
    
    if ($stmt->execute() && $stmt->rowCount() > 0) {
        $mensagem = alerta('Auxiliar removido da equipe com sucesso!', 'success');
    } else {
        $mensagem = alerta('Erro ao remover auxiliar da equipe.', 'danger');
    }
}

// Buscar auxiliares ativos que não estão na equipe
$stmt = $db->prepare("SELECT c.* FROM colaboradores c 
                     WHERE c.tipo = 'auxiliar' AND c.status = 'ativo'
                     AND c.id NOT IN (
                         SELECT ce.auxiliar_id FROM colaborador_equipe ce
                         WHERE ce.instalador_id = :instalador_id AND ce.data_fim IS NULL
                     )
                     ORDER BY c.nome ASC");
$stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
$stmt->execute();
$auxiliares_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar auxiliares atualmente na equipe
$stmt = $db->prepare("SELECT c.*, ce.data_inicio 
                     FROM colaborador_equipe ce
                     JOIN colaboradores c ON ce.auxiliar_id = c.id
                     WHERE ce.instalador_id = :instalador_id AND ce.data_fim IS NULL
                     ORDER BY c.nome ASC");
$stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
$stmt->execute();
$auxiliares_equipe = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar histórico de auxiliares removidos da equipe
$stmt = $db->prepare("SELECT c.*, ce.data_inicio, ce.data_fim 
                     FROM colaborador_equipe ce
                     JOIN colaboradores c ON ce.auxiliar_id = c.id
                     WHERE ce.instalador_id = :instalador_id AND ce.data_fim IS NOT NULL
                     ORDER BY ce.data_fim DESC");
$stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
$stmt->execute();
$historico_auxiliares = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Definir título da página
$titulo = "Gerenciar Equipe";

// Incluir cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-users-cog me-2"></i>Gerenciar Equipe</h1>
    <a href="colaboradores.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-user-hard-hat me-2"></i>Instalador</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Nome:</strong> <?php echo $instalador['nome']; ?></p>
                <p><strong>Telefone:</strong> <?php echo $instalador['telefone'] ?? 'Não informado'; ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Email:</strong> <?php echo $instalador['email'] ?? 'Não informado'; ?></p>
                <p><strong>Data de Admissão:</strong> <?php echo dataParaBr($instalador['data_admissao']); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Auxiliares da Equipe</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Desde</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($auxiliares_equipe) > 0): ?>
                            <?php foreach ($auxiliares_equipe as $auxiliar): ?>
                                <tr>
                                    <td><?php echo $auxiliar['nome']; ?></td>
                                    <td><?php echo $auxiliar['telefone'] ?? '-'; ?></td>
                                    <td><?php echo dataParaBr($auxiliar['data_inicio']); ?></td>
                                    <td class="text-center">
                                        <a href="?instalador_id=<?php echo $instalador_id; ?>&remover=1&auxiliar_id=<?php echo $auxiliar['id']; ?>" 
                                           class="btn btn-sm btn-danger" title="Remover da Equipe"
                                           onclick="return confirm('Tem certeza que deseja remover este auxiliar da equipe?')">
                                            <i class="fas fa-user-minus"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-3">Nenhum auxiliar na equipe.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (count($historico_auxiliares) > 0): ?>
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Histórico de Auxiliares</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Período</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico_auxiliares as $auxiliar): ?>
                            <tr>
                                <td><?php echo $auxiliar['nome']; ?></td>
                                <td>De <?php echo dataParaBr($auxiliar['data_inicio']); ?> até <?php echo dataParaBr($auxiliar['data_fim']); ?></td>
                                <td>
                                    <?php if ($auxiliar['status'] == 'ativo'): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inativo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Adicionar Auxiliar à Equipe</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="auxiliar_id" class="form-label">Selecione um Auxiliar</label>
                        <select class="form-select" id="auxiliar_id" name="auxiliar_id" required>
                            <option value="">Selecione um auxiliar...</option>
                            <?php foreach ($auxiliares_disponiveis as $auxiliar): ?>
                                <option value="<?php echo $auxiliar['id']; ?>">
                                    <?php echo $auxiliar['nome']; ?>
                                    <?php if ($auxiliar['telefone']): ?>
                                        (<?php echo $auxiliar['telefone']; ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="adicionar_auxiliar" value="1" class="btn btn-success">
                            <i class="fas fa-plus-circle me-2"></i>Adicionar à Equipe
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once('includes/footer.php');
?>