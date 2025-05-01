<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
verificarAutenticacao();

// Verificar permissões
if (!$_SESSION['usuario']['nivel'] === 'admin' && !verificarPermissao('gerenciar_agendamentos')) {
    header('Location: index.php?erro=sem_permissao');
    exit;
}

// Inicialização de variáveis
$mensagem = '';
$orcamento_id = isset($_GET['orcamento_id']) ? intval($_GET['orcamento_id']) : 0;
$agendamento_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$orcamento = null;
$agendamentos = [];
$colaboradores = [];
$agendamento_atual = null;

// Buscar colaboradores instaladores
$stmt = $pdo->query("SELECT id, nome FROM colaboradores WHERE tipo = 'instalador' ORDER BY nome");
$colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se estamos acessando um agendamento específico pelo seu ID
if ($agendamento_id > 0) {
    // Buscar o agendamento pelo seu ID
    $stmt = $pdo->prepare("SELECT a.*, c.nome as colaborador_nome, a.orcamento_id
                        FROM agendamentos a 
                        LEFT JOIN colaboradores c ON a.instalador_id = c.id 
                        WHERE a.id = :agendamento_id");
    $stmt->bindParam(':agendamento_id', $agendamento_id, PDO::PARAM_INT);
    $stmt->execute();
    $agendamento_atual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($agendamento_atual) {
        // Definir o orcamento_id a partir do agendamento
        $orcamento_id = $agendamento_atual['orcamento_id'];
        // Buscar o orçamento
        $orcamento = buscarOrcamento($orcamento_id);
        
        // Buscar todos os agendamentos relacionados a este orçamento
        $stmt = $pdo->prepare("SELECT a.*, c.nome as colaborador_nome 
                            FROM agendamentos a 
                            LEFT JOIN colaboradores c ON a.instalador_id = c.id 
                            WHERE a.orcamento_id = :orcamento_id 
                            ORDER BY a.data_inicio DESC");
        $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
        $stmt->execute();
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
// Se temos um orçamento específico (direto ou via agendamento), vamos carregar seus dados
else if ($orcamento_id > 0) {
    $orcamento = buscarOrcamento($orcamento_id);
    
    // Buscar agendamentos relacionados a este orçamento
    $stmt = $pdo->prepare("SELECT a.*, c.nome as colaborador_nome 
                         FROM agendamentos a 
                         LEFT JOIN colaboradores c ON a.instalador_id = c.id 
                         WHERE a.orcamento_id = :orcamento_id 
                         ORDER BY a.data_inicio DESC");
    $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar se há agendamentos ativos (pendentes ou agendados)
    $has_agendamento_ativo = false;
    foreach ($agendamentos as $agendamento) {
        if ($agendamento['status'] == 'agendado' || $agendamento['status'] == 'pendente') {
            $has_agendamento_ativo = true;
            break;
        }
    }
}

// Processar formulário de novo agendamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'agendar') {
    $orcamento_id = intval($_POST['orcamento_id']);
    $colaborador_id = intval($_POST['colaborador_id']);
    $data_servico = $_POST['data_servico'];
    $hora_inicio = $_POST['hora_inicio'];
    $observacoes = limpaString($_POST['observacoes'] ?? '');
    
    if (empty($orcamento_id) || empty($colaborador_id) || empty($data_servico) || empty($hora_inicio)) {
        $mensagem = alerta('Todos os campos obrigatórios devem ser preenchidos.', 'danger');
    } else {
        try {
            // Carregar orçamento para verificar tempo previsto
            $orcamento = buscarOrcamento($orcamento_id);
            if (!$orcamento) {
                throw new Exception('Orçamento não encontrado.');
            }
            
            // Calcular hora de término com base no tempo previsto
            $data_inicio = $data_servico . ' ' . $hora_inicio . ':00';
            $data_hora_inicio = new DateTime($data_inicio);
            
            // Converter tempo previsto para minutos
            $tempo_previsto = $orcamento['tempo_previsto'];
            $unidade_tempo = $orcamento['unidade_tempo'];
            $duracao_minutos = $tempo_previsto;
            
            if ($unidade_tempo === 'horas') {
                $duracao_minutos = $tempo_previsto * 60;
            } else if ($unidade_tempo === 'dias') {
                $duracao_minutos = $tempo_previsto * 60 * 8; // 8 horas por dia
            }
            
            // Calcular data e hora de término
            $data_hora_fim = clone $data_hora_inicio;
            $data_hora_fim->add(new DateInterval('PT' . $duracao_minutos . 'M'));
            
            // Iniciar transação
            $pdo->beginTransaction();
            
            // Verificar se já existem agendamentos ativos para este orçamento
            $stmt = $pdo->prepare("SELECT id FROM agendamentos WHERE orcamento_id = :orcamento_id AND status = 'agendado'");
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            $agendamentos_ativos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Cancelar todos os agendamentos ativos existentes
            if (count($agendamentos_ativos) > 0) {
                $placeholders = str_repeat('?,', count($agendamentos_ativos) - 1) . '?';
                $stmt = $pdo->prepare("UPDATE agendamentos SET status = 'cancelado', observacoes = CONCAT(observacoes, ' [Cancelado automaticamente devido a reagendamento]') WHERE id IN ($placeholders)");
                $stmt->execute($agendamentos_ativos);
            }
            
            // Verificar se o colaborador está disponível no horário
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agendamentos 
                                 WHERE instalador_id = :colaborador_id 
                                 AND status = 'agendado' 
                                 AND ((data_inicio <= :data_inicio AND data_fim >= :data_inicio) 
                                 OR (data_inicio <= :data_fim AND data_fim >= :data_fim) 
                                 OR (data_inicio >= :data_inicio AND data_fim <= :data_fim))");
            $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
            $data_inicio_str = $data_hora_inicio->format('Y-m-d H:i:s');
            $data_fim_str = $data_hora_fim->format('Y-m-d H:i:s');
            $stmt->bindParam(':data_inicio', $data_inicio_str);
            $stmt->bindParam(':data_fim', $data_fim_str);
            $stmt->execute();
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Colaborador já ocupado nesse horário. Por favor, selecione outro colaborador ou horário.');
            }
            
            // Gerar código de confirmação
            $codigo_confirmacao = md5(uniqid(rand(), true));
            
            // Inserir agendamento
            $stmt = $pdo->prepare("INSERT INTO agendamentos (
                                orcamento_id, instalador_id, data_inicio, data_fim, 
                                status, observacoes, codigo_confirmacao, usuario_id, cliente_agendou,
                                data_agendamento, hora_inicio, hora_fim)
                                VALUES (
                                :orcamento_id, :colaborador_id, :data_inicio, :data_fim, 
                                'agendado', :observacoes, :codigo_confirmacao, :usuario_id, TRUE,
                                :data_agendamento, :hora_inicio, :hora_fim)");
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
            
            // Armazenar em variáveis antes de passar por referência
            $data_inicio_valor = $data_hora_inicio->format('Y-m-d H:i:s');
            $data_fim_valor = $data_hora_fim->format('Y-m-d H:i:s');
            
            $stmt->bindParam(':data_inicio', $data_inicio_valor);
            $stmt->bindParam(':data_fim', $data_fim_valor);
            $stmt->bindParam(':observacoes', $observacoes);
            $stmt->bindParam(':codigo_confirmacao', $codigo_confirmacao);
            $stmt->bindParam(':usuario_id', $_SESSION['usuario_id'], PDO::PARAM_INT);
            
            // Parâmetros para os campos obrigatórios (data_agendamento, hora_inicio, hora_fim)
            $data_agendamento_valor = $data_hora_inicio->format('Y-m-d');
            $hora_inicio_valor = $data_hora_inicio->format('H:i:s');
            $hora_fim_valor = $data_hora_fim->format('H:i:s');
            $stmt->bindParam(':data_agendamento', $data_agendamento_valor);
            $stmt->bindParam(':hora_inicio', $hora_inicio_valor);
            $stmt->bindParam(':hora_fim', $hora_fim_valor);
            $stmt->execute();
            
            // Atualizar status de execução do orçamento
            $stmt = $pdo->prepare("UPDATE orcamentos SET status_execucao = 'agendado' WHERE id = :id");
            $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Commit da transação
            $pdo->commit();
            
            // Verificar se foi um reagendamento ou novo agendamento
            if (count($agendamentos_ativos) > 0) {
                $mensagem = alerta('Reagendamento realizado com sucesso! O agendamento anterior foi cancelado.', 'success');
            } else {
                $mensagem = alerta('Agendamento realizado com sucesso!', 'success');
            }
            
            // Recarregar agendamentos
            $stmt = $pdo->prepare("SELECT a.*, c.nome as colaborador_nome 
                             FROM agendamentos a 
                             LEFT JOIN colaboradores c ON a.instalador_id = c.id 
                             WHERE a.orcamento_id = :orcamento_id 
                             ORDER BY a.data_inicio DESC");
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            // Rollback em caso de erro
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensagem = alerta('Erro ao processar agendamento: ' . $e->getMessage(), 'danger');
        }
    }
}

// Processar cancelamento de agendamento
if (isset($_GET['acao']) && $_GET['acao'] == 'cancelar' && isset($_GET['id'])) {
    $agendamento_id = intval($_GET['id']);
    
    try {
        // Verificar se o agendamento existe
        $stmt = $pdo->prepare("SELECT orcamento_id FROM agendamentos WHERE id = :id");
        $stmt->bindParam(':id', $agendamento_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($resultado = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $orcamento_id = $resultado['orcamento_id'];
            
            // Cancelar agendamento
            $stmt = $pdo->prepare("UPDATE agendamentos SET status = 'cancelado' WHERE id = :id");
            $stmt->bindParam(':id', $agendamento_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Verificar se há outros agendamentos ativos para este orçamento
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agendamentos 
                               WHERE orcamento_id = :orcamento_id AND status = 'agendado'");
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Se não houver outros agendamentos, volta para pendente
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("UPDATE orcamentos SET status_execucao = 'pendente' WHERE id = :id");
                $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            $mensagem = alerta('Agendamento cancelado com sucesso!', 'success');
            
            // Recarregar agendamentos
            $stmt = $pdo->prepare("SELECT a.*, c.nome as colaborador_nome 
                             FROM agendamentos a 
                             LEFT JOIN colaboradores c ON a.instalador_id = c.id 
                             WHERE a.orcamento_id = :orcamento_id 
                             ORDER BY a.data_inicio DESC");
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $mensagem = alerta('Agendamento não encontrado.', 'danger');
        }
    } catch (Exception $e) {
        $mensagem = alerta('Erro ao cancelar agendamento: ' . $e->getMessage(), 'danger');
    }
}

require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>
        <?php if ($orcamento): ?>
            <i class="fas fa-calendar-alt me-2"></i>Agendamento para Orçamento #<?php echo $orcamento['numero']; ?>
        <?php else: ?>
            <i class="fas fa-calendar-alt me-2"></i>Gerenciamento de Agendamentos
        <?php endif; ?>
    </h1>
    <div>
        <?php if ($orcamento): ?>
            <?php if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'relatorio_agendamentos.php') !== false): ?>
                <a href="relatorio_agendamentos.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar para Relatório
                </a>
            <?php else: ?>
                <a href="orcamento_visualizar.php?id=<?php echo $orcamento_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar para Orçamento
                </a>
            <?php endif; ?>
        <?php else: ?>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
        <?php endif; ?>
    </div>
</div>

<?php echo $mensagem; ?>

<?php if ($orcamento): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Orçamento</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">Número:</dt>
                    <dd class="col-sm-8"><?php echo $orcamento['numero']; ?></dd>
                    
                    <dt class="col-sm-4">Cliente:</dt>
                    <dd class="col-sm-8"><?php 
                        $cliente = buscarCliente($orcamento['cliente_id']);
                        echo $cliente ? $cliente['nome'] : 'Cliente não encontrado';
                    ?></dd>
                    
                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo $orcamento['status'] == 'aprovado' ? 'success' : ($orcamento['status'] == 'pendente' ? 'warning' : 'danger'); ?>">
                            <?php echo ucfirst($orcamento['status']); ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-4">Execução:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php 
                            echo $orcamento['status_execucao'] == 'finalizado' ? 'success' : 
                                 ($orcamento['status_execucao'] == 'agendado' ? 'info' : 'secondary'); ?>">
                            <?php echo ucfirst($orcamento['status_execucao']); ?>
                        </span>
                    </dd>
                    
                    <dt class="col-sm-4">Tempo Previsto:</dt>
                    <dd class="col-sm-8"><?php echo $orcamento['tempo_previsto'] . ' ' . $orcamento['unidade_tempo']; ?></dd>
                    
                    <dt class="col-sm-4">Valor Total:</dt>
                    <dd class="col-sm-8">R$ <?php echo number_format($orcamento['valor_total'], 2, ',', '.'); ?></dd>
                </dl>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-plus me-2"></i>
                    <?php if (isset($has_agendamento_ativo) && $has_agendamento_ativo): ?>
                        Reagendar Serviço
                    <?php else: ?>
                        Novo Agendamento
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($has_agendamento_ativo) && $has_agendamento_ativo): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Atenção!</strong> Já existe um agendamento ativo para este orçamento. 
                    Ao reagendar, o agendamento anterior será automaticamente cancelado.
                </div>
                <?php endif; ?>
                <form method="post" action="">
                    <input type="hidden" name="acao" value="agendar">
                    <input type="hidden" name="orcamento_id" value="<?php echo $orcamento_id; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="data_servico" class="form-label required-field">Data de Execução</label>
                            <input type="date" class="form-control" id="data_servico" name="data_servico" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="hora_inicio" class="form-label required-field">Horário de Início</label>
                            <select class="form-select" id="hora_inicio" name="hora_inicio" required>
                                <option value="">Selecione o horário</option>
                                <?php
                                // Calcular hora máxima possível com base no tempo previsto
                                $tempo_previsto = $orcamento ? $orcamento['tempo_previsto'] : 60;
                                $unidade_tempo = $orcamento ? $orcamento['unidade_tempo'] : 'minutos';
                                
                                // Converter tempo previsto para minutos
                                $duracao_minutos = $tempo_previsto;
                                if ($unidade_tempo === 'horas') {
                                    $duracao_minutos = $tempo_previsto * 60;
                                } else if ($unidade_tempo === 'dias') {
                                    $duracao_minutos = $tempo_previsto * 60 * 8; // 8 horas por dia
                                }
                                
                                // Calcular hora máxima para início (17:00 - duração em minutos)
                                $hora_maxima = floor((17 * 60 - $duracao_minutos) / 60);
                                
                                // Garantir que não seja menor que 7h (início do expediente)
                                $hora_maxima = max(7, $hora_maxima);
                                
                                // Gerar opções de horário das 7h até a hora máxima calculada
                                for ($hora = 7; $hora <= $hora_maxima; $hora++) {
                                    $hora_str = str_pad($hora, 2, '0', STR_PAD_LEFT) . ':00';
                                    echo "<option value=\"{$hora_str}\">{$hora_str}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="colaborador_id" class="form-label required-field">Instalador Responsável</label>
                        <select class="form-select" id="colaborador_id" name="colaborador_id" required>
                            <option value="">Selecione um instalador</option>
                            <?php foreach($colaboradores as $colaborador): ?>
                                <option value="<?php echo $colaborador['id']; ?>">
                                    <?php echo $colaborador['nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Selecione data e horário primeiro para ver apenas colaboradores disponíveis.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="2"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check me-2"></i>
                        <?php if (isset($has_agendamento_ativo) && $has_agendamento_ativo): ?>
                            Reagendar Serviço
                        <?php else: ?>
                            Agendar Serviço
                        <?php endif; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Agendamentos</h5>
    </div>
    <div class="card-body">
        <?php if (count($agendamentos) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data/Hora Início</th>
                            <th>Data/Hora Fim</th>
                            <th>Instalador</th>
                            <th>Status</th>
                            <th>Observações</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agendamentos as $agendamento): ?>
                            <tr>
                                <td><?php echo $agendamento['id']; ?></td>
                                <td><?php echo $agendamento['data_inicio'] ? date('d/m/Y H:i', strtotime($agendamento['data_inicio'])) : 'Não definido'; ?></td>
                                <td><?php echo $agendamento['data_fim'] ? date('d/m/Y H:i', strtotime($agendamento['data_fim'])) : 'Não definido'; ?></td>
                                <td><?php echo $agendamento['colaborador_nome']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $agendamento['status'] == 'agendado' ? 'success' : 
                                             ($agendamento['status'] == 'cancelado' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($agendamento['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $agendamento['observacoes']; ?></td>
                                <td>
                                    <?php if ($agendamento['status'] == 'agendado'): ?>
                                        <a href="agendamento.php?orcamento_id=<?php echo $orcamento_id; ?>&acao=cancelar&id=<?php echo $agendamento['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Tem certeza que deseja cancelar este agendamento?')">
                                            <i class="fas fa-calendar-times"></i> Cancelar
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            <i class="fas fa-ban"></i> <?php echo ucfirst($agendamento['status']); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Nenhum agendamento encontrado para este orçamento.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
    <?php if ($agendamento_id > 0 && !$agendamento_atual): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i>Agendamento não encontrado ou não disponível. Verifique se o ID é válido.
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>Selecione um orçamento para gerenciar seus agendamentos.
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- JavaScript para carregar colaboradores disponíveis -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dataServico = document.getElementById('data_servico');
    const horaInicio = document.getElementById('hora_inicio');
    const colaboradorSelect = document.getElementById('colaborador_id');
    
    if (!dataServico || !horaInicio || !colaboradorSelect) return;
    
    const tempoPrevisto = <?php echo $orcamento ? $orcamento['tempo_previsto'] : 60; ?>;
    const unidadeTempo = '<?php echo $orcamento ? $orcamento['unidade_tempo'] : 'minutos'; ?>';
    
    // Função para carregar colaboradores disponíveis
    function carregarColaboradoresDisponiveis() {
        const dataValue = dataServico.value;
        const horaValue = horaInicio.value;
        
        // Verificar se ambos os campos estão preenchidos
        if (!dataValue || !horaValue) return;
        
        // Salvar opção selecionada atualmente
        const selectedValue = colaboradorSelect.value;
        
        // Limpar opções atuais exceto a primeira
        const firstOption = colaboradorSelect.options[0];
        colaboradorSelect.innerHTML = '';
        colaboradorSelect.appendChild(firstOption);
        
        // Definir mensagem de carregamento
        const loadingOption = document.createElement('option');
        loadingOption.text = 'Carregando colaboradores disponíveis...';
        loadingOption.disabled = true;
        colaboradorSelect.appendChild(loadingOption);
        colaboradorSelect.selectedIndex = 1;
        
        // Buscar colaboradores disponíveis via AJAX
        fetch(`ajax/verificar_colaboradores_disponiveis.php?data=${dataValue}&hora=${horaValue}&tempo_previsto=${tempoPrevisto}&unidade_tempo=${unidadeTempo}`)
            .then(response => response.json())
            .then(data => {
                // Remover opção de carregamento
                colaboradorSelect.removeChild(loadingOption);
                
                if (data.status === 'sucesso') {
                    // Preencher select com colaboradores disponíveis
                    if (data.colaboradores.length > 0) {
                        let foundSelected = false;
                        
                        data.colaboradores.forEach(colaborador => {
                            const option = document.createElement('option');
                            option.value = colaborador.id;
                            option.text = colaborador.nome;
                            
                            // Verificar se este era o valor selecionado anteriormente
                            if (selectedValue && colaborador.id == selectedValue) {
                                option.selected = true;
                                foundSelected = true;
                            }
                            
                            colaboradorSelect.appendChild(option);
                        });
                        
                        // Se não encontrou o item selecionado anteriormente, selecionar o primeiro
                        if (!foundSelected && data.colaboradores.length > 0) {
                            colaboradorSelect.selectedIndex = 1;
                        }
                    } else {
                        // Se não há colaboradores disponíveis
                        const naoDisponivelOption = document.createElement('option');
                        naoDisponivelOption.text = 'Nenhum colaborador disponível neste horário';
                        naoDisponivelOption.disabled = true;
                        colaboradorSelect.appendChild(naoDisponivelOption);
                    }
                } else {
                    // Exibir mensagem de erro
                    const erroOption = document.createElement('option');
                    erroOption.text = 'Erro ao carregar colaboradores: ' + data.mensagem;
                    erroOption.disabled = true;
                    colaboradorSelect.appendChild(erroOption);
                }
            })
            .catch(error => {
                // Remover opção de carregamento
                colaboradorSelect.removeChild(loadingOption);
                
                // Exibir mensagem de erro de conexão
                const erroOption = document.createElement('option');
                erroOption.text = 'Erro de conexão ao buscar colaboradores';
                erroOption.disabled = true;
                colaboradorSelect.appendChild(erroOption);
                console.error('Erro:', error);
            });
    }
    
    // Eventos para acionar a busca de colaboradores disponíveis
    dataServico.addEventListener('change', carregarColaboradoresDisponiveis);
    horaInicio.addEventListener('change', carregarColaboradoresDisponiveis);
});
</script>

<?php require_once('includes/footer.php'); ?>