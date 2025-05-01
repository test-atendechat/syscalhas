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

// Processar formulário quando enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Atualizar horários disponíveis
    if (isset($_POST['horarios'])) {
        $horarios = $_POST['horarios'];
        
        try {
            // Iniciar transação
            $db->beginTransaction();
            
            // Percorrer cada horário e atualizar
            foreach ($horarios as $id => $horario) {
                $disponivel = isset($horario['disponivel']) ? 1 : 0;
                $observacao = $horario['observacao'] ?? '';
                
                $stmt = $db->prepare("UPDATE horarios_disponiveis 
                                   SET disponivel = :disponivel, observacao = :observacao 
                                   WHERE id = :id");
                $stmt->bindParam(':disponivel', $disponivel, PDO::PARAM_BOOL);
                $stmt->bindParam(':observacao', $observacao);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Confirmar transação
            $db->commit();
            $mensagem = alerta('Horários atualizados com sucesso!', 'success');
        } catch (Exception $e) {
            // Reverter alterações em caso de erro
            $db->rollBack();
            $mensagem = alerta('Erro ao atualizar horários: ' . $e->getMessage(), 'danger');
        }
    }
    
    // Adicionar novo horário
    if (isset($_POST['adicionar'])) {
        $dia_semana = intval($_POST['dia_semana']);
        $hora_inicio = $_POST['hora_inicio'];
        $hora_fim = $_POST['hora_fim'];
        $disponivel = isset($_POST['disponivel']) ? 1 : 0;
        $observacao = $_POST['observacao'] ?? '';
        
        try {
            $stmt = $db->prepare("INSERT INTO horarios_disponiveis (dia_semana, hora_inicio, hora_fim, disponivel, observacao) 
                               VALUES (:dia_semana, :hora_inicio, :hora_fim, :disponivel, :observacao)");
            $stmt->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
            $stmt->bindParam(':hora_inicio', $hora_inicio);
            $stmt->bindParam(':hora_fim', $hora_fim);
            $stmt->bindParam(':disponivel', $disponivel, PDO::PARAM_BOOL);
            $stmt->bindParam(':observacao', $observacao);
            
            if ($stmt->execute()) {
                $mensagem = alerta('Novo horário adicionado com sucesso!', 'success');
            } else {
                $mensagem = alerta('Erro ao adicionar horário.', 'danger');
            }
        } catch (Exception $e) {
            $mensagem = alerta('Erro ao adicionar horário: ' . $e->getMessage(), 'danger');
        }
    }
    
    // Excluir horário
    if (isset($_POST['excluir']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        
        try {
            $stmt = $db->prepare("DELETE FROM horarios_disponiveis WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $mensagem = alerta('Horário excluído com sucesso!', 'success');
            } else {
                $mensagem = alerta('Erro ao excluir horário.', 'danger');
            }
        } catch (Exception $e) {
            $mensagem = alerta('Erro ao excluir horário: ' . $e->getMessage(), 'danger');
        }
    }
}

// Buscar horários disponíveis agrupados por dia da semana
$stmt = $db->prepare("SELECT * FROM horarios_disponiveis ORDER BY dia_semana, hora_inicio");
$stmt->execute();
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar horários por dia da semana
$horarios_por_dia = [];
foreach ($horarios as $horario) {
    $dia = $horario['dia_semana'];
    if (!isset($horarios_por_dia[$dia])) {
        $horarios_por_dia[$dia] = [];
    }
    $horarios_por_dia[$dia][] = $horario;
}

// Nomes dos dias da semana
$dias_semana = [
    0 => 'Domingo',
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado'
];

// Definir título da página
$titulo = "Horários Disponíveis";
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-clock me-2"></i>Horários Disponíveis</h1>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAdicionarHorario">
            <i class="fas fa-plus-circle me-2"></i>Adicionar Horário
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
    <p class="mb-0">Configure os horários disponíveis para agendamento para cada dia da semana. 
    Você pode marcar ou desmarcar os horários disponíveis e adicionar observações para cada período.</p>
</div>

<!-- Formulário de Horários Disponíveis -->
<form method="post">
    <div class="accordion" id="accordionHorarios">
        <?php foreach ($dias_semana as $dia_id => $dia_nome): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading<?php echo $dia_id; ?>">
                    <button class="accordion-button <?php echo isset($horarios_por_dia[$dia_id]) ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $dia_id; ?>" aria-expanded="<?php echo isset($horarios_por_dia[$dia_id]) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $dia_id; ?>">
                        <?php echo $dia_nome; ?>
                        <?php if (isset($horarios_por_dia[$dia_id])): ?>
                            <span class="badge bg-primary ms-2"><?php echo count($horarios_por_dia[$dia_id]); ?> horário(s)</span>
                        <?php else: ?>
                            <span class="badge bg-secondary ms-2">Nenhum horário</span>
                        <?php endif; ?>
                    </button>
                </h2>
                <div id="collapse<?php echo $dia_id; ?>" class="accordion-collapse collapse <?php echo isset($horarios_por_dia[$dia_id]) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $dia_id; ?>" data-bs-parent="#accordionHorarios">
                    <div class="accordion-body">
                        <?php if (isset($horarios_por_dia[$dia_id])): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th width="150">Horário Início</th>
                                            <th width="150">Horário Fim</th>
                                            <th width="100">Disponível</th>
                                            <th>Observação</th>
                                            <th width="80">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($horarios_por_dia[$dia_id] as $horario): ?>
                                            <tr>
                                                <td>
                                                    <?php echo substr($horario['hora_inicio'], 0, 5); ?>
                                                    <input type="hidden" name="horarios[<?php echo $horario['id']; ?>][hora_inicio]" value="<?php echo $horario['hora_inicio']; ?>">
                                                </td>
                                                <td>
                                                    <?php echo substr($horario['hora_fim'], 0, 5); ?>
                                                    <input type="hidden" name="horarios[<?php echo $horario['id']; ?>][hora_fim]" value="<?php echo $horario['hora_fim']; ?>">
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                        <input class="form-check-input" type="checkbox" id="disponivel_<?php echo $horario['id']; ?>" name="horarios[<?php echo $horario['id']; ?>][disponivel]" value="1" <?php echo $horario['disponivel'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" name="horarios[<?php echo $horario['id']; ?>][observacao]" value="<?php echo htmlspecialchars($horario['observacao']); ?>" placeholder="Observação opcional">
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmarExclusaoHorario(<?php echo $horario['id']; ?>, '<?php echo substr($horario['hora_inicio'], 0, 5) . ' - ' . substr($horario['hora_fim'], 0, 5); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border">
                                <i class="fas fa-calendar-times me-2"></i>Nenhum horário disponível configurado para <?php echo $dia_nome; ?>.
                                <a href="#" class="ms-2" data-bs-toggle="modal" data-bs-target="#modalAdicionarHorario" data-dia="<?php echo $dia_id; ?>">Adicionar horário</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Botão Salvar -->
    <div class="d-grid gap-2 mt-4">
        <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-2"></i>Salvar Alterações
        </button>
    </div>
</form>

<!-- Modal Adicionar Horário -->
<div class="modal fade" id="modalAdicionarHorario" tabindex="-1" aria-labelledby="modalAdicionarHorarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalAdicionarHorarioLabel"><i class="fas fa-plus-circle me-2"></i>Adicionar Novo Horário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="dia_semana" class="form-label required">Dia da Semana</label>
                        <select class="form-select" id="dia_semana" name="dia_semana" required>
                            <?php foreach ($dias_semana as $dia_id => $dia_nome): ?>
                                <option value="<?php echo $dia_id; ?>"><?php echo $dia_nome; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="hora_inicio" class="form-label required">Horário Início</label>
                            <select class="form-select" id="hora_inicio" name="hora_inicio" required>
                                <?php
                                $horarios_inicio = [
                                    '08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'
                                ];
                                
                                foreach ($horarios_inicio as $hora) {
                                    echo "<option value=\"{$hora}\">{$hora}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="hora_fim" class="form-label required">Horário Fim</label>
                            <select class="form-select" id="hora_fim" name="hora_fim" required>
                                <?php
                                $horarios_fim = [
                                    '10:00', '11:00', '12:00', '13:00', '15:00', '16:00', '17:00', '18:00'
                                ];
                                
                                foreach ($horarios_fim as $hora) {
                                    echo "<option value=\"{$hora}\">{$hora}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="disponivel" name="disponivel" value="1" checked>
                        <label class="form-check-label" for="disponivel">Disponível para agendamento</label>
                    </div>
                    <div class="mb-3">
                        <label for="observacao" class="form-label">Observação</label>
                        <input type="text" class="form-control" id="observacao" name="observacao" placeholder="Observação opcional">
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
                <input type="hidden" id="horario_id" name="id" value="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Exclusão</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir o horário <strong id="horario_info"></strong>?</p>
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
// Configurar modal de adicionar horário
document.addEventListener('DOMContentLoaded', function() {
    const modalAdicionarHorario = document.getElementById('modalAdicionarHorario');
    
    if (modalAdicionarHorario) {
        modalAdicionarHorario.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (button && button.hasAttribute('data-dia')) {
                const dia = button.getAttribute('data-dia');
                const selectDia = document.getElementById('dia_semana');
                selectDia.value = dia;
            }
        });
    }
    
    // Atualizar horário de fim com base no início
    const horaInicio = document.getElementById('hora_inicio');
    const horaFim = document.getElementById('hora_fim');
    
    if (horaInicio && horaFim) {
        horaInicio.addEventListener('change', function() {
            const inicio = this.value;
            const [hora] = inicio.split(':');
            const horaFimSugerida = `${parseInt(hora) + 2}:00`;
            
            // Selecionar o próximo horário disponível
            for (let i = 0; i < horaFim.options.length; i++) {
                if (horaFim.options[i].value >= horaFimSugerida) {
                    horaFim.selectedIndex = i;
                    break;
                }
            }
        });
    }
});

// Confirmar exclusão de horário
function confirmarExclusaoHorario(id, horario) {
    document.getElementById('horario_id').value = id;
    document.getElementById('horario_info').textContent = horario;
    
    // Abrir modal de confirmação
    const modal = new bootstrap.Modal(document.getElementById('modalConfirmarExclusao'));
    modal.show();
}
</script>