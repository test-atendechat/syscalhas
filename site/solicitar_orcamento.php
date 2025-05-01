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
$orcamentista_id = isset($_GET['orcamentista_id']) ? intval($_GET['orcamentista_id']) : 0;

// Carregar todos os orçamentistas (colaboradores com tipo = 'orcamentista')
try {
    $stmt = $pdo->prepare("SELECT id, nome, tipo FROM colaboradores WHERE tipo = 'orcamentista' AND status = 'ativo' ORDER BY nome");
    $stmt->execute();
    $orcamentistas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mensagem = alerta('Erro ao carregar orçamentistas: ' . $e->getMessage(), 'danger');
}

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
    
    // Calcular hora de fim (1 hora após a hora de início)
    $hora_fim = date('H:i:s', strtotime($hora_inicio . ' + 1 hour'));
    
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
            $titulo = "Visita para Orçamento - " . $nome;
            $tipo = 'orcamento';
            $status = 'agendado';
            
            $stmt = $pdo->prepare("INSERT INTO agendamentos 
                                  (titulo, cliente_id, instalador_id, data_agendamento, hora_inicio, hora_fim, tipo, status, observacoes) 
                                  VALUES (:titulo, :cliente_id, :instalador_id, :data_agendamento, :hora_inicio, :hora_fim, :tipo, :status, :observacoes)");
            $stmt->bindParam(':titulo', $titulo);
            $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt->bindParam(':instalador_id', $orcamentista_id, PDO::PARAM_INT);
            $stmt->bindParam(':data_agendamento', $data_agendamento);
            $stmt->bindParam(':hora_inicio', $hora_inicio);
            $stmt->bindParam(':hora_fim', $hora_fim);
            $stmt->bindParam(':tipo', $tipo);
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

// Verificar horários disponíveis do orçamentista selecionado
if ($orcamentista_id > 0 && !empty($data_selecionada)) {
    // Definir horários possíveis de trabalho (8h às 18h, intervalo de 1h)
    $horarios_possiveis = [];
    for ($hora = 8; $hora < 18; $hora++) {
        $horarios_possiveis[] = sprintf("%02d:00:00", $hora);
    }
    
    try {
        // Buscar agendamentos existentes do orçamentista na data selecionada
        $stmt = $pdo->prepare("SELECT hora_inicio, hora_fim FROM agendamentos 
                            WHERE instalador_id = :instalador_id 
                            AND data_agendamento = :data_agendamento");
        $stmt->bindParam(':instalador_id', $orcamentista_id, PDO::PARAM_INT);
        $stmt->bindParam(':data_agendamento', $data_selecionada);
        $stmt->execute();
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Remover horários já agendados
        $horarios_ocupados = [];
        foreach ($agendamentos as $agendamento) {
            $inicio = date('H:i:s', strtotime($agendamento['hora_inicio']));
            $fim = date('H:i:s', strtotime($agendamento['hora_fim']));
            
            foreach ($horarios_possiveis as $horario) {
                $horario_fim = date('H:i:s', strtotime($horario . ' + 1 hour'));
                
                // Verificar se há sobreposição
                if (($inicio <= $horario && $fim > $horario) || 
                    ($inicio < $horario_fim && $fim >= $horario_fim) || 
                    ($inicio >= $horario && $fim <= $horario_fim)) {
                    $horarios_ocupados[] = $horario;
                }
            }
        }
        
        // Filtrar horários disponíveis
        $horarios_disponiveis = array_diff($horarios_possiveis, $horarios_ocupados);
    } catch (Exception $e) {
        $mensagem = alerta('Erro ao verificar horários disponíveis: ' . $e->getMessage(), 'danger');
    }
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
                        <label for="orcamentista_id" class="form-label fw-bold">Orçamentista*</label>
                        <select name="orcamentista_id" id="orcamentista_id" class="form-select" required>
                            <option value="">Selecione um orçamentista</option>
                            <?php foreach ($orcamentistas as $orcamentista): ?>
                                <option value="<?php echo $orcamentista['id']; ?>" <?php echo ($orcamentista_id == $orcamentista['id']) ? 'selected' : ''; ?>>
                                    <?php echo $orcamentista['nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="data_agendamento" class="form-label fw-bold">Data da Visita*</label>
                        <input type="date" class="form-control" id="data_agendamento" name="data_agendamento" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               value="<?php echo $data_selecionada; ?>" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="hora_inicio" class="form-label fw-bold">Horário para a Visita*</label>
                    <input type="hidden" name="hora_inicio" id="hora_inicio" required>
                    
                    <div id="horarios-disponiveis" class="mt-2">
                        <?php if ($orcamentista_id > 0 && !empty($data_selecionada)): ?>
                            <?php if (count($horarios_disponiveis) > 0): ?>
                                <?php foreach ($horarios_disponiveis as $horario): ?>
                                    <div class="time-slot" data-hora="<?php echo $horario; ?>">
                                        <?php echo substr($horario, 0, 5); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Não há horários disponíveis para esta data. Por favor, selecione outra data.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Selecione um orçamentista e uma data para ver os horários disponíveis.
                            </div>
                        <?php endif; ?>
                    </div>
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
        
        // Selecionar horário
        const timeSlots = document.querySelectorAll('.time-slot');
        const horaInicioInput = document.getElementById('hora_inicio');
        
        timeSlots.forEach(slot => {
            slot.addEventListener('click', function() {
                // Remover seleção anterior
                timeSlots.forEach(s => s.classList.remove('selected'));
                
                // Selecionar este horário
                this.classList.add('selected');
                
                // Atualizar campo oculto
                horaInicioInput.value = this.dataset.hora;
            });
        });
        
        // Atualizar horários disponíveis quando mudar data ou orçamentista
        const orcamentistaSelect = document.getElementById('orcamentista_id');
        const dataAgendamentoInput = document.getElementById('data_agendamento');
        
        function atualizarHorarios() {
            if (orcamentistaSelect.value && dataAgendamentoInput.value) {
                window.location.href = 'solicitar_orcamento.php?orcamentista_id=' + 
                                        orcamentistaSelect.value + 
                                        '&data=' + dataAgendamentoInput.value;
            }
        }
        
        if (orcamentistaSelect) {
            orcamentistaSelect.addEventListener('change', atualizarHorarios);
        }
        
        if (dataAgendamentoInput) {
            dataAgendamentoInput.addEventListener('change', atualizarHorarios);
        }
        
        // Validar formulário antes de enviar
        const form = document.getElementById('formAgendamento');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!horaInicioInput.value) {
                    e.preventDefault();
                    alert('Por favor, selecione um horário para a visita.');
                }
            });
        }
    });
    </script>
</body>
</html>