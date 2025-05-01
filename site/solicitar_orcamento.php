<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/functions.php');

// Incluir bibliotecas de notificações
require_once('../includes/notificacoes.php');

// Define a função de notificação de solicitação diretamente
function notificarSolicitacaoOrcamento($cliente_id, $cliente_nome, $dados_adicionais = [], $tipo_solicitacao = 'visita') {
    $link = "../clientes.php?id={$cliente_id}";
    $telefone = isset($dados_adicionais['telefone']) ? $dados_adicionais['telefone'] : '';
    
    // Construir mensagem de notificação
    $mensagem = "NOVA SOLICITAÇÃO: "; 
    
    if ($tipo_solicitacao == 'visita') {
        $mensagem .= "Visita técnica agendada";
    } else {
        $mensagem .= "Orçamento solicitado";
    }
    
    $mensagem .= " por {$cliente_nome}";
    
    if (!empty($telefone)) {
        $mensagem .= " - {$telefone}";
    }
    
    if (isset($dados_adicionais['data_agendamento']) && isset($dados_adicionais['hora_inicio'])) {
        $data_formatada = date('d/m/Y', strtotime($dados_adicionais['data_agendamento']));
        $mensagem .= " para {$data_formatada} às {$dados_adicionais['hora_inicio']}";
    }
    
    // Adicionar notificação com a categoria "solicitacoes"
    return adicionarNotificacao($mensagem, 'warning', $link, 'solicitacoes');
}

function notificarNovoOrcamento($orcamento_id, $numero, $cliente_nome) {
    $link = "../orcamento_visualizar.php?id={$orcamento_id}";
    $mensagem = "NOVO ORÇAMENTO #{$numero} criado para {$cliente_nome}";
    
    // Adicionar notificação com a categoria "orcamentos"
    return adicionarNotificacao($mensagem, 'primary', $link, 'orcamentos');
}

// Garantir acesso às variáveis globais de conexão
global $db, $pdo;

// Obter configurações de horário de funcionamento do banco de dados
$stmt_config = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_inicio', 'horario_fim', 'dias_funcionamento', 'horario_entrada_disponivel', 'horario_almoco_inicio', 'horario_almoco_fim', 'tempo_previsto_visita')");
$config = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);

// Valores padrão caso não existam configurações
$horario_inicio = isset($config['horario_inicio']) ? $config['horario_inicio'] : '08:00';
$horario_fim = isset($config['horario_fim']) ? $config['horario_fim'] : '18:00';
$horario_entrada_disponivel = isset($config['horario_entrada_disponivel']) ? $config['horario_entrada_disponivel'] : '08:30';
$horario_almoco_inicio = isset($config['horario_almoco_inicio']) ? $config['horario_almoco_inicio'] : '12:00';
$horario_almoco_fim = isset($config['horario_almoco_fim']) ? $config['horario_almoco_fim'] : '13:00';
$tempo_previsto_visita = isset($config['tempo_previsto_visita']) ? (int)$config['tempo_previsto_visita'] : 60;
$dias_funcionamento = isset($config['dias_funcionamento']) ? explode(',', $config['dias_funcionamento']) : [1, 2, 3, 4, 5]; // Padrão: Segunda a Sexta

