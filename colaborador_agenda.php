<?php
$titulo = 'Agenda do Colaborador';
require_once('includes/config.php');
require_once('includes/functions.php');
require_once('includes/header.php');
// O header.php já inclui requisições para db.php e verificação de autenticação

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

// Inicializando a variável global $pdo que já foi incluída
global $pdo;
// O arquivo includes/db.php já foi incluído e inicializa a variável $pdo
// (anteriormente conhecida como $db em algumas partes do sistema)

// Buscar dados do colaborador
if ($colaborador_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = :id");
    $stmt->bindParam(':id', $colaborador_id, PDO::PARAM_INT);
    $stmt->execute();
    $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar se o colaborador existe
    if (!$colaborador) {
        header('Location: colaboradores.php?mensagem=' . urlencode('Colaborador não encontrado.') . '&tipo=danger');
        exit;
    }
    
    // Inicializar a conexão com o banco de dados
    global $pdo;
    
    // Buscar agenda do colaborador - Disponibilidades cadastradas
    $stmt = $pdo->prepare("SELECT * FROM colaborador_agenda WHERE colaborador_id = :colaborador_id ORDER BY data_disponibilidade, hora_inicio");
    $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
    $stmt->execute();
    $disponibilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar agendamentos feitos por clientes para este colaborador
    $stmt_agendamentos = $pdo->prepare("SELECT a.*, o.numero, o.cliente_id, c.nome as cliente_nome, 
                             o.observacoes as detalhes
                             FROM agendamentos a
                             JOIN orcamentos o ON a.orcamento_id = o.id
                             JOIN clientes c ON o.cliente_id = c.id
                             WHERE a.instalador_id = :colaborador_id
                             ORDER BY a.data_inicio");
    $stmt_agendamentos->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
    $stmt_agendamentos->execute();
    $agendamentos_clientes = $stmt_agendamentos->fetchAll(PDO::FETCH_ASSOC);
}

// Processar formulário - Adicionar/Editar disponibilidade
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'salvar') {
    $data_disponibilidade = $_POST['data_disponibilidade'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fim = $_POST['hora_fim'] ?? '';
    
    // Verificar se é indisponibilidade (botão "Registrar Indisponibilidade" foi pressionado)
    $botao = isset($_POST['btnSalvarIndisponibilidade']) ? 'indisponibilidade' : 'disponibilidade';
    $disponivel = ($botao == 'indisponibilidade') ? 0 : (isset($_POST['disponivel']) ? 1 : 0);
    
    // Se for indisponibilidade, usar o motivo selecionado como observação
    $tipo_indisponibilidade = $_POST['tipo_indisponibilidade'] ?? '';
    if ($botao == 'indisponibilidade' && !empty($tipo_indisponibilidade)) {
        $_POST['observacao'] = 'Motivo: ' . $tipo_indisponibilidade . ' - ' . ($_POST['observacao'] ?? '');
    }
    
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
            
            global $pdo;
            if ($registro_id > 0) {
                // Atualizar registro existente
                $stmt = $pdo->prepare("UPDATE colaborador_agenda SET 
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
                $stmt = $pdo->prepare("INSERT INTO colaborador_agenda (
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
            $stmt = $pdo->prepare("SELECT * FROM colaborador_agenda WHERE colaborador_id = :colaborador_id ORDER BY data_disponibilidade, hora_inicio");
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
        // Verificar se é um registro automático pela observação
        $stmt_verificar = $pdo->prepare("SELECT observacao FROM colaborador_agenda WHERE id = :id AND colaborador_id = :colaborador_id");
        $stmt_verificar->bindParam(':id', $registro_id, PDO::PARAM_INT);
        $stmt_verificar->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
        $stmt_verificar->execute();
        $observacao = $stmt_verificar->fetchColumn();
        
        // Se o registro tiver texto indicando que é automático, não permitir exclusão
        if (strpos($observacao, '(automático)') !== false) {
            $mensagem = alerta('Não é possível excluir registros automáticos de indisponibilidade. Eles são gerenciados pelas configurações do sistema.', 'warning');
        } else {
            // Excluir o registro normalmente se não for automático
            $stmt = $pdo->prepare("DELETE FROM colaborador_agenda WHERE id = :id AND colaborador_id = :colaborador_id AND (observacao NOT LIKE '%automático%' OR observacao IS NULL)");
            $stmt->bindParam(':id', $registro_id, PDO::PARAM_INT);
            $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $mensagem = alerta('Registro de disponibilidade excluído com sucesso!', 'success');
            } else {
                $mensagem = alerta('Registro não encontrado ou você não tem permissão para excluí-lo.', 'danger');
            }
        }
        
        // Atualizar lista de disponibilidades
        $stmt = $pdo->prepare("SELECT * FROM colaborador_agenda WHERE colaborador_id = :colaborador_id ORDER BY data_disponibilidade, hora_inicio");
        $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
        $stmt->execute();
        $disponibilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $mensagem = alerta('Erro ao excluir registro: ' . $e->getMessage(), 'danger');
    }
}

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
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary edit-disponibilidade" 
                                                data-id="<?php echo $disponibilidade['id']; ?>"
                                                data-data="<?php echo $disponibilidade['data_disponibilidade']; ?>"
                                                data-inicio="<?php echo $disponibilidade['hora_inicio']; ?>"
                                                data-fim="<?php echo $disponibilidade['hora_fim']; ?>"
                                                data-disponivel="<?php echo $disponibilidade['disponivel']; ?>"
                                                data-obs="<?php echo htmlspecialchars($disponibilidade['observacao']); ?>"
                                                data-recorrente="<?php echo $disponibilidade['recorrente']; ?>"
                                                data-dia="<?php echo $disponibilidade['dia_semana']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?id=<?php echo $colaborador_id; ?>&acao=excluir&registro_id=<?php echo $disponibilidade['id']; ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Tem certeza que deseja excluir este registro de disponibilidade?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">Nenhum registro de disponibilidade cadastrado para este colaborador.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Exibir Agendamentos de Clientes -->
<?php if (isset($agendamentos_clientes) && count($agendamentos_clientes) > 0): ?>
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Agendamentos de Clientes</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Cliente</th>
                        <th>Orçamento</th>
                        <th>Detalhes</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agendamentos_clientes as $agendamento): ?>
                        <tr class="<?php echo $agendamento['status'] == 'concluido' ? 'table-success' : ($agendamento['status'] == 'cancelado' ? 'table-danger' : ''); ?>">
                            <td><?php echo date('d/m/Y', strtotime($agendamento['data_agendamento'])); ?></td>
                            <td>
                                <?php 
                                echo date('H:i', strtotime($agendamento['hora_inicio'])) . ' até ' . 
                                     date('H:i', strtotime($agendamento['hora_fim'])); 
                                ?>
                            </td>
                            <td>
                                <a href="clientes.php?id=<?php echo $agendamento['cliente_id']; ?>">
                                    <?php echo $agendamento['cliente_nome']; ?>
                                </a>
                            </td>
                            <td>
                                <a href="orcamento_visualizar.php?id=<?php echo $agendamento['orcamento_id']; ?>">
                                    #<?php echo $agendamento['numero']; ?>
                                </a>
                            </td>
                            <td><?php echo isset($agendamento['detalhes']) ? $agendamento['detalhes'] : ''; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $agendamento['status'] == 'agendado' ? 'primary' : 
                                        ($agendamento['status'] == 'concluido' ? 'success' : 
                                        ($agendamento['status'] == 'cancelado' ? 'danger' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($agendamento['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
// JavaScript para manipulação do formulário de disponibilidade
document.addEventListener('DOMContentLoaded', function() {
    const recorrenteCheckbox = document.getElementById('recorrente');
    const diaSemanaGroup = document.getElementById('dia_semana_group');
    const indisponivelCheckbox = document.getElementById('indisponivel');
    const disponivelCheckbox = document.getElementById('disponivel');
    const motivoIndisponibilidade = document.getElementById('motivo_indisponibilidade');
    const btnSalvar = document.getElementById('btnSalvar');
    const btnSalvarIndisponibilidade = document.getElementById('btnSalvarIndisponibilidade');
    const btnCancelar = document.getElementById('btnCancelar');
    const registroIdInput = document.getElementById('registro_id');
    
    // Mostrar/ocultar campo de dia da semana quando a opção recorrente for marcada
    recorrenteCheckbox.addEventListener('change', function() {
        diaSemanaGroup.style.display = this.checked ? 'block' : 'none';
        
        // Se for recorrente, preencher com o dia da semana correspondente à data selecionada
        if (this.checked) {
            const dataDisponibilidade = document.getElementById('data_disponibilidade').value;
            if (dataDisponibilidade) {
                const data = new Date(dataDisponibilidade);
                const diaSemana = data.getDay(); // 0=Domingo, 1=Segunda, ...
                document.getElementById('dia_semana').value = diaSemana;
            }
        }
    });
    
    // Quando uma data for selecionada e a opção recorrente estiver marcada, atualizar o dia da semana
    document.getElementById('data_disponibilidade').addEventListener('change', function() {
        if (recorrenteCheckbox.checked && this.value) {
            const data = new Date(this.value);
            const diaSemana = data.getDay();
            document.getElementById('dia_semana').value = diaSemana;
        }
    });
    
    // Configurar botões de edição de disponibilidade
    document.querySelectorAll('.edit-disponibilidade').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const data = this.getAttribute('data-data');
            const inicio = this.getAttribute('data-inicio');
            const fim = this.getAttribute('data-fim');
            const disponivel = this.getAttribute('data-disponivel') === '1';
            const observacao = this.getAttribute('data-obs');
            const recorrente = this.getAttribute('data-recorrente') === '1';
            const diaSemana = this.getAttribute('data-dia');
            
            // Preencher formulário
            registroIdInput.value = id;
            document.getElementById('data_disponibilidade').value = data;
            document.getElementById('hora_inicio').value = inicio;
            document.getElementById('hora_fim').value = fim;
            document.getElementById('disponivel').checked = disponivel;
            document.getElementById('indisponivel').checked = !disponivel;
            document.getElementById('observacao').value = observacao;
            recorrenteCheckbox.checked = recorrente;
            
            if (recorrente) {
                diaSemanaGroup.style.display = 'block';
                document.getElementById('dia_semana').value = diaSemana;
            } else {
                diaSemanaGroup.style.display = 'none';
            }
            
            // Ajustar display dos botões
            btnCancelar.style.display = 'block';
            
            // Se for indisponibilidade, mostrar o motivo
            if (!disponivel) {
                toggleIndisponivel(true);
            } else {
                toggleIndisponivel(false);
            }
            
            // Rolar até o formulário
            document.querySelector('.card-header').scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    // Botão de cancelar edição
    btnCancelar.addEventListener('click', function() {
        // Resetar formulário
        registroIdInput.value = 0;
        document.getElementById('data_disponibilidade').value = '';
        document.getElementById('hora_inicio').value = '';
        document.getElementById('hora_fim').value = '';
        document.getElementById('disponivel').checked = true;
        document.getElementById('indisponivel').checked = false;
        document.getElementById('observacao').value = '';
        recorrenteCheckbox.checked = false;
        diaSemanaGroup.style.display = 'none';
        motivoIndisponibilidade.style.display = 'none';
        btnSalvar.style.display = 'inline-block';
        btnSalvarIndisponibilidade.style.display = 'none';
        btnCancelar.style.display = 'none';
    });
});

// Função para alternar entre disponibilidade e indisponibilidade
function toggleIndisponivel(forceShow = null) {
    const indisponivelCheckbox = document.getElementById('indisponivel');
    const disponivelCheckbox = document.getElementById('disponivel');
    const motivoIndisponibilidade = document.getElementById('motivo_indisponibilidade');
    const btnSalvar = document.getElementById('btnSalvar');
    const btnSalvarIndisponibilidade = document.getElementById('btnSalvarIndisponibilidade');
    
    // Se forceShow for definido, usar esse valor; senão, usar o estado atual do checkbox
    const isIndisponivel = forceShow !== null ? forceShow : indisponivelCheckbox.checked;
    
    if (isIndisponivel) {
        disponivelCheckbox.checked = false;
        motivoIndisponibilidade.style.display = 'block';
        btnSalvar.style.display = 'none';
        btnSalvarIndisponibilidade.style.display = 'inline-block';
    } else {
        disponivelCheckbox.checked = true;
        indisponivelCheckbox.checked = false;
        motivoIndisponibilidade.style.display = 'none';
        btnSalvar.style.display = 'inline-block';
        btnSalvarIndisponibilidade.style.display = 'none';
    }
}
</script>

<?php require_once('includes/footer.php'); ?>
