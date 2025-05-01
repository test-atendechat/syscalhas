<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/functions.php');

// Garantir acesso às variáveis globais de conexão
global $db, $pdo;

// Definir constantes para o horário de funcionamento
$horario_inicio = "08:00"; // 8h da manhã
$horario_fim = "18:00";     // 6h da tarde
$dias_funcionamento = [1, 2, 3, 4, 5]; // Segunda a sexta (1-5)

// Inicializar variáveis
$mensagem = '';
$data_selecionada = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$orcamentista_id = isset($_GET['orcamentista_id']) ? intval($_GET['orcamentista_id']) : 0;
$nome = $telefone = $email = $endereco = $cidade = $observacoes = '';

// Duração padrão para visitas de orçamento (1 hora)
$tempo_previsto = 1;
$unidade_tempo = 'horas';

// Carregar todos os orçamentistas (colaboradores com tipo = 'orcamentista')
try {
    $stmt = $pdo->prepare("SELECT id, nome FROM colaboradores WHERE tipo = 'orcamentista' AND status = 'ativo' ORDER BY nome");
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
    $data_servico = $_POST['data_servico'];
    $hora_inicio = $_POST['hora_inicio'];
    $observacoes = trim($_POST['observacoes']);
    $orcamentista_id = intval($_POST['colaborador_id']);
    
    // Calcular hora de fim (1 hora após a hora de início)
    $hora_fim = date('H:i:s', strtotime($hora_inicio . ' + 1 hour'));
    
    // Validar campos obrigatórios
    if (empty($nome) || empty($telefone) || empty($endereco) || empty($cidade) || empty($data_servico) || empty($hora_inicio) || $orcamentista_id <= 0) {
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
            $stmt->bindParam(':data_agendamento', $data_servico); // Usamos data_servico em vez de data_agendamento
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
            
            // Redirecionar para a página inicial após 5 segundos
            echo "<meta http-equiv='refresh' content='5;url=index.php'>";

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
                        <label for="nome" class="form-label fw-bold required-field">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="telefone" class="form-label fw-bold required-field">Telefone (WhatsApp)</label>
                        <input type="tel" class="form-control" id="telefone" name="telefone" placeholder="(00) 00000-0000" value="<?php echo htmlspecialchars($telefone); ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label fw-bold">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="cidade" class="form-label fw-bold required-field">Cidade</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo htmlspecialchars($cidade); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="endereco" class="form-label fw-bold required-field">Endereço Completo</label>
                    <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($endereco); ?>" required placeholder="Rua, número, bairro">
                </div>
                
                <div class="mb-3">
                    <label for="observacoes" class="form-label fw-bold">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Descreva brevemente o serviço desejado ou qualquer informação adicional"><?php echo htmlspecialchars($observacoes); ?></textarea>
                </div>
                
                <hr class="my-4">
                <h4 class="mb-3">Agendar Visita Técnica</h4>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Informação:</strong> Um profissional especializado irá até o local para avaliar o serviço. A consulta tem duração estimada de 1 hora.
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="data_servico" class="form-label required-field">Data da Visita</label>
                        <input type="date" class="form-control" id="data_servico" name="data_servico" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               value="<?php echo $data_selecionada; ?>" required>
                        <small class="text-muted">Selecione a data desejada para a visita técnica</small>
                    </div>
                    <div class="col-md-6">
                        <label for="hora_inicio" class="form-label required-field">Horário da Visita</label>
                        <select class="form-select" id="hora_inicio" name="hora_inicio" required>
                            <option value="">Selecione um horário</option>
                            <?php
                            // Criando horários de 1 em 1 hora, das 8h às 17h
                            $horario_inicio_partes = explode(':', $horario_inicio);
                            $horario_fim_partes = explode(':', $horario_fim);
                            $horas_inicio = (int)$horario_inicio_partes[0];
                            $minutos_inicio = isset($horario_inicio_partes[1]) ? (int)$horario_inicio_partes[1] : 0;
                            $horas_fim = (int)$horario_fim_partes[0];
                            $minutos_fim = isset($horario_fim_partes[1]) ? (int)$horario_fim_partes[1] : 0;
                            
                            // Converter para minutos totais para facilitar os cálculos
                            $minutos_total_inicio = $horas_inicio * 60 + $minutos_inicio;
                            $minutos_total_fim = $horas_fim * 60 + $minutos_fim;
                            
                            // Duração da visita em minutos (1 hora)
                            $duracao_minutos = 60;
                            
                            // O último horário possível é o horário final menos a duração da visita
                            $hora_maxima_minutos = $minutos_total_fim - $duracao_minutos;
                            $hora_maxima_horas = floor($hora_maxima_minutos / 60);
                            $hora_maxima_mins = $hora_maxima_minutos % 60;
                            
                            // Garantir que não seja menor que o horário de início
                            $hora_maxima_horas = max($horas_inicio, $hora_maxima_horas);
                            
                            // Criar intervalo de horas em incrementos de 1 hora
                            for ($hora = $horas_inicio; $hora <= $hora_maxima_horas; $hora++) {
                                // Para a primeira hora, considerar os minutos de início
                                $min_inicial = ($hora == $horas_inicio) ? $minutos_inicio : 0;
                                // Para a última hora, considerar os minutos calculados
                                $max_min = ($hora == $hora_maxima_horas) ? $hora_maxima_mins : 59;
                                
                                // Adicionar opções para cada hora disponível em incrementos de 30 min
                                for ($min = $min_inicial; $min <= $max_min; $min += 30) {
                                    if ($min == 60) continue; // Pular quando for exatamente 60 minutos
                                    $hora_str = str_pad($hora, 2, '0', STR_PAD_LEFT) . ':' . str_pad($min, 2, '0', STR_PAD_LEFT);
                                    echo "<option value=\"{$hora_str}\">{$hora_str}</option>";
                                }
                            }
                            ?>
                        </select>
                        <small class="text-muted">Horário de trabalho: <?php echo $horario_inicio; ?> às <?php echo $horario_fim; ?>.</small>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="colaborador_id" class="form-label required-field">Orçamentista</label>
                        <select class="form-select" id="colaborador_id" name="colaborador_id" required>
                            <option value="">Selecione um orçamentista disponível</option>
                            <?php
                            // A lista será carregada via JavaScript, dependendo da data e hora selecionadas
                            $selected_id = $orcamentista_id ?? 0;
                            if ($selected_id > 0) {
                                // Buscar o colaborador diretamente na tabela de colaboradores
                                $stmt = $pdo->prepare("SELECT id, nome FROM colaboradores WHERE id = :id AND tipo = 'orcamentista' AND status = 'ativo'");
                                $stmt->bindParam(':id', $selected_id, PDO::PARAM_INT);
                                $stmt->execute();
                                
                                if ($colaborador = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$colaborador['id']}\" selected>{$colaborador['nome']}</option>";
                                }
                            }
                            ?>
                        </select>
                        <small class="text-muted">Tempo previsto para esta visita: 1 hora.</small>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Importante:</strong> Apenas orçamentistas disponíveis para este horário são mostrados na lista.
                    Os horários de trabalho são das <?php echo $horario_inicio; ?> às <?php echo $horario_fim; ?> horas.
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
        
        // Script para validar o horário e carregar colaboradores disponíveis
        const dataServico = document.getElementById('data_servico');
        const horaInicio = document.getElementById('hora_inicio');
        const colaboradorSelect = document.getElementById('colaborador_id');
        const tempoPrevisto = 1; // 1 hora para visita de orçamento
        const unidadeTempo = 'horas';
        
        // Converter tempo previsto para minutos
        let duracaoMinutos = tempoPrevisto;
        if (unidadeTempo === 'horas') {
            duracaoMinutos = tempoPrevisto * 60;
        } else if (unidadeTempo === 'dias') {
            duracaoMinutos = tempoPrevisto * 60 * 8; // Considerando 8 horas por dia
        }
        
        // Função para carregar colaboradores disponíveis
        function carregarColaboradoresDisponiveis() {
            const dataValue = dataServico.value;
            const horaValue = horaInicio.value;
            
            // Verificar se ambos os campos estão preenchidos
            if (!dataValue || !horaValue) return;
            
            // Limpar opções atuais exceto a primeira
            const firstOption = colaboradorSelect.options[0];
            colaboradorSelect.innerHTML = '';
            colaboradorSelect.appendChild(firstOption);
            
            // Definir mensagem de carregamento
            const loadingOption = document.createElement('option');
            loadingOption.text = 'Carregando orçamentistas disponíveis...';
            loadingOption.disabled = true;
            colaboradorSelect.appendChild(loadingOption);
            
            // Fazer a requisição AJAX para verificar colaboradores disponíveis
            fetch('../ajax/verificar_colaboradores_disponiveis.php?tipo=orcamentista&data=' + dataValue + '&hora_inicio=' + horaValue + '&tempo_previsto=' + tempoPrevisto + '&unidade_tempo=' + unidadeTempo)
                .then(response => response.json())
                .then(data => {
                    // Remover opção de carregamento
                    colaboradorSelect.removeChild(loadingOption);
                    
                    if (data.status === 'sucesso') {
                        // Adicionar opções de colaboradores disponíveis
                        data.colaboradores.forEach(colaborador => {
                            const option = document.createElement('option');
                            option.value = colaborador.id;
                            option.text = colaborador.nome;
                            colaboradorSelect.appendChild(option);
                        });
                        
                        if (data.colaboradores.length === 0) {
                            const naoDisponivelOption = document.createElement('option');
                            naoDisponivelOption.text = 'Nenhum orçamentista disponível neste horário';
                            naoDisponivelOption.disabled = true;
                            colaboradorSelect.appendChild(naoDisponivelOption);
                        }
                    } else {
                        const errorOption = document.createElement('option');
                        errorOption.text = data.mensagem || 'Erro ao carregar orçamentistas';
                        errorOption.disabled = true;
                        colaboradorSelect.appendChild(errorOption);
                    }
                })
                .catch(error => {
                    // Remover opção de carregamento
                    colaboradorSelect.removeChild(loadingOption);
                    
                    const errorOption = document.createElement('option');
                    errorOption.text = 'Erro ao carregar orçamentistas: ' + error.message;
                    errorOption.disabled = true;
                    colaboradorSelect.appendChild(errorOption);
                });
        }
        
        // Registrar ouvintes de eventos para data e hora
        if (dataServico && horaInicio && colaboradorSelect) {
            dataServico.addEventListener('change', carregarColaboradoresDisponiveis);
            horaInicio.addEventListener('change', carregarColaboradoresDisponiveis);
        }
        
        // Validar formulário antes de enviar
        const form = document.getElementById('formAgendamento');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!colaboradorSelect.value) {
                    e.preventDefault();
                    alert('Por favor, selecione um orçamentista disponível.');
                    return;
                }
                
                if (!dataServico.value) {
                    e.preventDefault();
                    alert('Por favor, selecione uma data para a visita.');
                    return;
                }
                
                if (!horaInicio.value) {
                    e.preventDefault();
                    alert('Por favor, selecione um horário para a visita.');
                    return;
                }
            });
        }
        
        // Carregar colaboradores ao iniciar a página se os campos já estiverem preenchidos
        if (dataServico && horaInicio && dataServico.value && horaInicio.value) {
            carregarColaboradoresDisponiveis();
        }
    });
    </script>
</body>
</html>