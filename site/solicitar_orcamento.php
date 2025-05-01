<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/functions.php');

// Garantir acesso às variáveis globais de conexão
global $db, $pdo;

// Inicializar variáveis
$mensagem = '';
$horarios_disponiveis = [];
$orcamentistas = [];
$data_selecionada = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$hora_selecionada = isset($_GET['hora']) ? $_GET['hora'] : '';
$orcamentista_id = isset($_POST['orcamentista_id']) ? intval($_POST['orcamentista_id']) : 0;

// Não buscaremos mais os orçamentistas via PHP. Isso será feito via AJAX no frontend.
// O código JavaScript irá fazer a chamada para 'ajax/verificar_orcamentistas_disponiveis.php'

// Processar formulário de agendamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agendar'])) {
    // Validar dados
    $nome = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $email = trim($_POST['email']);
    $endereco = trim($_POST['endereco']);
    $cidade = trim($_POST['cidade']);
    $data_agendamento = $_POST['data_agendamento'];
    $hora_inicio = $_POST['hora_inicio'];
    $observacoes = trim($_POST['observacoes']);
    $orcamentista_id = intval($_POST['orcamentista_id']);
    
    // Buscar configurações de tempo para visita técnica
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'tempo_visita_tecnica' LIMIT 1");
    $stmt->execute();
    $tempo_visita = $stmt->fetchColumn() ?: 60; // Tempo padrão: 60 minutos se não configurado
    
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'unidade_tempo_visita' LIMIT 1");
    $stmt->execute();
    $unidade_tempo = $stmt->fetchColumn() ?: 'minutos'; // Unidade padrão: minutos
    
    // Calcular duração em minutos
    $duracao_minutos = ($unidade_tempo == 'horas') ? ($tempo_visita * 60) : $tempo_visita;
    
    // Calcular hora de fim com base nas configurações
    $hora_fim = date('H:i:s', strtotime($hora_inicio . ' + ' . $duracao_minutos . ' minutes'));
    
    // Validar campos obrigatórios
    if (empty($nome) || empty($telefone) || empty($endereco) || empty($cidade) || empty($data_agendamento) || empty($hora_inicio) || $orcamentista_id <= 0) {
        $mensagem = alerta('Preencha todos os campos obrigatórios.', 'danger');
    } else {
        try {
            // Verificar se o cliente já existe (por telefone)
            $stmt = $pdo->prepare("SELECT id FROM clientes WHERE telefone = :telefone LIMIT 1");
            $stmt->bindParam(':telefone', $telefone);
            $stmt->execute();
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Se o cliente não existe, criar um novo
            if (!$cliente) {
                $stmt = $pdo->prepare("INSERT INTO clientes (nome, telefone, email, endereco, cidade, data_cadastro) 
                                    VALUES (:nome, :telefone, :email, :endereco, :cidade, NOW())");
                $stmt->bindParam(':nome', $nome);
                $stmt->bindParam(':telefone', $telefone);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':endereco', $endereco);
                $stmt->bindParam(':cidade', $cidade);
                $stmt->execute();
                
                $cliente_id = $pdo->lastInsertId();
            } else {
                $cliente_id = $cliente['id'];
                
                // Atualizar dados do cliente se necessário
                $stmt = $pdo->prepare("UPDATE clientes SET nome = :nome, email = :email, endereco = :endereco, cidade = :cidade 
                                    WHERE id = :id");
                $stmt->bindParam(':nome', $nome);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':endereco', $endereco);
                $stmt->bindParam(':cidade', $cidade);
                $stmt->bindParam(':id', $cliente_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Criar o agendamento para visita técnica (orçamento)
            $tipo = 'orcamento';
            $status = 'agendado';
            
            // Calcular data e hora de início/fim no formato timestamp para compatibilidade
            $data_hora_inicio = new DateTime($data_agendamento . ' ' . $hora_inicio);
            $data_hora_fim = new DateTime($data_agendamento . ' ' . $hora_fim);
            $data_inicio = $data_hora_inicio->format('Y-m-d H:i:s');
            $data_fim = $data_hora_fim->format('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("INSERT INTO agendamentos 
                                  (cliente_id, instalador_id, data_agendamento, hora_inicio, hora_fim, 
                                   data_inicio, data_fim, status, observacoes, cliente_agendou) 
                                  VALUES 
                                  (:cliente_id, :instalador_id, :data_agendamento, :hora_inicio, :hora_fim, 
                                   :data_inicio, :data_fim, :status, :observacoes, TRUE)");
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':instalador_id', $orcamentista_id, PDO::PARAM_INT);
            $stmt->bindParam(':data_agendamento', $data_agendamento);
            $stmt->bindParam(':hora_inicio', $hora_inicio);
            $stmt->bindParam(':hora_fim', $hora_fim);
            $stmt->bindParam(':data_inicio', $data_inicio);
            $stmt->bindParam(':data_fim', $data_fim);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':observacoes', $observacoes);
            $stmt->execute();
            
            $mensagem = alerta('Solicitação de orçamento agendada com sucesso! Em breve entraremos em contato.', 'success');
            
            // Limpar formulário após sucesso
            $nome = $telefone = $email = $endereco = $cidade = $observacoes = '';
            $data_selecionada = date('Y-m-d');
            $orcamentista_id = 0;
        } catch (Exception $e) {
            $mensagem = alerta('Erro ao agendar orçamento: ' . $e->getMessage(), 'danger');
        }
    }
}

