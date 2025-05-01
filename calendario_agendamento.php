<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Título da página
$titulo = "Calendário de Agendamentos";

// Parâmetros opcionais
$orcamento_id = isset($_GET['orcamento_id']) ? intval($_GET['orcamento_id']) : 0;
$cliente_view = isset($_GET['cliente_view']) && $_GET['cliente_view'] == 1;

// Se for visualização de cliente, verificar se o orçamento existe e está aprovado
if ($cliente_view && $orcamento_id > 0) {
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

// Incluir cabeçalho
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

<!-- Container do Calendário e Detalhes -->
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
<?php require_once('includes/footer.php'); ?>

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
            return { disponivel: false, motivo: eventoIndisponivel.extendedProps.motivo };
        }
        
        // Verificar se o dia tem horários disponíveis
        const diaSemana = new Date(data).getDay();
        if (!horariosPorDia[diaSemana] || horariosPorDia[diaSemana].length === 0) {
            return { disponivel: false, motivo: 'Não há horários disponíveis neste dia' };
        }
        
        // Verificar se já existem agendamentos para este dia e se ainda há horários disponíveis
        const agendamentosNoDia = events.filter(event => 
            !event.extendedProps?.indisponivel && 
            event.start.split('T')[0] === data && 
            event.extendedProps?.status !== 'cancelado');
            
        // Se todos os horários estiverem ocupados, o dia não está disponível
        if (agendamentosNoDia.length >= horariosPorDia[diaSemana].length) {
            return { disponivel: false, motivo: 'Todos os horários estão ocupados' };
        }
        
        return { disponivel: true };
    }

    // Função para carregar horários disponíveis para um dia selecionado
    function carregarHorariosDisponiveis(data) {
        const diaSemana = new Date(data).getDay();
        const selectHorario = document.getElementById('horario');
        selectHorario.innerHTML = '<option value="">Selecione um horário disponível</option>';
        
        // Limpar horários anteriores
        while (selectHorario.options.length > 1) {
            selectHorario.remove(1);
        }
        
        // Se não há horários para este dia, retornar
        if (!horariosPorDia[diaSemana] || horariosPorDia[diaSemana].length === 0) {
            return;
        }
        
        // Obter agendamentos existentes para este dia
        const agendamentosNoDia = events.filter(event => 
            !event.extendedProps?.indisponivel && 
            event.start.split('T')[0] === data && 
            event.extendedProps?.status !== 'cancelado');
            
        // Horários já agendados neste dia
        const horariosOcupados = agendamentosNoDia.map(event => {
            return { inicio: event.start.split('T')[1], fim: event.end.split('T')[1] };
        });
        
        // Adicionar apenas horários disponíveis que não estão ocupados
        horariosPorDia[diaSemana].forEach(horario => {
            // Verificar se este horário está ocupado
            const horarioOcupado = horariosOcupados.some(ocupado => 
                ocupado.inicio === horario.inicio);
                
            if (!horarioOcupado) {
                const option = document.createElement('option');
                option.value = `${horario.inicio}|${horario.fim}`;
                option.textContent = `${horario.inicio.substring(0, 5)} às ${horario.fim.substring(0, 5)}`;
                selectHorario.appendChild(option);
            }
        });
    }

    // Configuração do calendário
    const calendarEl = document.getElementById('calendario');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listWeek'
        },
        locale: 'pt-br',
        events: events,
        selectable: true,
        dayMaxEvents: 3, // Limitar o número de eventos por dia
        height: 'auto', // Ajustar altura automaticamente
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: false
        },
        eventDisplay: 'block',
        eventTextColor: '#fff',
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            list: 'Lista'
        },
        displayEventTime: true,
        selectConstraint: {
            // Impedir seleção de dias indisponíveis
            start: hoje.toISOString().split('T')[0], // Hoje
            end: '2099-12-31' // Data futura distante
        },
        selectAllow: function(selectInfo) {
            const resultado = verificarDisponibilidadeDia(selectInfo.startStr);
            return resultado.disponivel;
        },
        dayCellClassNames: function(arg) {
            // Adicionar classes para dias indisponíveis para estilização
            const resultado = verificarDisponibilidadeDia(arg.date.toISOString().split('T')[0]);
            return resultado.disponivel ? [] : ['dia-indisponivel'];
        },
        dayCellDidMount: function(arg) {
            // Adicionar estilo visual para dias indisponíveis
            const resultado = verificarDisponibilidadeDia(arg.date.toISOString().split('T')[0]);
            if (!resultado.disponivel) {
                arg.el.style.backgroundColor = '#343a40';
                arg.el.style.color = '#aaa';
                arg.el.style.cursor = 'not-allowed';
                
                // Adicionar tooltip com motivo da indisponibilidade
                if (resultado.motivo) {
                    arg.el.title = `Indisponível: ${resultado.motivo}`;
                }
            }
        },
        select: function(info) {
            // Verificar se o dia selecionado está indisponível
            const dataStr = info.startStr;
            const diaIndisponivel = events.some(event => 
                event.extendedProps && event.extendedProps.indisponivel && 
                event.start === dataStr);
            
            if (diaIndisponivel) {
                // Dia indisponível, mostrar mensagem
                const motivo = events.find(event => 
                    event.extendedProps && event.extendedProps.indisponivel && 
                    event.start === dataStr).extendedProps.motivo || 'Não há horários disponíveis';
                    
                document.getElementById('detalhes-agendamento').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-ban me-2"></i>
                        <strong>Data indisponível</strong><br>
                        Motivo: ${motivo}
                    </div>
                    <p class="text-muted">Por favor, selecione outra data para agendamento.</p>
                `;
            } else {
                // Dia disponível, mostrar opções de agendamento
                const diaSemana = new Date(info.startStr).getDay();
                const horariosDisponiveis = horariosPorDia[diaSemana] || [];
                
                if (horariosDisponiveis.length === 0) {
                    // Sem horários disponíveis neste dia
                    document.getElementById('detalhes-agendamento').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Sem horários disponíveis</strong><br>
                            Não há horários disponíveis para esta data.
                        </div>
                        <p class="text-muted">Por favor, selecione outra data para agendamento.</p>
                    `;
                } else {
                    // Mostrar detalhes e opções para agendar
                    const dataFormatada = new Date(info.startStr).toLocaleDateString('pt-BR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    });
                    
                    let opcoesHtml = '';
                    horariosDisponiveis.forEach(horario => {
                        opcoesHtml += `<option value="${horario.inicio}-${horario.fim}">${horario.inicio} - ${horario.fim}</option>`;
                    });
                    
                    // Mostrar detalhes no painel lateral
                    document.getElementById('detalhes-agendamento').innerHTML = `
                        <h5 class="text-primary">${dataFormatada}</h5>
                        <div class="alert alert-success mb-3">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Data disponível para agendamento</strong>
                        </div>
                        <p><strong>Horários disponíveis:</strong></p>
                        <ul class="list-group mb-3">
                            ${horariosDisponiveis.map(h => `<li class="list-group-item">${h.inicio} - ${h.fim}</li>`).join('')}
                        </ul>
                        ${(!orcamentoJaAgendado || !clienteView) ? `
                        <button class="btn btn-primary w-100" id="btn-agendar-modal" data-data="${info.startStr}">
                            <i class="fas fa-calendar-plus me-2"></i>Agendar instalação
                        </button>
                        ` : `
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Orçamento já agendado!</strong><br>
                            Este orçamento já possui um agendamento ativo. Selecione a data com o agendamento para ver os detalhes.
                        </div>
                        `}
                    `;
                    
                    // Adicionar evento ao botão de agendamento se ele existir
                    const btnAgendar = document.getElementById('btn-agendar-modal');
                    if (btnAgendar) {
                        btnAgendar.addEventListener('click', function() {
                        // Preencher formulário modal
                        document.getElementById('data_agendamento').value = this.getAttribute('data-data');
                        
                        // Preencher select de horários
                        const selectHorario = document.getElementById('horario');
                        selectHorario.innerHTML = '<option value="">Selecione um horário disponível</option>';
                        horariosDisponiveis.forEach(horario => {
                            const option = document.createElement('option');
                            option.value = `${horario.inicio}|${horario.fim}`;
                            option.textContent = `${horario.inicio} - ${horario.fim}`;
                            selectHorario.appendChild(option);
                        });
                        
                        // Consultar previsão do tempo (simulado neste exemplo)
                        const dataFormatada = new Date(document.getElementById('data_agendamento').value).toLocaleDateString('pt-BR', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric'
                        });
                        
                        // Gerar uma previsão aleatória para demonstração
                        const climas = [
                            { icone: 'sun', descricao: 'Ensolarado', temp: '28°C', prob_chuva: '0%', cor: 'success' },
                            { icone: 'cloud-sun', descricao: 'Parcialmente nublado', temp: '24°C', prob_chuva: '10%', cor: 'info' },
                            { icone: 'cloud', descricao: 'Nublado', temp: '22°C', prob_chuva: '20%', cor: 'secondary' },
                            { icone: 'cloud-rain', descricao: 'Chuva leve', temp: '19°C', prob_chuva: '40%', cor: 'warning' },
                            { icone: 'cloud-showers-heavy', descricao: 'Chuva forte', temp: '17°C', prob_chuva: '80%', cor: 'danger' }
                        ];
                        
                        // Selecionar um clima aleatório (em produção, isso viria da API de clima)
                        const climaIndex = Math.floor(Math.random() * climas.length);
                        const clima = climas[climaIndex];
                        
                        document.getElementById('previsao-container').innerHTML = `
                            <div class="card border-${clima.cor} mb-3 shadow-sm">
                                <div class="card-header bg-${clima.cor} bg-opacity-10 d-flex align-items-center">
                                    <i class="fas fa-${clima.icone} me-2 text-${clima.cor}"></i>
                                    <span class="fw-semibold">Previsão do Tempo para ${dataFormatada}</span>
                                </div>
                                <div class="card-body py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="me-4 text-center">
                                            <i class="fas fa-${clima.icone} text-${clima.cor} fa-2x mb-2"></i>
                                            <div class="fw-bold">${clima.temp}</div>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">${clima.descricao}</h6>
                                            <div class="small text-muted d-flex align-items-center">
                                                <i class="fas fa-tint me-1 text-primary"></i> 
                                                Probabilidade de chuva: ${clima.prob_chuva}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-${clima.cor} bg-opacity-10 small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Esta previsão é uma estimativa. Recomendamos verificar novamente mais próximo à data.
                                </div>
                            </div>
                        `;
                        
                        // Abrir modal
                        const modal = new bootstrap.Modal(document.getElementById('agendamentoModal'));
                        modal.show();
                    });
                }
            }
        },
        eventClick: function(info) {
            // Mostrar detalhes do evento no painel lateral
            const evento = info.event;
            const props = evento.extendedProps;
            
            if (props && props.indisponivel) {
                // Mostrar informações sobre indisponibilidade
                document.getElementById('detalhes-agendamento').innerHTML = `
                    <h5 class="text-danger">${new Date(evento.start).toLocaleDateString('pt-BR')}</h5>
                    <div class="alert alert-danger">
                        <i class="fas fa-ban me-2"></i>
                        <strong>Data indisponível</strong><br>
                        Motivo: ${props.motivo}
                    </div>
                    <p class="text-muted">Por favor, selecione outra data para agendamento.</p>
                `;
            } else {
                // Mostrar detalhes do agendamento
                const dataFormatada = new Date(evento.start).toLocaleDateString('pt-BR');
                const horaInicio = new Date(evento.start).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
                const horaFim = new Date(evento.end).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
                
                let statusHtml = '';
                switch (props.status) {
                    case 'agendado':
                        statusHtml = '<span class="badge bg-primary">Agendado</span>';
                        break;
                    case 'concluido':
                        statusHtml = '<span class="badge bg-success">Concluído</span>';
                        break;
                    case 'reagendado':
                        statusHtml = '<span class="badge bg-warning">Reagendado</span>';
                        break;
                }
                
                document.getElementById('detalhes-agendamento').innerHTML = `
                    <h5 class="mb-3">${evento.title}</h5>
                    <div class="card mb-3">
                        <div class="card-body">
                            <p class="mb-2"><strong>Data:</strong> ${dataFormatada}</p>
                            <p class="mb-2"><strong>Horário:</strong> ${horaInicio} - ${horaFim}</p>
                            <p class="mb-2"><strong>Status:</strong> ${statusHtml}</p>
                            ${props.previsao ? `<div class="mt-3 alert alert-info small">${props.previsao}</div>` : ''}
                        </div>
                    </div>
                    ${!clienteView ? `
                    <div class="d-grid gap-2">
                        <a href="agendamento_form.php?id=${evento.id}" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Editar Agendamento
                        </a>
                    </div>
                    ` : ''}
                `;
            }
        },
        // Renderização personalizada dos eventos
        eventContent: function(arg) {
            const evento = arg.event;
            const props = evento.extendedProps;
            
            if (props && props.indisponivel) {
                // Renderização para dias indisponíveis
                return { html: '' }; // Apenas o background escuro
            } else {
                // Renderização para agendamentos
                let iconClass = 'fas fa-tools'; // ícone padrão
                
                switch (props.status) {
                    case 'concluido':
                        iconClass = 'fas fa-check-circle';
                        break;
                    case 'reagendado':
                        iconClass = 'fas fa-sync-alt';
                        break;
                }
                
                return {
                    html: `
                        <div class="fc-event-time">${new Date(evento.start).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'})}</div>
                        <div class="fc-event-title">
                            <i class="${iconClass} me-1"></i> ${evento.title}
                        </div>
                    `
                };
            }
        }
    });
    
    calendar.render();
    
    // Handler para salvar agendamento
    document.getElementById('btn-salvar-agendamento').addEventListener('click', function() {
        const form = document.getElementById('formAgendamentoRapido');
        const formData = new FormData(form);
        
        // Validar campos obrigatórios
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        // Capturar valores do formulário
        const orcamentoId = formData.get('orcamento_id');
        const dataAgendamento = formData.get('data_agendamento');
        const horarioSplit = formData.get('horario').split('|');
        const horaInicio = horarioSplit[0];
        const horaFim = horarioSplit[1];
        const observacoes = formData.get('observacoes');
        
        // Capturar instaladores selecionados
        const instaladoresSelect = document.getElementById('instaladores');
        const instaladoresSelecionados = Array.from(instaladoresSelect.selectedOptions).map(option => option.value);
        
        // Criar um objeto com os dados do agendamento
        const agendamentoData = {
            orcamento_id: orcamentoId,
            data_agendamento: dataAgendamento,
            hora_inicio: horaInicio,
            hora_fim: horaFim,
            observacoes: observacoes,
            instaladores: instaladoresSelecionados
        };
        
        // Preparar os dados para URL e formatar data para apresentação
        const dataFormatada = new Date(dataAgendamento).toLocaleDateString('pt-BR');
        const horaFormatada = horaInicio.substring(0, 5);
        
        // O ideal seria enviar via AJAX, mas vamos fazer o redirecionamento simples
        if (clienteView) {
            // Se for visualização do cliente, redirecionar para o orçamento com mensagem de sucesso
            const codigo = new URLSearchParams(window.location.search).get('codigo_acesso');
            if (codigo) {
                window.location.href = `orcamento_visualizar.php?codigo=${codigo}&mensagem=agendado&data=${dataFormatada}&hora=${horaFormatada}`;
            } else {
                // Redirecionar para o formulário de agendamento completo
                window.location.href = `agendamento_form.php?orcamento_id=${orcamentoId}&data=${dataAgendamento}&hora_inicio=${horaInicio}&hora_fim=${horaFim}`;
            }
        } else {
            // Se for acesso interno (admin), redirecionar para o formulário de agendamento completo
            window.location.href = `agendamento_form.php?orcamento_id=${orcamentoId}&data=${dataAgendamento}&hora_inicio=${horaInicio}&hora_fim=${horaFim}`;
        }
    });
});
</script>

<style>
/* Estilos personalizados para o calendário */
.fc-daygrid-day.fc-day-past {
    opacity: 0.7;
}

.fc-day-today {
    background-color: rgba(var(--cor-principal-rgb), 0.1) !important;
}

.fc-event {
    cursor: pointer;
    border-radius: 4px;
    font-size: 0.85em;
}

.fc-event-time {
    font-weight: bold;
}

/* Dias indisponíveis */
.fc-daygrid-day.indisponivel {
    background-color: #343a40;
    color: #fff;
    cursor: not-allowed;
}

.fc-daygrid-day-events {
    min-height: 2em;
}

.fc-day-sat, .fc-day-sun {
    background-color: rgba(0,0,0,0.05);
}

.fc .fc-toolbar-title {
    text-transform: capitalize;
}

/* Responsividade */
@media (max-width: 768px) {
    .fc .fc-toolbar {
        flex-direction: column;
        gap: 0.5em;
    }
    
    .fc-view-harness {
        height: auto !important;
        min-height: 400px;
    }
}
</style>