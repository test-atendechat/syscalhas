<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Verificar permissão para acesso interno
if (!isset($_GET['cliente_view']) && !isset($_GET['codigo_acesso'])) {
    verificarPermissao('gerenciar_agendamentos');
}

// Título da página
$titulo = "Calendário de Agendamentos";

// Parâmetros opcionais
$orcamento_id = isset($_GET['orcamento_id']) ? intval($_GET['orcamento_id']) : 0;
$cliente_view = isset($_GET['cliente_view']) && $_GET['cliente_view'] == 1;
$embed_mode = isset($_GET['embed']) && $_GET['embed'] == 1;
$codigo_acesso = isset($_GET['codigo_acesso']) ? $_GET['codigo_acesso'] : '';

// Se for visualização de cliente, verificar se o orçamento existe e está aprovado
if (($cliente_view || !empty($codigo_acesso)) && $orcamento_id > 0) {
    $stmt = $db->prepare("SELECT o.*, c.nome as cliente_nome FROM orcamentos o 
                         INNER JOIN clientes c ON o.cliente_id = c.id 
                         WHERE o.id = :id AND o.status = 'aprovado'");
    $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // Orçamento não existe ou não está aprovado
        header('Location: orcamentos.php?erro=' . urlencode('Orçamento não encontrado ou não está aprovado.'));
        exit;
    }
    
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obter eventos de agendamento existentes
$stmt = $db->prepare("SELECT 
                        a.id,
                        a.data_agendamento, 
                        a.hora_inicio, 
                        a.hora_fim, 
                        a.status,
                        c.nome as cliente_nome,
                        o.numero as orcamento_numero,
                        a.previsao_tempo,
                        a.temperatura,
                        a.previsao_chuva
                     FROM agendamentos a 
                     INNER JOIN orcamentos o ON a.orcamento_id = o.id
                     INNER JOIN clientes c ON o.cliente_id = c.id
                     WHERE a.status NOT IN ('cancelado')");
$stmt->execute();
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar eventos para o calendário
$events = [];

// Variável para verificar se este orçamento já está agendado
$orcamento_atual_agendado = false;

foreach ($agendamentos as $agendamento) {
    // Se estamos visualizando um orçamento específico, verificar se ele já está agendado
    if ($orcamento_id > 0 && intval($agendamento['orcamento_numero']) === $orcamento_id) {
        $orcamento_atual_agendado = true;
    }
    
    // Definir cores com base no status
    $color = '#3788d8'; // azul padrão
    switch ($agendamento['status']) {
        case 'agendado':
            $color = '#3788d8'; // azul
            break;
        case 'concluido':
            $color = '#28a745'; // verde
            break;
        case 'reagendado':
            $color = '#ffc107'; // amarelo
            break;
    }
    
    // Informações sobre previsão do tempo
    $previsao_info = '';
    if (!empty($agendamento['previsao_tempo'])) {
        $previsao_info = "<br>Previsão: {$agendamento['previsao_tempo']}";
        if (!empty($agendamento['temperatura'])) {
            $previsao_info .= " | {$agendamento['temperatura']}°C";
        }
        $previsao_info .= $agendamento['previsao_chuva'] ? " | <span class='text-danger'>Possibilidade de chuva</span>" : '';
    }
    
    $events[] = [
        'id' => $agendamento['id'],
        'title' => "#{$agendamento['orcamento_numero']} - {$agendamento['cliente_nome']}",
        'start' => "{$agendamento['data_agendamento']}T{$agendamento['hora_inicio']}",
        'end' => "{$agendamento['data_agendamento']}T{$agendamento['hora_fim']}",
        'color' => $color,
        'extendedProps' => [
            'status' => $agendamento['status'],
            'cliente' => $agendamento['cliente_nome'],
            'orcamento' => $agendamento['orcamento_numero'],
            'previsao' => $previsao_info
        ],
        'description' => "<strong>Cliente:</strong> {$agendamento['cliente_nome']}<br>
                          <strong>Status:</strong> " . ucfirst($agendamento['status']) . "
                          $previsao_info"
    ];
}

// Obter dias indisponíveis
$stmt = $db->prepare("SELECT data_inicio, data_fim, motivo FROM indisponibilidades");
$stmt->execute();
$indisponibilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Definir dias indisponíveis como eventos de fundo
foreach ($indisponibilidades as $indisponibilidade) {
    // Converter para objetos DateTime para facilitar o cálculo de intervalo
    $inicio = new DateTime($indisponibilidade['data_inicio']);
    $fim = new DateTime($indisponibilidade['data_fim']);
    $fim->modify('+1 day'); // Incluir o último dia
    
    // Adicionar cada dia no intervalo como evento de fundo
    $intervalo = new DateInterval('P1D');
    $periodo = new DatePeriod($inicio, $intervalo, $fim);
    
    foreach ($periodo as $data) {
        $events[] = [
            'title' => "Indisponível: {$indisponibilidade['motivo']}",
            'start' => $data->format('Y-m-d'),
            'allDay' => true,
            'display' => 'background',
            'color' => '#343a40', // cor escura
            'classNames' => ['indisponivel'],
            'extendedProps' => [
                'indisponivel' => true,
                'motivo' => $indisponibilidade['motivo']
            ]
        ];
    }
}

// Obter horários disponíveis para cada dia da semana
$stmt = $db->prepare("SELECT dia_semana, hora_inicio, hora_fim, disponivel FROM horarios_disponiveis ORDER BY dia_semana, hora_inicio");
$stmt->execute();
$horarios_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar horários por dia da semana para facilitar o acesso no JavaScript
$horarios_por_dia = [];
foreach ($horarios_disponiveis as $horario) {
    $dia = $horario['dia_semana'];
    if (!isset($horarios_por_dia[$dia])) {
        $horarios_por_dia[$dia] = [];
    }
    
    if ($horario['disponivel']) {
        $horarios_por_dia[$dia][] = [
            'inicio' => $horario['hora_inicio'],
            'fim' => $horario['hora_fim']
        ];
    }
}

// Estrutura de página diferente dependendo do modo
if (!$embed_mode) {
    // Incluir cabeçalho normal para página completa
    require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo $titulo; ?></h1>
    <div>
        <?php if (!$cliente_view): ?>
            <a href="agendamento_form.php" class="btn btn-success me-2">
                <i class="fas fa-plus me-2"></i>Novo Agendamento
            </a>
            <a href="agendamentos.php" class="btn btn-secondary">
                <i class="fas fa-list me-2"></i>Lista de Agendamentos
            </a>
        <?php else: ?>
            <a href="orcamento_visualizar.php?id=<?php echo $orcamento_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar ao Orçamento
            </a>
        <?php endif; ?>
    </div>
</div>
<?php } else { ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css' rel='stylesheet'>
    <link href="css/styles.css" rel="stylesheet">
    <style>
        body {
            padding: 0;
            margin: 0;
            background: transparent;
            overflow: hidden;
        }
        .btn-dark {
            background-color: #444;
        }
        .fc-theme-standard .fc-scrollgrid {
            border: 1px solid #ddd;
        }
        .fc td, .fc th {
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>

<!-- Conteúdo simples para o modo incorporado -->
<div class="py-2">
<?php } ?>

<!-- Mensagem para cliente -->
<?php if ($cliente_view && isset($orcamento)): ?>
    <?php if ($orcamento_atual_agendado): ?>
    <div class="alert alert-success mb-4">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Olá, <?php echo $orcamento['cliente_nome']; ?>!</strong> Seu orçamento já possui um agendamento.
        Você pode ver os detalhes da instalação agendada no calendário abaixo.
    </div>
    <?php else: ?>
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Olá, <?php echo $orcamento['cliente_nome']; ?>!</strong> Selecione uma data disponível no calendário para agendar sua instalação.
        As datas em <strong>cinza escuro</strong> não estão disponíveis para agendamento.
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$embed_mode): ?>
<!-- Container do Calendário e Detalhes para modo normal -->
<div class="row">
    <!-- Calendário -->
    <div class="col-lg-9">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-calendar-alt me-2"></i>Calendário de Agendamentos
            </div>
            <div class="card-body">
                <div id="calendario"></div>
            </div>
        </div>
    </div>
    
    <!-- Painel de Detalhes -->
    <div class="col-lg-3">
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-info-circle me-2"></i>Detalhes
            </div>
            <div class="card-body" id="detalhes-agendamento">
                <div class="text-center py-5">
                    <i class="fas fa-calendar-day display-1 text-muted mb-3"></i>
                    <p class="text-muted">Selecione uma data no calendário para ver os detalhes ou agendar um serviço.</p>
                </div>
            </div>
        </div>
        
        <!-- Legenda -->
        <div class="card mt-3">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-tags me-2"></i>Legenda
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span class="badge bg-primary px-3 py-2 me-2" style="width: 30px;"></span>
                    Agendado
                </div>
                <div class="mb-2">
                    <span class="badge bg-success px-3 py-2 me-2" style="width: 30px;"></span>
                    Concluído
                </div>
                <div class="mb-2">
                    <span class="badge bg-warning px-3 py-2 me-2" style="width: 30px;"></span>
                    Reagendado
                </div>
                <div class="mb-2">
                    <span class="badge px-3 py-2 me-2" style="width: 30px; background-color: #343a40;"></span>
                    Indisponível
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Interface simplificada para modo incorporado -->
<div class="card border-0">
    <div class="card-body p-0">
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-info mb-2">
                    <i class="fas fa-info-circle me-2"></i>
                    <span class="fw-bold">Selecione uma data disponível</span> no calendário para agendar sua instalação.
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-8">
                <!-- Calendário compacto para modo incorporado -->
                <div id="calendario"></div>
            </div>
            <div class="col-md-4">
                <!-- Painel informativo compacto -->
                <div id="detalhes-agendamento" class="p-2">
                    <div class="py-3 text-center">
                        <i class="fas fa-calendar-day fs-1 text-muted mb-2"></i>
                        <p class="small text-muted mb-0">Clique em uma data para selecionar o horário de início da instalação.</p>
                    </div>
                </div>
                
                <!-- Legenda simplificada -->
                <div class="mt-3 p-2 border-top">
                    <p class="small fw-bold mb-2">Legenda:</p>
                    <div class="d-flex flex-column gap-2 small">
                        <div>
                            <span class="badge bg-primary me-1" style="width: 15px;"></span>
                            Agendado
                        </div>
                        <div>
                            <span class="badge me-1" style="width: 15px; background-color: #343a40;"></span>
                            Indisponível
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 py-2 small">
                        <i class="fas fa-umbrella me-1"></i> Em caso de previsão de chuva na data selecionada, o serviço poderá ser reagendado para o próximo dia útil disponível.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal de Agendamento Rápido -->
<div class="modal fade" id="agendamentoModal" tabindex="-1" aria-labelledby="agendamentoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="agendamentoModalLabel">Agendar Instalação</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="formAgendamentoRapido">
                    <?php if ($cliente_view): ?>
                        <input type="hidden" id="orcamento_id" name="orcamento_id" value="<?php echo $orcamento_id; ?>">
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="orcamento_id" class="form-label required">Orçamento</label>
                            <select class="form-select" id="orcamento_id" name="orcamento_id" required>
                                <option value="">Selecione um orçamento</option>
                                <?php
                                $stmt = $db->prepare("SELECT o.id, o.numero, c.nome as cliente_nome 
                                                     FROM orcamentos o 
                                                     INNER JOIN clientes c ON o.cliente_id = c.id 
                                                     WHERE o.status = 'aprovado' 
                                                     ORDER BY o.numero DESC");
                                $stmt->execute();
                                $orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($orcamentos as $orcamento) {
                                    echo "<option value=\"{$orcamento['id']}\"># {$orcamento['numero']} - {$orcamento['cliente_nome']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="data_agendamento" class="form-label required">Data</label>
                            <input type="date" class="form-control" id="data_agendamento" name="data_agendamento" required readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="horario" class="form-label required">Horário</label>
                            <select class="form-select" id="horario" name="horario" required>
                                <option value="">Selecione um horário disponível</option>
                                <!-- Será preenchido via JavaScript -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="instaladores" class="form-label">Instaladores</label>
                        <select class="form-select" id="instaladores" name="instaladores[]" multiple>
                            <?php
                            $stmt = $db->prepare("SELECT id, nome FROM instaladores WHERE ativo = true ORDER BY nome ASC");
                            $stmt->execute();
                            $instaladores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($instaladores as $instalador) {
                                echo "<option value=\"{$instalador['id']}\">{$instalador['nome']}</option>";
                            }
                            ?>
                        </select>
                        <div class="form-text">Pressione Ctrl (ou Cmd) para selecionar múltiplos instaladores.</div>
                    </div>
                    
                    <div class="mb-3" id="previsao-container">
                        <!-- Será preenchido via JavaScript quando uma data for selecionada -->
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-salvar-agendamento">Salvar Agendamento</button>
            </div>
        </div>
    </div>
</div>

<!-- Incluir rodapé -->
<?php 
if (!$embed_mode) {
    require_once('includes/footer.php');
} else {
?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php } ?>

<!-- Incluir FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/locales/pt-br.js"></script>

<!-- Estilos personalizados para o calendário -->
<style>
    /* Reduzir tamanho das células do calendário */
    .fc .fc-daygrid-day-frame {
        min-height: 5em !important;
    }
    
    /* Reduzir tamanho dos eventos */
    .fc-event {
        font-size: 0.8rem;
        line-height: 1.2;
        padding: 2px 4px;
        border-radius: 3px;
        margin-bottom: 1px;
    }
    
    /* Melhorar estilo dos ícones nos eventos */
    .fc-event-title i {
        font-size: 0.75rem;
    }
    
    /* Melhorar estilo dos dias indisponíveis */
    .dia-indisponivel {
        background-color: #f8f9fa;
        opacity: 0.6;
    }
    
    /* Estilos para os botões do cabeçalho */
    .fc-button {
        padding: 0.3em 0.6em !important;
        font-size: 0.85rem !important;
    }
    
    /* Cabeçalho da tabela mais compacto */
    .fc-col-header-cell {
        padding: 5px 0 !important;
    }
    
    /* Adicionar hover nos dias */
    .fc-daygrid-day:not(.dia-indisponivel):hover {
        background-color: rgba(13, 110, 253, 0.05);
    }
    
    /* Ajuste para o número do dia */
    .fc-daygrid-day-number {
        font-size: 0.9rem;
        padding: 5px 8px !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dados do PHP para JavaScript
    const events = <?php echo json_encode($events); ?>;
    const horariosPorDia = <?php echo json_encode($horarios_por_dia); ?>;
    const orcamentoId = <?php echo $orcamento_id; ?>;
    const clienteView = <?php echo $cliente_view ? 'true' : 'false'; ?>;
    const orcamentoJaAgendado = <?php echo $orcamento_atual_agendado ? 'true' : 'false'; ?>;
    const hoje = new Date();
    
    // Função para verificar disponibilidade dos dias
    function verificarDisponibilidadeDia(data) {
        // Verificar se a data está no passado
        if (new Date(data) < new Date(hoje.setHours(0, 0, 0, 0))) {
            return { disponivel: false, motivo: 'Data no passado' };
        }
        
        // Verificar se o dia está na lista de indisponibilidades
        const diaIndisponivel = events.some(event => 
            event.extendedProps && event.extendedProps.indisponivel && 
            event.start === data);
            
        if (diaIndisponivel) {
            const eventoIndisponivel = events.find(event => 
                event.extendedProps && event.extendedProps.indisponivel && 
                event.start === data);
            return { 
                disponivel: false, 
                motivo: eventoIndisponivel ? eventoIndisponivel.extendedProps.motivo : 'Dia indisponível' 
            };
        }
        
        // Verificar se é final de semana
        const dataObj = new Date(data);
        const diaSemana = dataObj.getDay(); // 0 = Domingo, 6 = Sábado
        
        if (diaSemana === 0 || diaSemana === 6) {
            return { disponivel: false, motivo: 'Final de semana' };
        }
        
        // Verificar se existem horários disponíveis para este dia da semana
        if (!horariosPorDia[diaSemana] || horariosPorDia[diaSemana].length === 0) {
            return { disponivel: false, motivo: 'Sem horários disponíveis neste dia' };
        }
        
        return { disponivel: true };
    }
    
    // Inicializar o calendário
    const calendarEl = document.getElementById('calendario');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        events: events,
        selectable: !orcamentoJaAgendado,
        selectConstraint: {
            start: hoje.toISOString().split('T')[0], // Hoje
            end: '2030-12-31' // Data futura distante
        },
        weekends: false, // Não exibir finais de semana
        contentHeight: 'auto',
        dayMaxEvents: 3,
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: false,
            hour12: false
        },
        dateClick: function(info) {
            // Verificar se o dia está disponível
            const disponibilidade = verificarDisponibilidaDia(info.dateStr);
            if (!disponibilidade.disponivel) {
                if (clienteView) {
                    // Mensagem amigável para o cliente
                    alert(`Este dia não está disponível: ${disponibilidade.motivo}`);
                }
                return;
            }
            
            // Preencher o formulário de agendamento com a data selecionada
            const formDataAgendamento = document.getElementById('data_agendamento');
            if (formDataAgendamento) {
                formDataAgendamento.value = info.dateStr;
            }
            
            // Preencher os horários disponíveis com base no dia da semana
            const diaSemana = new Date(info.dateStr).getDay();
            const selectHorario = document.getElementById('horario');
            if (selectHorario) {
                // Limpar opções atuais
                selectHorario.innerHTML = '<option value="">Selecione um horário</option>';
                
                // Adicionar horários disponíveis para o dia da semana
                if (horariosPorDia[diaSemana]) {
                    horariosPorDia[diaSemana].forEach(horario => {
                        const option = document.createElement('option');
                        option.value = horario.inicio;
                        option.textContent = horario.inicio;
                        selectHorario.appendChild(option);
                    });
                }
            }
            
            // Verificar se há previsão para o dia (simulação)
            const previsaoContainer = document.getElementById('previsao-container');
            if (previsaoContainer) {
                // Aqui poderia fazer uma chamada AJAX para um serviço de previsão do tempo real
                // Mas usamos uma previsão simulada para este exemplo
                const temChuvaSim = Math.random() > 0.7; // 30% de chance de chuva
                const temperatura = Math.round(15 + Math.random() * 15); // Entre 15 e 30 graus
                
                const classeChuva = temChuvaSim ? 'alert-warning' : 'alert-info';
                const iconeChuva = temChuvaSim ? 'fa-umbrella' : 'fa-sun';
                const textoChuva = temChuvaSim ? 'Há previsão de chuva para esta data' : 'Não há previsão de chuva para esta data';
                
                previsaoContainer.innerHTML = `
                    <div class="alert ${classeChuva} mb-0">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas ${iconeChuva} fa-2x me-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Previsão do tempo: ${temperatura}°C</h6>
                                <p class="mb-0">${textoChuva}</p>
                                ${temChuvaSim ? '<small class="text-danger">Em caso de chuva forte, o serviço poderá ser reagendado.</small>' : ''}
                            </div>
                        </div>
                    </div>`;
            }
            
            // Exibir modal de agendamento
            const modal = new bootstrap.Modal(document.getElementById('agendamentoModal'));
            modal.show();
            
            // Também atualizar o painel de detalhes
            atualizarPainelDetalhes(info.dateStr);
        },
        eventClick: function(info) {
            // Exibir detalhes do agendamento no painel lateral
            const detalheEl = document.getElementById('detalhes-agendamento');
            if (detalheEl) {
                // Formatar data
                const dataObj = new Date(info.event.start);
                const dataFormatada = dataObj.toLocaleDateString('pt-BR');
                
                // Obter informações do evento
                const cliente = info.event.extendedProps.cliente;
                const orcamento = info.event.extendedProps.orcamento;
                const status = info.event.extendedProps.status;
                const previsaoInfo = info.event.extendedProps.previsao || '';
                
                // Formatar horários
                const horaInicio = info.event.start.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
                const horaFim = info.event.end ? info.event.end.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'}) : '';
                
                // Exibir informações
                detalheEl.innerHTML = `
                    <h5 class="border-bottom pb-2 mb-3">Agendamento #${orcamento}</h5>
                    <p><strong>Cliente:</strong> ${cliente}</p>
                    <p><strong>Data:</strong> ${dataFormatada}</p>
                    <p><strong>Horário:</strong> ${horaInicio} - ${horaFim}</p>
                    <p><strong>Status:</strong> <span class="badge ${getBadgeClass(status)}">${ucfirst(status)}</span></p>
                    ${previsaoInfo ? `<div class="mt-3 pt-2 border-top"><strong>Previsão do Tempo:</strong> ${previsaoInfo}</div>` : ''}
                    <div class="d-grid gap-2 mt-4">
                        ${!clienteView ? `<a href="agendamento_form.php?id=${info.event.id}" class="btn btn-primary"><i class="fas fa-edit me-2"></i>Editar</a>` : ''}
                    </div>
                `;
            }
        },
        dayCellDidMount: function(info) {
            // Verificar disponibilidade do dia
            const disponibilidade = verificarDisponibilidadeDia(info.date.toISOString().split('T')[0]);
            if (!disponibilidade.disponivel) {
                // Marcar células indisponíveis
                info.el.classList.add('dia-indisponivel');
                // Criar tooltip ou indicador
                const indicador = document.createElement('div');
                indicador.className = 'position-absolute bottom-0 end-0 m-1 small text-muted';
                indicador.innerHTML = '<i class="fas fa-ban"></i>';
                info.el.style.position = 'relative';
                info.el.appendChild(indicador);
            }
        }
    });
    
    calendar.render();
    
    // Função para atualizar o painel de detalhes com informações do dia
    function atualizarPainelDetalhes(dataStr) {
        const detalheEl = document.getElementById('detalhes-agendamento');
        if (!detalheEl) return;
        
        // Verificar disponibilidade
        const disponibilidade = verificarDisponibilidadeDia(dataStr);
        if (!disponibilidade.disponivel) {
            detalheEl.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Dia Indisponível</strong>
                    <p class="mb-0 mt-2">${disponibilidade.motivo}</p>
                </div>
                <div class="text-center py-3">
                    <i class="fas fa-calendar-times display-1 text-muted mb-3"></i>
                    <p>Por favor, selecione outra data no calendário.</p>
                </div>
            `;
            return;
        }
        
        // Dia disponível, mostrar formulário simplificado
        const dataObj = new Date(dataStr);
        const diaSemana = dataObj.getDay();
        const diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        const dataFormatada = dataObj.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric'});
        
        // Horários disponíveis
        let opcoesHorario = '<option value="">Selecione</option>';
        if (horariosPorDia[diaSemana]) {
            horariosPorDia[diaSemana].forEach(horario => {
                opcoesHorario += `<option value="${horario.inicio}">${horario.inicio}</option>`;
            });
        }
        
        detalheEl.innerHTML = `
            <h5 class="border-bottom pb-2 mb-3">${diasSemana[diaSemana]}, ${dataFormatada}</h5>
            <form action="salvar_agendamento.php" method="post">
                <input type="hidden" name="data_agendamento" value="${dataStr}">
                <input type="hidden" name="orcamento_id" value="${orcamentoId}">
                <div class="mb-3">
                    <label class="form-label">Horário de Início:</label>
                    <select class="form-select" name="hora_inicio" required>${opcoesHorario}</select>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Agendar Serviço</button>
                </div>
            </form>
        `;
    }
    
    // Manipulador para salvar agendamento a partir do modal
    const btnSalvarAgendamento = document.getElementById('btn-salvar-agendamento');
    if (btnSalvarAgendamento) {
        btnSalvarAgendamento.addEventListener('click', function() {
            const form = document.getElementById('formAgendamentoRapido');
            if (!form) return;
            
            // Verificar campos obrigatórios
            const orcamentoId = form.querySelector('#orcamento_id').value;
            const dataAgendamento = form.querySelector('#data_agendamento').value;
            const horario = form.querySelector('#horario').value;
            
            if (!orcamentoId || !dataAgendamento || !horario) {
                alert('Por favor, preencha todos os campos obrigatórios.');
                return;
            }
            
            // Criar formulário para submissão
            const formSubmit = document.createElement('form');
            formSubmit.method = 'post';
            formSubmit.action = 'salvar_agendamento.php';
            formSubmit.style.display = 'none';
            
            // Adicionar campos
            const campos = [
                { name: 'orcamento_id', value: orcamentoId },
                { name: 'data_agendamento', value: dataAgendamento },
                { name: 'hora_inicio', value: horario },
                { name: 'observacoes', value: form.querySelector('#observacoes').value || '' }
            ];
            
            // Adicionar instaladores selecionados
            const selectInstaladores = form.querySelector('#instaladores');
            if (selectInstaladores) {
                const instaladoresSelecionados = Array.from(selectInstaladores.selectedOptions).map(option => option.value);
                campos.push({ name: 'instaladores', value: JSON.stringify(instaladoresSelecionados) });
            }
            
            // Adicionar campos ao formulário
            campos.forEach(campo => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = campo.name;
                input.value = campo.value;
                formSubmit.appendChild(input);
            });
            
            // Adicionar ao documento e submeter
            document.body.appendChild(formSubmit);
            formSubmit.submit();
        });
    }
    
    // Funções auxiliares
    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    function getBadgeClass(status) {
        switch (status) {
            case 'agendado': return 'bg-primary';
            case 'concluido': return 'bg-success';
            case 'reagendado': return 'bg-warning';
            case 'cancelado': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }
    
    function verificarDisponibilidaDia(data) {
        return verificarDisponibilidadeDia(data);
    }
});
</script>