// Inicializar variáveis
$mensagem = '';
$data_selecionada = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
$orcamentista_id = isset($_GET['orcamentista_id']) ? intval($_GET['orcamentista_id']) : 0;
$nome = $telefone = $email = $cpf_cnpj = $endereco = $cidade = $estado = $cep = $observacoes = '';

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
    $cpf_cnpj = trim($_POST['cpf_cnpj']);
    $endereco = trim($_POST['endereco']);
    $cidade = trim($_POST['cidade']);
    $estado = trim($_POST['estado']);
    $cep = trim($_POST['cep']);
    $data_servico = $_POST['data_servico'];
    $hora_inicio = $_POST['hora_inicio'];
    $observacoes = trim($_POST['observacoes']);
    $orcamentista_id = intval($_POST['colaborador_id']);
    
    // Calcular hora de fim (1 hora após a hora de início)
    $hora_fim = date('H:i:s', strtotime($hora_inicio . ' + ' . $tempo_previsto_visita . ' minutes'));
    
    // Validar campos obrigatórios
    if (empty($nome) || empty($telefone) || empty($endereco) || empty($cidade) || empty($data_servico) || empty($hora_inicio) || $orcamentista_id <= 0) {
        $mensagem = alerta('Preencha todos os campos obrigatórios.', 'danger');
    } else {
        try {
            // Verificar se o cliente já existe (por telefone, email ou CPF/CNPJ)
            $where_clauses = [];
            $params = [];
            
            // Adicionar condições para cada campo que possa identificar um cliente
            if (!empty($telefone)) {
                $where_clauses[] = "telefone = :telefone";
                $params[':telefone'] = $telefone;
            }
            
            if (!empty($email)) {
                $where_clauses[] = "email = :email";
                $params[':email'] = $email;
            }
            
            if (!empty($cpf_cnpj)) {
                $where_clauses[] = "cpf_cnpj = :cpf_cnpj";
                $params[':cpf_cnpj'] = $cpf_cnpj;
            }
            
            // Se não tiver nenhum campo para identificar, não busca
            if (empty($where_clauses)) {
                $cliente = false;
            } else {
                // Montar a query usando OR para verificar qualquer um dos campos
                $where_sql = implode(' OR ', $where_clauses);
                $sql = "SELECT id FROM clientes WHERE {$where_sql} LIMIT 1";
                
                $stmt = $pdo->prepare($sql);
                foreach ($params as $param => $value) {
                    $stmt->bindValue($param, $value);
                }
                $stmt->execute();
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Se o cliente não existe, criar um novo
            if (!$cliente) {
                $stmt = $pdo->prepare("INSERT INTO clientes (nome, cpf_cnpj, telefone, email, endereco, cidade, estado, cep, data_cadastro, observacoes) 
                                    VALUES (:nome, :cpf_cnpj, :telefone, :email, :endereco, :cidade, :estado, :cep, NOW(), :observacoes)");
                $stmt->bindParam(':nome', $nome);
                $stmt->bindParam(':cpf_cnpj', $cpf_cnpj);
                $stmt->bindParam(':telefone', $telefone);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':endereco', $endereco);
                $stmt->bindParam(':cidade', $cidade);
                $stmt->bindParam(':estado', $estado);
                $stmt->bindParam(':cep', $cep);
                $stmt->bindParam(':observacoes', $observacoes);
                $stmt->execute();
                
                $cliente_id = $pdo->lastInsertId();
            } else {
                $cliente_id = $cliente['id'];
                
                // Atualizar dados do cliente se necessário
                $stmt = $pdo->prepare("UPDATE clientes SET 
                                    nome = :nome, 
                                    cpf_cnpj = :cpf_cnpj, 
                                    email = :email, 
                                    endereco = :endereco, 
                                    cidade = :cidade,
                                    estado = :estado,
                                    cep = :cep,
                                    observacoes = :observacoes
                                    WHERE id = :id");
                $stmt->bindParam(':nome', $nome);
                $stmt->bindParam(':cpf_cnpj', $cpf_cnpj);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':endereco', $endereco);
                $stmt->bindParam(':cidade', $cidade);
                $stmt->bindParam(':estado', $estado);
                $stmt->bindParam(':cep', $cep);
                $stmt->bindParam(':observacoes', $observacoes);
                $stmt->bindParam(':id', $cliente_id, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            // Criar o agendamento para visita técnica (orçamento)
            // Usar status 'orcamento_agendado' pois será um agendamento com orçamentista
            $status = 'orcamento_agendado';
            
            // Criar orçamento em branco para vincular ao agendamento
            // Calcula a data de validade (30 dias após a data atual)
            $data_validade = date('Y-m-d', strtotime('+30 days'));
            
            // Gerar um número de orçamento no formato padrão usando a função do sistema
            $numero_orcamento = gerarNumeroOrcamento();
            
            // Gerar código de acesso único para acompanhamento externo do orçamento
            $codigo_acesso = md5(uniqid(rand(), true));
            
            $stmt_orcamento = $pdo->prepare("INSERT INTO orcamentos (numero, cliente_id, data_criacao, data_validade, status, codigo_acesso, valor_produtos, valor_mao_obra, valor_total, status_pagamento, status_execucao) 
                                         VALUES (:numero, :cliente_id, NOW(), :data_validade, 'pendente', :codigo_acesso, 0.00, 0.00, 0.00, 'pendente', 'pendente')");
            $stmt_orcamento->bindParam(':numero', $numero_orcamento);
            $stmt_orcamento->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
            $stmt_orcamento->bindParam(':data_validade', $data_validade);
            $stmt_orcamento->bindParam(':codigo_acesso', $codigo_acesso);
            $stmt_orcamento->execute();
            $orcamento_id = $pdo->lastInsertId();
            
            // Gerar notificações para a nova solicitação de orçamento
            $dados_notificacao = [
                'telefone' => $telefone,
                'data_agendamento' => $data_servico,
                'hora_inicio' => $hora_inicio
            ];
            notificarSolicitacaoOrcamento($cliente_id, $nome, $dados_notificacao);
            notificarNovoOrcamento($orcamento_id, $numero_orcamento, $nome);

            // Observações ajustadas para incluir o título como parte das observações
            $observacoes_completas = "Visita para Orçamento - " . $nome . "\n\n" . $observacoes;
            
            $stmt = $pdo->prepare("INSERT INTO agendamentos 
                                  (orcamento_id, instalador_id, data_agendamento, hora_inicio, hora_fim, status, observacoes) 
                                  VALUES (:orcamento_id, :instalador_id, :data_agendamento, :hora_inicio, :hora_fim, :status, :observacoes)");
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->bindParam(':instalador_id', $orcamentista_id, PDO::PARAM_INT);
            $stmt->bindParam(':data_agendamento', $data_servico); // Usamos data_servico em vez de data_agendamento
            $stmt->bindParam(':hora_inicio', $hora_inicio);
            $stmt->bindParam(':hora_fim', $hora_fim);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':observacoes', $observacoes_completas);
            $stmt->execute();
            
            $mensagem = alerta('Solicitação de orçamento agendada com sucesso! Em breve entraremos em contato.', 'success');
            
            // Limpar formulário após sucesso
            $nome = $telefone = $email = $cpf_cnpj = $endereco = $cidade = $estado = $cep = $observacoes = '';
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
    // Definir horários possíveis de trabalho com base nas configurações
    $horarios_possiveis = [];
    $horario_inicio_partes = explode(':', $horario_inicio);
    $horario_fim_partes = explode(':', $horario_fim);
    $hora_inicio = intval($horario_inicio_partes[0]);
    $hora_fim = intval($horario_fim_partes[0]);
    
    // Considerar apenas horas completas para agendar visitas
    for ($hora = $hora_inicio; $hora < $hora_fim; $hora++) {
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
                $horario_fim = date('H:i:s', strtotime($horario . ' + ' . $tempo_previsto_visita . ' minutes'));
                
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
                        <label for="cpf_cnpj" class="form-label fw-bold">CPF/CNPJ</label>
                        <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" value="<?php echo htmlspecialchars($cpf_cnpj); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="endereco" class="form-label fw-bold required-field">Endereço Completo</label>
                    <input type="text" class="form-control" id="endereco" name="endereco" value="<?php echo htmlspecialchars($endereco); ?>" required placeholder="Rua, número, bairro">
                </div>

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label for="cidade" class="form-label fw-bold required-field">Cidade</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" value="<?php echo htmlspecialchars($cidade); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="estado" class="form-label fw-bold">Estado</label>
                        <input type="text" class="form-control" id="estado" name="estado" value="<?php echo htmlspecialchars($estado); ?>" placeholder="SP">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="cep" class="form-label fw-bold">CEP</label>
                        <input type="text" class="form-control" id="cep" name="cep" value="<?php echo htmlspecialchars($cep); ?>" placeholder="00000-000">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="observacoes" class="form-label fw-bold">Observações</label>
                    <textarea class="form-control" id="observacoes" name="observacoes" rows="4" placeholder="Informe: Instalação Nova, Reparo, Manutenção, Lugar Alto, Precisa de Escada, Não precisa de Escada, Tem escada no local, etc."><?php echo htmlspecialchars($observacoes); ?></textarea>
                    <small class="text-muted">Estas informações ajudarão nosso orçamentista a se preparar adequadamente para sua visita.</small>
                </div>
                
                <hr class="my-4">
                <h4 class="mb-3">Agendar Visita Técnica</h4>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Informação:</strong> Um profissional especializado irá até o local para avaliar o serviço. A consulta tem duração estimada de <?php 
                        $horas = intval($tempo_previsto_visita/60);
                        $minutos = $tempo_previsto_visita % 60;
                        if ($horas > 0) {
                            echo $horas . ' hora' . ($horas > 1 ? 's' : '');
                            if ($minutos > 0) echo ' e ' . $minutos . ' minutos';
                        } else {
                            echo $minutos . ' minutos';
                        }
                    ?>.
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
                            
                            // Duração da visita em minutos (configurada no sistema)
                            $duracao_minutos = (int)$tempo_previsto_visita;
                            
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
                        <small class="text-muted">Tempo previsto para esta visita: <?php 
                            $horas = intval($tempo_previsto_visita/60);
                            $minutos = $tempo_previsto_visita % 60;
                            if ($horas > 0) {
                                echo $horas . ' hora' . ($horas > 1 ? 's' : '');
                                if ($minutos > 0) echo ' e ' . $minutos . ' minutos';
                            } else {
                                echo $minutos . ' minutos';
                            }
                        ?>.</small>
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
        
        // Formatar CEP
        const inputCep = document.getElementById('cep');
        if (inputCep) {
            inputCep.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 8) value = value.substring(0, 8);
                
                if (value.length > 5) {
                    e.target.value = value.replace(/^(\d{5})(\d{0,3})$/, '$1-$2');
                } else {
                    e.target.value = value;
                }
            });
        }
        
        // Formatar CPF/CNPJ
        const inputCpfCnpj = document.getElementById('cpf_cnpj');
        if (inputCpfCnpj) {
            inputCpfCnpj.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length > 14) {
                    // Limita a 14 dígitos (CNPJ)
                    value = value.substring(0, 14);
                }
                
                if (value.length > 11) {
                    // CNPJ
                    e.target.value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
                } else if (value.length > 9) {
                    // CPF
                    e.target.value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
                } else if (value.length > 6) {
                    // CPF parcial
                    e.target.value = value.replace(/^(\d{3})(\d{3})(\d{0,3})$/, '$1.$2.$3');
                } else if (value.length > 3) {
                    // CPF parcial
                    e.target.value = value.replace(/^(\d{3})(\d{0,3})$/, '$1.$2');
                } else {
                    e.target.value = value;
                }
            });
        }
        
        // Script para validar o horário e carregar colaboradores disponíveis
        const dataServico = document.getElementById('data_servico');
        const horaInicio = document.getElementById('hora_inicio');
        const colaboradorSelect = document.getElementById('colaborador_id');
        const tempoPrevisto = <?php echo $tempo_previsto_visita; ?>; // Usa o tempo configurado no sistema
        const unidadeTempo = 'minutos';
        
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