// Buscar configurações de horário do banco de dados
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_inicio', 'horario_fim', 'dias_funcionamento')");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Valores padrão caso não existam configurações
$horario_inicio = isset($config['horario_inicio']) ? $config['horario_inicio'] : '07:00';
$horario_fim = isset($config['horario_fim']) ? $config['horario_fim'] : '17:00';

// Extrair hora e converter para número
$hora_inicial = (int)substr($horario_inicio, 0, 2);
$hora_final = (int)substr($horario_fim, 0, 2);

// Gerar horários disponíveis baseados nas configurações
$horarios_possiveis = [];
for ($hora = $hora_inicial; $hora < $hora_final; $hora++) {
    $horarios_possiveis[] = sprintf("%02d:00:00", $hora);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Orçamento - <?php echo APP_NAME; ?></title>
    
    <!-- Meta tags para SEO -->
    <meta name="description" content="Solicite um orçamento personalizado para serviços de calhas e rufos. Atendemos Descalvado, Porto Ferreira e região.">
    <meta name="keywords" content="orçamento, calhas, rufos, agendar, visita técnica">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/styles.css" rel="stylesheet">
    <style>
        body {
            padding-top: 0;
            background-color: #f8f9fa;
        }
        .header {
            background-color: #0d6efd;
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0;
        }
        .form-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
            padding: 30px;
        }
        .time-slot {
            display: inline-block;
            margin: 5px;
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .time-slot:hover {
            background-color: #e9ecef;
        }
        .time-slot.selected {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .time-slot.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #f8f9fa;
        }
        .success-message {
            text-align: center;
            padding: 30px;
        }
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        .footer {
            background-color: #343a40;
            color: white;
            padding: 40px 0;
            margin-top: 40px;
        }
        .footer a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        .footer a:hover {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="fas fa-calculator me-2"></i>Solicitar Orçamento</h1>
                <a href="index.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-2"></i>Voltar para o site
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <?php echo $mensagem; ?>
        
        <div class="form-section">
            <h2 class="mb-4">Agende uma visita técnica</h2>
            <p class="lead mb-4">Preencha o formulário abaixo para solicitar um orçamento personalizado. Nossa equipe irá à sua residência ou empresa para avaliar suas necessidades.</p>
            
            <form method="post" id="formAgendamento">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nome" class="form-label fw-bold">Nome Completo*</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="telefone" class="form-label fw-bold">Telefone (WhatsApp)*</label>
                        <input type="tel" class="form-control" id="telefone" name="telefone" placeholder="(00) 00000-0000" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label fw-bold">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="cidade" class="form-label fw-bold">Cidade*</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="endereco" class="form-label fw-bold">Endereço Completo*</label>
                    <input type="text" class="form-control" id="endereco" name="endereco" required placeholder="Rua, número, bairro">
                </div>
                
                <div class="mb-3">
                    <label for="observacoes" class="form-label fw-bold">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Descreva brevemente o serviço desejado ou qualquer informação adicional"></textarea>
                </div>
                
                <hr class="my-4">
                <h4 class="mb-3">Escolha o orçamentista e a data para a visita</h4>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="data_agendamento" class="form-label fw-bold">Data da Visita*</label>
                        <input type="date" class="form-control" id="data_agendamento" name="data_agendamento" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               value="<?php echo $data_selecionada; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="selecionar_hora" class="form-label fw-bold">Horário para a Visita*</label>
                        <select id="selecionar_hora" class="form-select">
                            <option value="">Primeiro selecione uma hora</option>
                            <?php foreach ($horarios_possiveis as $horario): ?>
                                <option value="<?php echo $horario; ?>" <?php echo ($hora_selecionada == $horario) ? 'selected' : ''; ?>>
                                    <?php echo substr($horario, 0, 5); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="hora_inicio" id="hora_inicio" value="<?php echo $hora_selecionada; ?>" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="orcamentista_id" class="form-label fw-bold">Orçamentista Disponível*</label>
                    <select name="orcamentista_id" id="orcamentista_id" class="form-select" required>
                        <option value="">Selecione data e horário para ver os orçamentistas disponíveis</option>
                    </select>
                    <div id="mensagem-orcamentistas" class="alert alert-info mt-2" style="display: none;"></div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="agendar" class="btn btn-primary btn-lg">
                        <i class="fas fa-calendar-check me-2"></i>Agendar Visita para Orçamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3"><?php echo APP_NAME; ?></h5>
                    <p>Soluções profissionais em calhas e rufos para sua casa ou empresa. Atendemos Descalvado, Porto Ferreira e região.</p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3">Contato</h5>
                    <p><i class="fas fa-phone me-2"></i> Descalvado: (19) 99222-6892</p>
                    <p><i class="fas fa-phone me-2"></i> Porto Ferreira: (19) 99262-0970</p>
                    <p><i class="fas fa-envelope me-2"></i> contato@calhas.com</p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Links Rápidos</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php"><i class="fas fa-home me-2"></i>Início</a></li>
                        <li><a href="solicitar_orcamento.php"><i class="fas fa-calculator me-2"></i>Solicitar Orçamento</a></li>
                        <li><a href="../login.php"><i class="fas fa-sign-in-alt me-2"></i>Área do Cliente</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-light">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Formatar telefone
        const inputTelefone = document.getElementById('telefone');
        if (inputTelefone) {
            inputTelefone.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.substring(0, 11);
                
                if (value.length > 10) {
                    // Celular com DDD (11 dígitos)
                    e.target.value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
                } else if (value.length > 6) {
                    // Telefone com DDD (10 dígitos)
                    e.target.value = value.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
                } else if (value.length > 2) {
                    // Telefone com DDD (é digitando ainda)
                    e.target.value = value.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
                } else {
                    e.target.value = value;
                }
            });
        }
        
        // Selecionar horário e atualizar orçamentistas disponíveis via AJAX
        const selecionarHoraSelect = document.getElementById('selecionar_hora');
        const dataAgendamentoInput = document.getElementById('data_agendamento');
        const horaInicioInput = document.getElementById('hora_inicio');
        const orcamentistaSelect = document.getElementById('orcamentista_id');
        const mensagemOrcamentistas = document.getElementById('mensagem-orcamentistas');
        
        // Função para carregar orçamentistas disponíveis via AJAX
        function carregarOrcamentistasDisponiveis() {
            // Verificar se data e hora foram selecionadas
            if (!dataAgendamentoInput.value || !selecionarHoraSelect.value) {
                return;
            }
            
            // Atualizar campo oculto de hora
            horaInicioInput.value = selecionarHoraSelect.value;
            
            // Limpar select de orçamentistas
            orcamentistaSelect.innerHTML = '';
            
            // Adicionar opção de carregamento
            const loadingOption = document.createElement('option');
            loadingOption.text = 'Carregando orçamentistas disponíveis...';
            loadingOption.disabled = true;
            orcamentistaSelect.appendChild(loadingOption);
            orcamentistaSelect.selectedIndex = 0;
            
            // Exibir mensagem de carregamento
            mensagemOrcamentistas.textContent = 'Verificando orçamentistas disponíveis...';
            mensagemOrcamentistas.className = 'alert alert-info mt-2';
            mensagemOrcamentistas.style.display = 'block';
            
            // Fazer requisição AJAX
            // Garantir que a hora está no formato correto (HH:MM:SS)
            let horaAjustada = selecionarHoraSelect.value;
            if (!horaAjustada.includes(':')) {
                horaAjustada += ':00:00';
            } else if (horaAjustada.split(':').length === 2) {
                horaAjustada += ':00';
            }
            
            fetch(`../ajax/verificar_orcamentistas_disponiveis.php?data=${dataAgendamentoInput.value}&hora=${horaAjustada}`)
                .then(response => response.json())
                .then(data => {
                    // Remover opção de carregamento
                    orcamentistaSelect.removeChild(loadingOption);
                    
                    // Adicionar opção padrão
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.text = 'Selecione um orçamentista disponível';
                    orcamentistaSelect.appendChild(defaultOption);
                    
                    if (data.status === 'sucesso') {
                        mensagemOrcamentistas.style.display = 'none';
                        
                        // Preencher select com orçamentistas disponíveis
                        if (data.orcamentistas && data.orcamentistas.length > 0) {
                            data.orcamentistas.forEach(orcamentista => {
                                const option = document.createElement('option');
                                option.value = orcamentista.id;
                                option.text = orcamentista.nome;
                                orcamentistaSelect.appendChild(option);
                            });
                        } else {
                            // Se não há orçamentistas disponíveis
                            mensagemOrcamentistas.textContent = 'Não há orçamentistas disponíveis neste horário. Por favor, selecione outro horário ou data.';
                            mensagemOrcamentistas.className = 'alert alert-warning mt-2';
                            mensagemOrcamentistas.style.display = 'block';
                        }
                    } else {
                        // Exibir mensagem de erro
                        mensagemOrcamentistas.textContent = 'Erro ao verificar orçamentistas disponíveis: ' + (data.mensagem || 'Erro desconhecido');
                        mensagemOrcamentistas.className = 'alert alert-danger mt-2';
                        mensagemOrcamentistas.style.display = 'block';
                    }
                })
                .catch(error => {
                    // Remover opção de carregamento
                    if (loadingOption.parentNode) {
                        orcamentistaSelect.removeChild(loadingOption);
                    }
                    
                    // Adicionar opção padrão
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.text = 'Erro ao carregar orçamentistas';
                    orcamentistaSelect.appendChild(defaultOption);
                    
                    // Exibir mensagem de erro de conexão
                    mensagemOrcamentistas.textContent = 'Erro de conexão ao buscar orçamentistas disponíveis.';
                    mensagemOrcamentistas.className = 'alert alert-danger mt-2';
                    mensagemOrcamentistas.style.display = 'block';
                    
                    console.error('Erro:', error);
                });
        }
        
        // Configuração dos eventos para carregar orçamentistas disponíveis
        if (selecionarHoraSelect) {
            selecionarHoraSelect.addEventListener('change', carregarOrcamentistasDisponiveis);
        }
        
        if (dataAgendamentoInput) {
            dataAgendamentoInput.addEventListener('change', function() {
                // Resetar o select de horário e orçamentista ao mudar a data
                if (selecionarHoraSelect) {
                    selecionarHoraSelect.selectedIndex = 0;
                }
                horaInicioInput.value = '';
                
                // Limpar orçamentistas
                orcamentistaSelect.innerHTML = '';
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.text = 'Selecione data e horário para ver os orçamentistas disponíveis';
                orcamentistaSelect.appendChild(defaultOption);
                
                // Esconder mensagem
                mensagemOrcamentistas.style.display = 'none';
            });
        }
        
        // Se já temos data e hora selecionadas na página, carregar orçamentistas
        if (dataAgendamentoInput.value && horaInicioInput.value) {
            carregarOrcamentistasDisponiveis();
        }
        
        // Validar formulário antes de enviar
        const form = document.getElementById('formAgendamento');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                // Verificar se há um horário selecionado
                if (!horaInicioInput.value) {
                    e.preventDefault();
                    alert('Por favor, selecione um horário para a visita.');
                    return;
                }
                
                // Verificar se há um orçamentista selecionado
                if (!orcamentistaSelect.value) {
                    e.preventDefault();
                    alert('Por favor, selecione um orçamentista para a visita.');
                    return;
                }
            });
        }
    });
    </script>
</body>
</html>