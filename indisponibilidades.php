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
    
    $stmt = $db->prepare("DELETE FROM indisponibilidades WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $mensagem = alerta('Período de indisponibilidade excluído com sucesso!', 'success');
    } else {
        $mensagem = alerta('Erro ao excluir período de indisponibilidade!', 'danger');
    }
}

// Processar formulário quando enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar'])) {
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $motivo = $_POST['motivo'];
    $afeta_reagendamentos = isset($_POST['afeta_reagendamentos']) ? 1 : 0;
    $observacoes = $_POST['observacoes'] ?? '';
    
    // Validar datas
    if (strtotime($data_inicio) > strtotime($data_fim)) {
        $mensagem = alerta('A data de início não pode ser posterior à data de fim.', 'danger');
    } else {
        // Inserir no banco de dados
        $stmt = $db->prepare("INSERT INTO indisponibilidades (data_inicio, data_fim, motivo, afeta_reagendamentos, observacoes) 
                           VALUES (:data_inicio, :data_fim, :motivo, :afeta_reagendamentos, :observacoes)");
        $stmt->bindParam(':data_inicio', $data_inicio);
        $stmt->bindParam(':data_fim', $data_fim);
        $stmt->bindParam(':motivo', $motivo);
        $stmt->bindParam(':afeta_reagendamentos', $afeta_reagendamentos, PDO::PARAM_BOOL);
        $stmt->bindParam(':observacoes', $observacoes);
        
        if ($stmt->execute()) {
            $mensagem = alerta('Período de indisponibilidade adicionado com sucesso!', 'success');
        } else {
            $mensagem = alerta('Erro ao adicionar período de indisponibilidade!', 'danger');
        }
    }
}

// Buscar indisponibilidades
$stmt = $db->prepare("SELECT * FROM indisponibilidades ORDER BY data_inicio ASC");
$stmt->execute();
$indisponibilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Definir título da página
$titulo = "Gerenciar Indisponibilidades";
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-ban me-2"></i>Gerenciar Indisponibilidades</h1>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdicionarIndisponibilidade">
            <i class="fas fa-plus-circle me-2"></i>Adicionar Período
        </button>
        <a href="agendamentos.php" class="btn btn-secondary ms-2">
            <i class="fas fa-arrow-left me-2"></i>Voltar para Agendamentos
        </a>
    </div>
</div>

<?php echo isset($mensagem) ? $mensagem : ''; ?>

<!-- Instruções -->
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i><strong>Como funciona?</strong>
    <p class="mb-0">Configure períodos de indisponibilidade para dias como feriados, férias, manutenção, etc. 
    Durante estes períodos, não será possível realizar agendamentos.</p>
</div>

<!-- Listagem de Indisponibilidades -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-calendar-times me-2"></i>Períodos de Indisponibilidade
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th width="150">Data Início</th>
                        <th width="150">Data Fim</th>
                        <th>Motivo</th>
                        <th>Observações</th>
                        <th width="100">Afeta Reagendamentos</th>
                        <th width="80">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($indisponibilidades) > 0): ?>
                        <?php foreach ($indisponibilidades as $indisponibilidade): ?>
                            <tr>
                                <td><?php echo dataParaBr($indisponibilidade['data_inicio']); ?></td>
                                <td><?php echo dataParaBr($indisponibilidade['data_fim']); ?></td>
                                <td><?php echo htmlspecialchars($indisponibilidade['motivo']); ?></td>
                                <td><?php echo htmlspecialchars($indisponibilidade['observacoes']); ?></td>
                                <td class="text-center">
                                    <?php if ($indisponibilidade['afeta_reagendamentos']): ?>
                                        <span class="badge bg-warning">Sim</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmarExclusaoIndisponibilidade(<?php echo $indisponibilidade['id']; ?>, '<?php echo dataParaBr($indisponibilidade['data_inicio']) . ' a ' . dataParaBr($indisponibilidade['data_fim']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-3">Nenhum período de indisponibilidade cadastrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Visualização de Calendário -->
<div class="card mt-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-calendar me-2"></i>Visualização no Calendário
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>O calendário estará disponível nas próximas atualizações.
        </div>
    </div>
</div>

<!-- Modal Adicionar Indisponibilidade -->
<div class="modal fade" id="modalAdicionarIndisponibilidade" tabindex="-1" aria-labelledby="modalAdicionarIndisponibilidadeLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalAdicionarIndisponibilidadeLabel"><i class="fas fa-plus-circle me-2"></i>Adicionar Período de Indisponibilidade</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="data_inicio" class="form-label required">Data Início</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo date('Y-m-d'); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="data_fim" class="form-label required">Data Fim</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo date('Y-m-d'); ?>" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="motivo" class="form-label required">Motivo</label>
                        <select class="form-select" id="motivo" name="motivo" required>
                            <option value="">Selecione um motivo...</option>
                            <option value="Feriado">Feriado</option>
                            <option value="Férias">Férias</option>
                            <option value="Manutenção">Manutenção</option>
                            <option value="Capacitação">Capacitação/Treinamento</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="afeta_reagendamentos" name="afeta_reagendamentos" value="1">
                        <label class="form-check-label" for="afeta_reagendamentos">Afeta reagendamentos automáticos</label>
                        <div class="form-text">Se marcado, os reagendamentos automáticos por condições climáticas considerarão este período como indisponível.</div>
                    </div>
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Informações adicionais sobre este período"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="adicionar" value="1" class="btn btn-primary">Adicionar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Exclusão -->
<div class="modal fade" id="modalConfirmarExclusao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" id="indisponibilidade_id" name="id" value="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Exclusão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir o período de <strong id="indisponibilidade_info"></strong>?</p>
                    <p class="text-danger">Esta ação não pode ser desfeita.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="excluir" value="1" class="btn btn-danger">Excluir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once('includes/footer.php'); ?>

<script>
// Validar datas
document.addEventListener('DOMContentLoaded', function() {
    const dataInicio = document.getElementById('data_inicio');
    const dataFim = document.getElementById('data_fim');
    
    if (dataInicio && dataFim) {
        dataInicio.addEventListener('change', function() {
            dataFim.min = this.value;
            if (dataFim.value < this.value) {
                dataFim.value = this.value;
            }
        });
    }
});

// Confirmar exclusão de indisponibilidade
function confirmarExclusaoIndisponibilidade(id, periodo) {
    document.getElementById('indisponibilidade_id').value = id;
    document.getElementById('indisponibilidade_info').textContent = periodo;
    
    // Abrir modal de confirmação
    const modal = new bootstrap.Modal(document.getElementById('modalConfirmarExclusao'));
    modal.show();
}
</script>