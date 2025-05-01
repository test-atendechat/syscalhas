<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Processar apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Verificar se tem agendamento existente para este orçamento
$orcamento_id = isset($_POST['orcamento_id']) ? intval($_POST['orcamento_id']) : 0;

if ($orcamento_id <= 0) {
    header('Location: index.php?mensagem=' . urlencode('Orçamento inválido'));
    exit;
}

$stmt = $db->prepare("SELECT COUNT(*) FROM agendamentos WHERE orcamento_id = :orcamento_id AND status != 'cancelado'");
$stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->fetchColumn() > 0) {
    // Já existe um agendamento para este orçamento
    $redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'orcamentos.php';
    
    // Verificar se a URL já tem parâmetros
    $separador = (strpos($redirect_url, '?') !== false) ? '&' : '?';
    
    header("Location: {$redirect_url}{$separador}erro=" . urlencode('Este orçamento já possui um agendamento'));
    exit;
}

// Coletar dados do formulário
$data_agendamento = isset($_POST['data_agendamento']) ? $_POST['data_agendamento'] : null;
$hora_inicio = isset($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null;
$codigo_acesso = isset($_POST['codigo_acesso']) ? $_POST['codigo_acesso'] : null;

// Verificar instaladores selecionados
$instaladores = isset($_POST['instalador_id']) ? (array)$_POST['instalador_id'] : [];

// Validar dados
if (empty($data_agendamento) || empty($hora_inicio)) {
    $redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'orcamentos.php';
    
    // Verificar se a URL já tem parâmetros
    $separador = (strpos($redirect_url, '?') !== false) ? '&' : '?';
    
    header("Location: {$redirect_url}{$separador}erro=" . urlencode('Data e hora de início são obrigatórios'));
    exit;
}

// Validar se pelo menos um instalador foi selecionado
if (empty($instaladores)) {
    $redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'orcamentos.php';
    
    // Verificar se a URL já tem parâmetros
    $separador = (strpos($redirect_url, '?') !== false) ? '&' : '?';
    
    header("Location: {$redirect_url}{$separador}erro=" . urlencode('Selecione pelo menos um instalador disponível'));
    exit;
}

// Verificar se o orçamento existe e está aprovado
$stmt = $db->prepare("SELECT o.*, c.nome as cliente_nome, 
                      (SELECT tempo_previsto_horas FROM orcamentos WHERE id = :id) as tempo_previsto
                      FROM orcamentos o 
                      INNER JOIN clientes c ON o.cliente_id = c.id 
                      WHERE o.id = :id AND o.status = 'aprovado'");
$stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    // Orçamento não existe ou não está aprovado
    header('Location: orcamentos.php?erro=' . urlencode('Orçamento não encontrado ou não está aprovado'));
    exit;
}

$orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular hora de término com base no tempo previsto
$tempo_previsto = isset($orcamento['tempo_previsto_horas']) ? intval($orcamento['tempo_previsto_horas']) : 2;

// Formato da hora: HH:MM
$hora_inicio_partes = explode(':', $hora_inicio);
$hora_inicio_horas = intval($hora_inicio_partes[0]);
$hora_inicio_minutos = isset($hora_inicio_partes[1]) ? intval($hora_inicio_partes[1]) : 0;

// Calcular hora fim
$hora_fim_timestamp = strtotime("+{$tempo_previsto} hours", strtotime("{$data_agendamento} {$hora_inicio}"));
$hora_fim = date('H:i', $hora_fim_timestamp);

// Obter previsão do tempo (simulado para este exemplo)
$previsao_tempo = "Parcialmente nublado";
$temperatura = rand(15, 30); // Temperatura entre 15 e 30 graus
$previsao_chuva = (rand(0, 10) > 7); // 30% de chance de chuva

// Iniciar transação
$db->beginTransaction();

try {
    // Obter ID do usuário ou cliente que está agendando
    $usuario_id = isset($_SESSION['usuario']['id']) ? $_SESSION['usuario']['id'] : null;
    $cliente_agendou = true; // Assumimos que é cliente se está vindo do link externo
    
    // Inserir o agendamento
    $stmt = $db->prepare("INSERT INTO agendamentos 
                        (orcamento_id, data_agendamento, hora_inicio, hora_fim, status, 
                         previsao_tempo, temperatura, previsao_chuva, cliente_agendou, usuario_id) 
                        VALUES 
                        (:orcamento_id, :data_agendamento, :hora_inicio, :hora_fim, 'agendado', 
                         :previsao_tempo, :temperatura, :previsao_chuva, :cliente_agendou, :usuario_id)");
    $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
    $stmt->bindParam(':data_agendamento', $data_agendamento);
    $stmt->bindParam(':hora_inicio', $hora_inicio);
    $stmt->bindParam(':hora_fim', $hora_fim);
    $stmt->bindParam(':previsao_tempo', $previsao_tempo);
    $stmt->bindParam(':temperatura', $temperatura);
    $stmt->bindParam(':previsao_chuva', $previsao_chuva, PDO::PARAM_BOOL);
    $stmt->bindParam(':cliente_agendou', $cliente_agendou, PDO::PARAM_BOOL);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $agendamento_id = $db->lastInsertId();
    
    // Inserir os instaladores selecionados
    foreach ($instaladores as $instalador_id) {
        if (!empty($instalador_id)) {
            $stmt = $db->prepare("INSERT INTO agendamento_instaladores 
                                 (agendamento_id, instalador_id) 
                                 VALUES (:agendamento_id, :instalador_id)");
            $stmt->bindParam(':agendamento_id', $agendamento_id, PDO::PARAM_INT);
            $stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Verificar se o instalador tem auxiliar associado
            $stmt_auxiliar = $db->prepare("SELECT auxiliar_id FROM instaladores WHERE id = :instalador_id AND auxiliar_id IS NOT NULL");
            $stmt_auxiliar->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
            $stmt_auxiliar->execute();
            
            if ($stmt_auxiliar->rowCount() > 0) {
                $auxiliar_info = $stmt_auxiliar->fetch(PDO::FETCH_ASSOC);
                if (!empty($auxiliar_info['auxiliar_id'])) {
                    $stmt = $db->prepare("INSERT INTO agendamento_auxiliares 
                                         (agendamento_id, instalador_id, auxiliar_id) 
                                         VALUES (:agendamento_id, :instalador_id, :auxiliar_id)");
                    $stmt->bindParam(':agendamento_id', $agendamento_id, PDO::PARAM_INT);
                    $stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
                    $stmt->bindParam(':auxiliar_id', $auxiliar_info['auxiliar_id'], PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }
    }
    
    // Atualizar status de execução do orçamento
    $stmt = $db->prepare("UPDATE orcamentos SET status_execucao = 'agendado' WHERE id = :id");
    $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Confirmar transação
    $db->commit();
    
    // Redirecionar para a página de sucesso
    $redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'orcamentos.php';
    $data_formatada = date('d/m/Y', strtotime($data_agendamento));
    
    // Verificar se a URL já tem parâmetros
    $separador = (strpos($redirect_url, '?') !== false) ? '&' : '?';
    
    header("Location: {$redirect_url}{$separador}agendado=true&data={$data_formatada}&hora={$hora_inicio}");
    exit;
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    $db->rollback();
    
    // Redirecionar com mensagem de erro
    $redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'orcamentos.php';
    
    // Verificar se a URL já tem parâmetros
    $separador = (strpos($redirect_url, '?') !== false) ? '&' : '?';
    
    header("Location: {$redirect_url}{$separador}erro=" . urlencode('Erro ao salvar agendamento: ' . $e->getMessage()));
    exit;
}
