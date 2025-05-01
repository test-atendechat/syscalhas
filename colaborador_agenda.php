<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
verificarAutenticacao();

// Verificar permissões
if (!$_SESSION['usuario']['nivel'] === 'admin' && !verificarPermissao('gerenciar_colaboradores')) {
    header('Location: index.php?erro=sem_permissao');
    exit;
}

// Inicialização de variáveis
$mensagem = '';
$colaborador_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$colaborador = null;
$disponibilidades = [];

// Buscar dados do colaborador
if ($colaborador_id > 0) {
    $stmt = $db->prepare("SELECT * FROM colaboradores WHERE id = :id");
    $stmt->bindParam(':id', $colaborador_id, PDO::PARAM_INT);
    $stmt->execute();
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar se o colaborador existe
    if (!$colaborador) {
        header('Location: colaboradores.php?mensagem=' . urlencode('Colaborador não encontrado.') . '&tipo=danger');
        exit;
    }
    
    // Buscar agenda do colaborador
    $stmt = $db->prepare("SELECT * FROM colaborador_agenda WHERE colaborador_id = :colaborador_id ORDER BY data_disponibilidade, hora_inicio");
    $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
    $stmt->execute();
    $disponibilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Processar formulário - Adicionar/Editar disponibilidade
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'salvar') {
    $data_disponibilidade = $_POST['data_disponibilidade'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fim = $_POST['hora_fim'] ?? '';
    
    // Verificar se é indisponibilidade (botão "Registrar Indisponibilidade" foi pressionado)
    $botao = isset($_POST['btnSalvarIndisponibilidade']) ? 'indisponibilidade' : 'disponibilidade';
    $disponivel = ($botao == 'disponibilidade' && isset($_POST['disponivel'])) ? 1 : 0;
    
    $observacao = $_POST['observacao'] ?? '';
    $recorrente = isset($_POST['recorrente']) ? 1 : 0;
    $dia_semana = $recorrente ? (int)($_POST['dia_semana'] ?? 0) : null;
    $registro_id = isset($_POST['registro_id']) ? intval($_POST['registro_id']) : 0;
    
    if (empty($data_disponibilidade) || empty($hora_inicio) || empty($hora_fim)) {
        $mensagem = alerta('Todos os campos obrigatórios devem ser preenchidos.', 'danger');
    } else {
        try {
            // Verificar se a hora de início é anterior à hora de fim
            $inicio = new DateTime($hora_inicio);
            $fim = new DateTime($hora_fim);
            
            if ($inicio >= $fim) {
                throw new Exception('A hora de início deve ser anterior à hora de fim.');
            }
            
            if ($registro_id > 0) {
                // Atualizar registro existente
                $stmt = $db->prepare("UPDATE colaborador_agenda SET 
                                    data_disponibilidade = :data_disponibilidade,
                                    hora_inicio = :hora_inicio,
                                    hora_fim = :hora_fim,
                                    disponivel = :disponivel,
                                    observacao = :observacao,
                                    recorrente = :recorrente,
                                    dia_semana = :dia_semana
                                    WHERE id = :id AND colaborador_id = :colaborador_id");
                $stmt->bindParam(':id', $registro_id, PDO::PARAM_INT);
                $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
                $stmt->bindParam(':data_disponibilidade', $data_disponibilidade);
                $stmt->bindParam(':hora_inicio', $hora_inicio);
                $stmt->bindParam(':hora_fim', $hora_fim);
                $stmt->bindParam(':disponivel', $disponivel, PDO::PARAM_BOOL);
                $stmt->bindParam(':observacao', $observacao);
                $stmt->bindParam(':recorrente', $recorrente, PDO::PARAM_BOOL);
                $stmt->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
                $stmt->execute();
                
                $mensagem = alerta('Registro de disponibilidade atualizado com sucesso!', 'success');
            } else {
                // Inserir novo registro
                $stmt = $db->prepare("INSERT INTO colaborador_agenda (
                                    colaborador_id, data_disponibilidade, hora_inicio, hora_fim,
                                    disponivel, observacao, recorrente, dia_semana)
                                    VALUES (
                                    :colaborador_id, :data_disponibilidade, :hora_inicio, :hora_fim,
                                    :disponivel, :observacao, :recorrente, :dia_semana)");
                $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
                $stmt->bindParam(':data_disponibilidade', $data_disponibilidade);
                $stmt->bindParam(':hora_inicio', $hora_inicio);
                $stmt->bindParam(':hora_fim', $hora_fim);
                $stmt->bindParam(':disponivel', $disponivel, PDO::PARAM_BOOL);
                $stmt->bindParam(':observacao', $observacao);
                $stmt->bindParam(':recorrente', $recorrente, PDO::PARAM_BOOL);
                $stmt->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
                $stmt->execute();
                
                $mensagem = alerta('Registro de disponibilidade adicionado com sucesso!', 'success');
            }
            
            // Atualizar lista de disponibilidades
            $stmt = $db->prepare("SELECT * FROM colaborador_agenda WHERE colaborador_id = :colaborador_id ORDER BY data_disponibilidade, hora_inicio");
            $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
            $stmt->execute();
            $disponibilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $mensagem = alerta('Erro ao processar registro: ' . $e->getMessage(), 'danger');
        }
    }
}

// Processar exclusão de disponibilidade
if (isset($_GET['acao']) && $_GET['acao'] == 'excluir' && isset($_GET['registro_id'])) {
    $registro_id = intval($_GET['registro_id']);
    
    try {
        $stmt = $db->prepare("DELETE FROM colaborador_agenda WHERE id = :id AND colaborador_id = :colaborador_id");
        $stmt->bindParam(':id', $registro_id, PDO::PARAM_INT);
        $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $mensagem = alerta('Registro de disponibilidade excluído com sucesso!', 'success');
            
            // Atualizar lista de disponibilidades
            $stmt = $db->prepare("SELECT * FROM colaborador_agenda WHERE colaborador_id = :colaborador_id ORDER BY data_disponibilidade, hora_inicio");
            $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
            $stmt->execute();
            $disponibilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $mensagem = alerta('Registro não encontrado ou você não tem permissão para excluí-lo.', 'danger');
        }
    } catch (Exception $e) {
        $mensagem = alerta('Erro ao excluir registro: ' . $e->getMessage(), 'danger');
    }
}

require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>
        <?php if ($colaborador): ?>
            <i class="fas fa-calendar-alt me-2"></i>Agenda do Colaborador: <?php echo $colaborador['nome']; ?>
        <?php else: ?>
            <i class="fas fa-calendar-alt me-2"></i>Gerenciamento de Agenda
        <?php endif; ?>
    </h1>
    <div>
        <?php if ($colaborador): ?>
            <a href="colaboradores.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar para Colaboradores
            </a>
        <?php else: ?>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
        <?php endif; ?>
    </div>
</div>

<?php echo $mensagem; ?>

<?php if ($colaborador): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Colaborador</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">Nome:</dt>
                    <dd class="col-sm-8"><?php echo $colaborador['nome']; ?></dd>
                    
                    <dt class="col-sm-4">Tipo:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo $colaborador['tipo'] == 'instalador' ? 'primary' : 'info'; ?>">
                            <?php echo ucfirst($colaborador['tipo']); ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-4">Telefone:</dt>
                    <dd class="col-sm-8"><?php echo $colaborador['telefone'] ?? 'Não informado'; ?></dd>
                    
                    <dt class="col-sm-4">Situação:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo $colaborador['status'] == 'ativo' ? 'success' : 'danger'; ?>">
                            <?php echo ucfirst($colaborador['status']); ?>
                        </span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-plus me-2"></i>Gerenciar Disponibilidade</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="registro_id" value="0" id="registro_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="data_disponibilidade" class="form-label required-field">Data</label>
                            <input type="date" class="form-control" id="data_disponibilidade" name="data_disponibilidade" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="hora_inicio" class="form-label required-field">Início</label>
                            <input type="time" class="form-control" id="hora_inicio" name="hora_inicio" required>
                        </div>
                        <div class="col-md-3">
                            <label for="hora_fim" class="form-label required-field">Fim</label>
                            <input type="time" class="form-control" id="hora_fim" name="hora_fim" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="disponivel" name="disponivel" checked>
                                <label class="form-check-label fw-bold" for="disponivel">
                                    <i class="fas fa-check-circle text-success me-1"></i> Disponível para agendamentos
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="indisponivel" onclick="toggleIndisponivel()">
                                <label class="form-check-label fw-bold" for="indisponivel">
                                    <i class="fas fa-times-circle text-danger me-1"></i> Registrar indisponibilidade
                                </label>
                                <div id="motivo_indisponibilidade" class="mt-2" style="display: none;">
                                    <select class="form-select form-select-sm" id="tipo_indisponibilidade" name="tipo_indisponibilidade">
                                        <option value="Férias">Férias</option>
                                        <option value="Licença médica">Licença médica</option>
                                        <option value="Compromisso pessoal">Compromisso pessoal</option>
                                        <option value="Treinamento">Treinamento</option>
                                        <option value="Outro">Outro motivo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="recorrente" name="recorrente">
                                <label class="form-check-label" for="recorrente">
                                    <i class="fas fa-sync-alt text-primary me-1"></i> Repetir todas as semanas
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6" id="dia_semana_group" style="display: none;">
                            <label for="dia_semana" class="form-label">Dia da Semana</label>
                            <select class="form-select" id="dia_semana" name="dia_semana">
                                <option value="1">Segunda-feira</option>
                                <option value="2">Terça-feira</option>
                                <option value="3">Quarta-feira</option>
                                <option value="4">Quinta-feira</option>
                                <option value="5">Sexta-feira</option>
                                <option value="6">Sábado</option>
                                <option value="0">Domingo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacao" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="2" placeholder="Descreva aqui o motivo da indisponibilidade ou outras informações relevantes"></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="submit" class="btn btn-primary" id="btnSalvar">
                                <i class="fas fa-save me-2"></i>Salvar Disponibilidade
                            </button>
                            <button type="submit" class="btn btn-warning" id="btnSalvarIndisponibilidade" name="btnSalvarIndisponibilidade" style="display: none;">
                                <i class="fas fa-ban me-2"></i>Registrar Indisponibilidade
                            </button>
                        </div>
                        <button type="button" class="btn btn-secondary" id="btnCancelar" style="display: none;">
                            <i class="fas fa-times me-2"></i>Cancelar Edição
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Disponibilidades Cadastradas</h5>
    </div>
    <div class="card-body">
        <?php if (count($disponibilidades) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Status</th>
                            <th>Recorrente</th>
                            <th>Observações</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disponibilidades as $disponibilidade): ?>
                            <tr data-id="<?php echo $disponibilidade['id']; ?>" class="<?php echo $disponibilidade['disponivel'] ? '' : 'table-warning'; ?>">
                                <td>
                                    <?php if ($disponibilidade['recorrente']): ?>
                                        <?php 
                                        $dias_semana = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                                        echo $dias_semana[$disponibilidade['dia_semana']] . 's';
                                        ?>
                                    <?php else: ?>
                                        <?php echo date('d/m/Y', strtotime($disponibilidade['data_disponibilidade'])); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    echo date('H:i', strtotime($disponibilidade['hora_inicio'])) . ' até ' . 
                                         date('H:i', strtotime($disponibilidade['hora_fim'])); 
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $disponibilidade['disponivel'] ? 'success' : 'warning'; ?>">
                                        <?php echo $disponibilidade['disponivel'] ? 'Disponível' : 'Indisponível'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $disponibilidade['recorrente'] ? 'info' : 'secondary'; ?>">
                                        <?php echo $disponibilidade['recorrente'] ? 'Semanal' : 'Não'; ?>
                                    </span>
                                </td>
                                <td><?php echo $disponibilidade['observacao']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary btnEditar" 
                                            data-id="<?php echo $disponibilidade['id']; ?>"
                                            data-data="<?php echo $disponibilidade['data_disponibilidade']; ?>"
                                            data-inicio="<?php echo $disponibilidade['hora_inicio']; ?>"
                                            data-fim="<?php echo $disponibilidade['hora_fim']; ?>"
                                            data-disponivel="<?php echo $disponibilidade['disponivel']; ?>"
                                            data-recorrente="<?php echo $disponibilidade['recorrente']; ?>"
                                            data-dia-semana="<?php echo $disponibilidade['dia_semana']; ?>"
                                            data-observacao="<?php echo htmlspecialchars($disponibilidade['observacao']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <a href="colaborador_agenda.php?id=<?php echo $colaborador_id; ?>&acao=excluir&registro_id=<?php echo $disponibilidade['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Tem certeza que deseja excluir este registro?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Nenhuma disponibilidade cadastrada para este colaborador.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Função para alternar entre disponibilidade e indisponibilidade
function toggleIndisponivel() {
    const indisponivelCheck = document.getElementById('indisponivel');
    const disponivelCheck = document.getElementById('disponivel');
    const motivoDiv = document.getElementById('motivo_indisponibilidade');
    const tipoIndisponibilidade = document.getElementById('tipo_indisponibilidade');
    const observacaoInput = document.getElementById('observacao');
    const btnSalvar = document.getElementById('btnSalvar');
    const btnSalvarIndisponibilidade = document.getElementById('btnSalvarIndisponibilidade');
    
    if (indisponivelCheck.checked) {
        disponivelCheck.checked = false;
        disponivelCheck.disabled = true;
        motivoDiv.style.display = 'block';
        btnSalvar.style.display = 'none';
        btnSalvarIndisponibilidade.style.display = 'inline-block';
        
        // Pre-preencher o campo de observações
        const motivo = tipoIndisponibilidade.options[tipoIndisponibilidade.selectedIndex].value;
        if (!observacaoInput.value.includes(motivo)) {
            observacaoInput.value = motivo + (observacaoInput.value ? ': ' + observacaoInput.value : '');
        }
    } else {
        disponivelCheck.disabled = false;
        motivoDiv.style.display = 'none';
        btnSalvar.style.display = 'inline-block';
        btnSalvarIndisponibilidade.style.display = 'none';
    }
}

// Atualiza o campo de observações quando o tipo de indisponibilidade muda
function atualizarMotivoIndisponibilidade() {
    const indisponivelCheck = document.getElementById('indisponivel');
    const tipoIndisponibilidade = document.getElementById('tipo_indisponibilidade');
    const observacaoInput = document.getElementById('observacao');
    
    if (indisponivelCheck.checked) {
        const motivo = tipoIndisponibilidade.options[tipoIndisponibilidade.selectedIndex].value;
        // Limpar o motivo anterior se existir
        const partes = observacaoInput.value.split(': ');
        if (partes.length > 1) {
            observacaoInput.value = motivo + ': ' + partes[1];
        } else {
            observacaoInput.value = motivo;
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar seleção de dia da semana quando recorrente for marcado
    const recorrenteCheckbox = document.getElementById('recorrente');
    const diaSemanaGroup = document.getElementById('dia_semana_group');
    const indisponivelCheck = document.getElementById('indisponivel');
    const tipoIndisponibilidade = document.getElementById('tipo_indisponibilidade');
    const btnSalvarIndisponibilidade = document.getElementById('btnSalvarIndisponibilidade');
    
    recorrenteCheckbox.addEventListener('change', function() {
        diaSemanaGroup.style.display = this.checked ? 'block' : 'none';
    });
    
    // Inicializar o botão de indisponibilidade
    btnSalvarIndisponibilidade.style.display = 'none';
    
    // Configurar eventos para o tipo de indisponibilidade
    tipoIndisponibilidade.addEventListener('change', atualizarMotivoIndisponibilidade);
    
    // Configurar botões de edição
    const botoesEditar = document.querySelectorAll('.btnEditar');
    const formRegistro = document.querySelector('form');
    const registroIdInput = document.getElementById('registro_id');
    const dataInput = document.getElementById('data_disponibilidade');
    const horaInicioInput = document.getElementById('hora_inicio');
    const horaFimInput = document.getElementById('hora_fim');
    const disponivelCheckbox = document.getElementById('disponivel');
    const recorrenteInput = document.getElementById('recorrente');
    const diaSemanaSelect = document.getElementById('dia_semana');
    const observacaoInput = document.getElementById('observacao');
    const btnSalvar = document.getElementById('btnSalvar');
    const btnCancelar = document.getElementById('btnCancelar');
    
    botoesEditar.forEach(botao => {
        botao.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const data = this.getAttribute('data-data');
            const inicio = this.getAttribute('data-inicio');
            const fim = this.getAttribute('data-fim');
            const disponivel = this.getAttribute('data-disponivel') === '1';
            const recorrente = this.getAttribute('data-recorrente') === '1';
            const diaSemana = this.getAttribute('data-dia-semana');
            const observacao = this.getAttribute('data-observacao');
            
            // Preencher formulário com dados do registro
            registroIdInput.value = id;
            dataInput.value = data;
            horaInicioInput.value = inicio.substring(0, 5); // Remover segundos
            horaFimInput.value = fim.substring(0, 5); // Remover segundos
            disponivelCheckbox.checked = disponivel;
            recorrenteInput.checked = recorrente;
            diaSemanaGroup.style.display = recorrente ? 'block' : 'none';
            diaSemanaSelect.value = diaSemana;
            observacaoInput.value = observacao;
            
            // Atualizar botões
            btnSalvar.innerHTML = '<i class="fas fa-save me-2"></i>Atualizar Disponibilidade';
            btnCancelar.style.display = 'block';
            
            // Scroll para o formulário
            window.scrollTo({
                top: formRegistro.offsetTop - 100,
                behavior: 'smooth'
            });
        });
    });
    
    // Botão cancelar
    btnCancelar.addEventListener('click', function() {
        // Limpar formulário
        formRegistro.reset();
        registroIdInput.value = 0;
        diaSemanaGroup.style.display = 'none';
        
        // Resetar botões
        btnSalvar.innerHTML = '<i class="fas fa-save me-2"></i>Salvar Disponibilidade';
        btnCancelar.style.display = 'none';
    });
});
</script>
<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>Selecione um colaborador para gerenciar sua agenda.
</div>

<div class="row">
    <?php
    // Listar todos os colaboradores instaladores
    $query = "SELECT id, nome, telefone, tipo, status FROM colaboradores WHERE tipo = 'instalador' ORDER BY nome";
    $stmt = $db->query($query);
    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($colaboradores as $colab):
    ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100 border-<?php echo $colab['status'] == 'ativo' ? 'primary' : 'secondary'; ?>">
            <div class="card-header bg-<?php echo $colab['status'] == 'ativo' ? 'primary' : 'secondary'; ?> text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i><?php echo $colab['nome']; ?>
                    <?php if ($colab['status'] != 'ativo'): ?>
                        <span class="badge bg-danger ms-2"><?php echo ucfirst($colab['status']); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <p><i class="fas fa-phone me-2"></i><?php echo $colab['telefone'] ?: 'Telefone não cadastrado'; ?></p>
                <p><i class="fas fa-user-tag me-2"></i><?php echo ucfirst($colab['tipo']); ?></p>
                <?php
                // Contar agendamentos deste colaborador
                $stmtCount = $db->prepare("SELECT COUNT(*) FROM colaborador_agenda WHERE colaborador_id = :id");
                $stmtCount->bindParam(':id', $colab['id'], PDO::PARAM_INT);
                $stmtCount->execute();
                $agenda_count = $stmtCount->fetchColumn();
                ?>
                <p class="mb-0">
                    <i class="fas fa-calendar-check me-2"></i>
                    <?php if ($agenda_count > 0): ?>
                        <span class="text-success"><?php echo $agenda_count; ?> horários cadastrados</span>
                    <?php else: ?>
                        <span class="text-muted">Nenhum horário cadastrado</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="card-footer">
                <a href="colaborador_agenda.php?id=<?php echo $colab['id']; ?>" class="btn btn-primary w-100">
                    <i class="fas fa-calendar-alt me-2"></i>Gerenciar Agenda
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once('includes/footer.php'); ?>