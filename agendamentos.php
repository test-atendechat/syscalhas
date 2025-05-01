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

// Processar exclusão
if (isset($_POST['excluir']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    
    // Verificar se o agendamento possui orçamento aprovado
    $stmt = $db->prepare("SELECT a.*, o.status FROM agendamentos a 
                         INNER JOIN orcamentos o ON a.orcamento_id = o.id 
                         WHERE a.id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($agendamento && $agendamento['status'] === 'aprovado') {
        $mensagem = alerta('Não é possível excluir um agendamento de um orçamento aprovado!', 'danger');
    } else {
        $stmt = $db->prepare("DELETE FROM agendamentos WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Agendamento excluído com sucesso!', 'success');
        } else {
            $mensagem = alerta('Erro ao excluir agendamento!', 'danger');
        }
    }
}

// Definir título da página
$titulo = "Gerenciar Agendamentos";
require_once('includes/header.php');

// Buscar todos os agendamentos
$stmt = $db->prepare("SELECT a.*, o.numero as orcamento_numero, o.status as orcamento_status, 
                     c.nome as cliente_nome, u.nome as usuario_nome
                     FROM agendamentos a
                     INNER JOIN orcamentos o ON a.orcamento_id = o.id
                     INNER JOIN clientes c ON o.cliente_id = c.id
                     LEFT JOIN usuarios u ON a.usuario_id = u.id
                     ORDER BY a.data_agendamento DESC, a.hora_inicio ASC");
$stmt->execute();
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-calendar-alt me-2"></i>Gerenciar Agendamentos</h1>
    <a href="agendamento_form.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Novo Agendamento
    </a>
</div>

<?php echo isset($mensagem) ? $mensagem : ''; ?>

<!-- Filtros e Pesquisa -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="data_inicio" class="form-label">Data Início</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d'); ?>">
            </div>
            <div class="col-md-3">
                <label for="data_fim" class="form-label">Data Fim</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d', strtotime('+30 days')); ?>">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="agendado" <?php echo (isset($_GET['status']) && $_GET['status'] === 'agendado') ? 'selected' : ''; ?>>Agendado</option>
                    <option value="concluido" <?php echo (isset($_GET['status']) && $_GET['status'] === 'concluido') ? 'selected' : ''; ?>>Concluído</option>
                    <option value="cancelado" <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                    <option value="reagendado" <?php echo (isset($_GET['status']) && $_GET['status'] === 'reagendado') ? 'selected' : ''; ?>>Reagendado</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Agendamentos -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-list me-2"></i>Lista de Agendamentos
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Orçamento</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Status</th>
                        <th>Previsão</th>
                        <th>Agendado por</th>
                        <th width="120">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($agendamentos) > 0): ?>
                        <?php foreach ($agendamentos as $agendamento): ?>
                            <tr>
                                <td><?php echo $agendamento['id']; ?></td>
                                <td>
                                    <a href="orcamento_visualizar.php?id=<?php echo $agendamento['orcamento_id']; ?>" class="text-decoration-none">
                                        #<?php echo $agendamento['orcamento_numero']; ?>
                                    </a>
                                    <span class="badge status-<?php echo $agendamento['orcamento_status']; ?> ms-1">
                                        <?php echo ucfirst($agendamento['orcamento_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $agendamento['cliente_nome']; ?></td>
                                <td><?php echo dataParaBr($agendamento['data_agendamento']); ?></td>
                                <td><?php echo substr($agendamento['hora_inicio'], 0, 5) . ' - ' . substr($agendamento['hora_fim'], 0, 5); ?></td>
                                <td>
                                    <?php 
                                    $status_class = 'secondary';
                                    switch ($agendamento['status']) {
                                        case 'agendado':
                                            $status_class = 'primary';
                                            break;
                                        case 'concluido':
                                            $status_class = 'success';
                                            break;
                                        case 'cancelado':
                                            $status_class = 'danger';
                                            break;
                                        case 'reagendado':
                                            $status_class = 'warning';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($agendamento['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($agendamento['previsao_tempo'])): ?>
                                        <span class="text-<?php echo $agendamento['previsao_chuva'] ? 'danger' : 'success'; ?>">
                                            <?php echo $agendamento['previsao_tempo']; ?>
                                            <?php if (!empty($agendamento['temperatura'])): ?>
                                                <br><?php echo $agendamento['temperatura']; ?>°C
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Não disponível</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($agendamento['cliente_agendou']): ?>
                                        <span class="badge bg-info">Cliente</span>
                                    <?php else: ?>
                                        <?php echo $agendamento['usuario_nome'] ?: 'Sistema'; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="agendamento_form.php?id=<?php echo $agendamento['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="agendamento_visualizar.php?id=<?php echo $agendamento['id']; ?>" class="btn btn-sm btn-outline-info" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($agendamento['orcamento_status'] !== 'aprovado' || $_SESSION['usuario']['nivel'] === 'admin'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Excluir" 
                                                onclick="confirmarExclusao(<?php echo $agendamento['id']; ?>, 'Agendamento #<?php echo $agendamento['id']; ?>', 'agendamentos.php')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-3">Nenhum agendamento encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Calendário de Agendamentos (Visão Mensal) -->
<div class="card mt-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-calendar me-2"></i>Calendário de Agendamentos
    </div>
    <div class="card-body">
        <div id="calendario-agendamentos" class="calendario-container">
            <!-- O calendário será renderizado via JavaScript -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>O calendário estará disponível nas próximas atualizações.
            </div>
        </div>
    </div>
</div>

<!-- Link para Configurações de Horários Disponíveis -->
<div class="d-flex justify-content-end mt-3">
    <a href="horarios_disponiveis.php" class="btn btn-outline-primary me-2">
        <i class="fas fa-clock me-2"></i>Configurar Horários Disponíveis
    </a>
    <a href="indisponibilidades.php" class="btn btn-outline-secondary">
        <i class="fas fa-ban me-2"></i>Gerenciar Indisponibilidades
    </a>
</div>

<?php require_once('includes/footer.php'); ?>

<script>
// Script básico para filtros e pesquisa
document.addEventListener('DOMContentLoaded', function() {
    // Futura implementação do calendário
});
</script>