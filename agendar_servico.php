<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Inicializar variáveis
$mensagem = '';
$tipo = 'danger';
$orcamento_id = intval($_POST['orcamento_id'] ?? 0);
$codigo = $_POST['codigo'] ?? '';
$data_servico = $_POST['data_servico'] ?? '';
$hora_inicio = $_POST['hora_inicio'] ?? '';
$colaborador_id = intval($_POST['colaborador_id'] ?? 0);
$tempo_previsto = intval($_POST['tempo_previsto'] ?? 60);
$unidade_tempo = $_POST['unidade_tempo'] ?? 'minutos';

// Validar dados recebidos
if (empty($orcamento_id) || empty($codigo) || empty($data_servico) || empty($hora_inicio)) {
    $mensagem = 'Dados incompletos para agendamento. Por favor, preencha todos os campos obrigatórios.';
} else {
    try {
        // Verificar se o orçamento existe e está aprovado
        $stmt = $db->prepare("SELECT * FROM orcamentos WHERE id = :id AND codigo_acesso = :codigo AND status = 'aprovado'");
        $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            $mensagem = 'Orçamento não encontrado ou não está aprovado.';
        } else {
            $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calcular hora de término com base no tempo previsto
            $data_inicio = $data_servico . ' ' . $hora_inicio . ':00';
            $data_hora_inicio = new DateTime($data_inicio);
            
            // Converter tempo previsto para minutos
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
            $db->beginTransaction();
            
            // Verificar se o colaborador está disponível no horário (se especificado)
            if ($colaborador_id > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM agendamentos 
                                     WHERE colaborador_id = :colaborador_id 
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
                    // Se o colaborador selecionado não está disponível, lançar erro
                    throw new Exception('O colaborador selecionado não está disponível neste horário. Por favor, selecione outro colaborador ou horário.');
                }
            } else {
                // Se não foi selecionado um colaborador, buscar qualquer um disponível
                $stmt = $db->prepare("SELECT id FROM colaboradores 
                                     WHERE tipo = 'instalador' 
                                     AND id NOT IN (
                                         SELECT DISTINCT colaborador_id FROM agendamentos 
                                         WHERE status = 'agendado' 
                                         AND ((data_inicio <= :data_inicio AND data_fim >= :data_inicio) 
                                         OR (data_inicio <= :data_fim AND data_fim >= :data_fim) 
                                         OR (data_inicio >= :data_inicio AND data_fim <= :data_fim))
                                     ) 
                                     LIMIT 1");
                $data_inicio_str = $data_hora_inicio->format('Y-m-d H:i:s');
                $data_fim_str = $data_hora_fim->format('Y-m-d H:i:s');
                $stmt->bindParam(':data_inicio', $data_inicio_str);
                $stmt->bindParam(':data_fim', $data_fim_str);
                $stmt->execute();
                
                if ($colaborador_disp = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $colaborador_id = $colaborador_disp['id'];
                } else {
                    throw new Exception('Não há colaboradores disponíveis nesse horário. Por favor, selecione outro horário ou data.');
                }
            }
            
            // Gerar código de confirmação
            $codigo_confirmacao = md5(uniqid(rand(), true));
            
            // Inserir agendamento
            $stmt = $db->prepare("INSERT INTO agendamentos (
                                orcamento_id, colaborador_id, data_inicio, data_fim, 
                                status, codigo_confirmacao, cliente_confirmou, data_cadastro)
                                VALUES (
                                :orcamento_id, :colaborador_id, :data_inicio, :data_fim, 
                                'agendado', :codigo_confirmacao, FALSE, NOW())");
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
            
            $data_inicio_final = $data_hora_inicio->format('Y-m-d H:i:s');
            $data_fim_final = $data_hora_fim->format('Y-m-d H:i:s');
            $stmt->bindParam(':data_inicio', $data_inicio_final);
            $stmt->bindParam(':data_fim', $data_fim_final);
            $stmt->bindParam(':codigo_confirmacao', $codigo_confirmacao);
            $stmt->execute();
            
            // Atualizar status de execução do orçamento
            $stmt = $db->prepare("UPDATE orcamentos SET status_execucao = 'agendado' WHERE id = :id");
            $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Commit da transação
            $db->commit();
            
            $mensagem = 'Agendamento realizado com sucesso! Em breve entraremos em contato para confirmar.';
            $tipo = 'success';
        }
    } catch (Exception $e) {
        // Rollback em caso de erro
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $mensagem = 'Erro ao processar agendamento: ' . $e->getMessage();
    }
}

// Redirecionar de volta para a página de visualização com a mensagem
header("Location: orcamento_visualizar.php?codigo={$codigo}&mensagem_agendamento={$mensagem}&tipo={$tipo}");
exit;
