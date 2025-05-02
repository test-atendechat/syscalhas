<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

// Garantir acesso às variáveis globais da conexão com o banco de dados
global $db, $pdo;

// Verificar se é uma requisição GET ou POST
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Se for GET, vamos redirecionar para a página de agendamento
    $orcamento_id = isset($_GET['orcamento_id']) ? intval($_GET['orcamento_id']) : 0;
    if ($orcamento_id > 0) {
        header("Location: agendamento.php?orcamento_id=$orcamento_id");
        exit;
    } else {
        header('Location: index.php');
        exit;
    }
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

// Obter configurações de horário de funcionamento
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_inicio', 'horario_fim', 'dias_funcionamento')");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Valores padrão caso não existam configurações
$horario_inicio = isset($config['horario_inicio']) ? $config['horario_inicio'] : '07:00';
$horario_fim = isset($config['horario_fim']) ? $config['horario_fim'] : '17:00';
$dias_funcionamento = isset($config['dias_funcionamento']) ? explode(',', $config['dias_funcionamento']) : [1, 2, 3, 4, 5]; // Padrão: Segunda a Sexta

// Validar dados recebidos
if (empty($orcamento_id) || empty($codigo) || empty($data_servico) || empty($hora_inicio)) {
    $mensagem = 'Dados incompletos para agendamento. Por favor, preencha todos os campos obrigatórios.';
} else {
    try {
        // Verificar se o orçamento existe e está aprovado
        $stmt = $pdo->prepare("SELECT * FROM orcamentos WHERE id = :id AND codigo_acesso = :codigo AND status = 'aprovado'");
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
            
            // Verificar se a data é um dia de funcionamento
            $dia_semana = date('N', strtotime($data_servico)); // Retorna 1 (segunda) a 7 (domingo)
            
            if (!in_array($dia_semana, $dias_funcionamento)) {
                throw new Exception('O dia selecionado não é um dia de funcionamento.');
            }
            
            // Verificar se está dentro do horário de funcionamento
            $hora_inicio_expediente = new DateTime($data_servico . ' ' . $horario_inicio);
            $hora_fim_expediente = new DateTime($data_servico . ' ' . $horario_fim);
            
            // Se a hora de início for anterior ao expediente ou se a hora do fim for posterior ao expediente
            if ($data_hora_inicio < $hora_inicio_expediente || $data_hora_fim > $hora_fim_expediente) {
                throw new Exception('O horário selecionado está fora do horário de funcionamento (' . $horario_inicio . ' - ' . $horario_fim . ').');
            }
            
            // Buscar as configurações de horário de almoço do banco
            $stmt_config = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_almoco_inicio', 'horario_almoco_fim')");
            $config_almoco = $stmt_config->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Usar os valores do banco ou padrões se não encontrados
            $horario_almoco_inicio = isset($config_almoco['horario_almoco_inicio']) ? $config_almoco['horario_almoco_inicio'] : '11:00';
            $horario_almoco_fim = isset($config_almoco['horario_almoco_fim']) ? $config_almoco['horario_almoco_fim'] : '13:00';
            
            // Verificar período de almoço
            $hora_almoco_inicio = new DateTime($data_servico . ' ' . $horario_almoco_inicio);
            $hora_almoco_fim = new DateTime($data_servico . ' ' . $horario_almoco_fim);
            
            // Se o período solicitado começar durante o horário de almoço
            if ($data_hora_inicio >= $hora_almoco_inicio && $data_hora_inicio < $hora_almoco_fim) {
                throw new Exception('O horário selecionado interfere com o período de almoço (' . 
                                $horario_almoco_inicio . ' - ' . $horario_almoco_fim . '). Por favor, escolha um horário antes ou depois deste período.');
            }
            
            // Verificar se o serviço atravessa o horário de almoço
            // A condição para atravessar o almoço é começar antes do almoço e terminar depois do almoço
            $comeca_antes_almoco = $data_hora_inicio < $hora_almoco_inicio;
            $termina_depois_almoco = $data_hora_fim > $hora_almoco_fim;
            
            // Um serviço atravessa o horário de almoço se começar antes do almoço e terminar depois do almoço
            $atravessa_almoco = $comeca_antes_almoco && $termina_depois_almoco;
            
            // Ajustar a duração do serviço considerando o horário de almoço
            if ($atravessa_almoco) {
                // Calcular a diferença em minutos entre o início e fim do almoço
                $diferenca_almoco = $hora_almoco_inicio->diff($hora_almoco_fim);
                $minutos_almoco = ($diferenca_almoco->h * 60) + $diferenca_almoco->i;
                
                // Adicionar o tempo de almoço ao tempo de término
                $data_hora_fim->add(new DateInterval('PT' . $minutos_almoco . 'M'));
            } else if ($data_hora_inicio < $hora_almoco_inicio && $data_hora_fim > $hora_almoco_inicio && $data_hora_fim <= $hora_almoco_fim) {
                // O serviço termina durante o almoço - ajustar para terminar após o almoço
                // Calcular quanto tempo seria durante o almoço
                $tempo_durante_almoco = $hora_almoco_inicio->diff($data_hora_fim);
                $minutos_durante_almoco = ($tempo_durante_almoco->h * 60) + $tempo_durante_almoco->i;
                
                // Ajustar fim para logo após o almoço + o tempo que seria durante o almoço
                $data_hora_fim = clone $hora_almoco_fim;
                $data_hora_fim->add(new DateInterval('PT' . $minutos_durante_almoco . 'M'));
            }
            
            // Iniciar transação
            $pdo->beginTransaction();
            
            // Verificar se o colaborador selecionado (instalador) existe na tabela instaladores
            if ($colaborador_id > 0) {
                // Verificar se o colaborador instalador existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM colaboradores WHERE id = :colaborador_id AND tipo = 'instalador' AND status = 'ativo'");
                $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->fetchColumn() == 0) {
                    // Se o instalador não existir ou não estiver ativo, lançar erro
                    throw new Exception('O instalador selecionado não existe ou está inativo. Por favor, selecione outro instalador.');
                }
                
                // Agora verificar se o instalador está disponível no horário
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM agendamentos 
                                     WHERE instalador_id = :colaborador_id 
                                     AND status = 'instalacao_agendada' 
                                     AND ((
                                        -- Verificar usando campos data_inicio/data_fim (formato timestamp)
                                        (data_inicio IS NOT NULL AND data_fim IS NOT NULL) AND
                                        ((data_inicio <= :data_inicio AND data_fim >= :data_inicio) 
                                        OR (data_inicio <= :data_fim AND data_fim >= :data_fim) 
                                        OR (data_inicio >= :data_inicio AND data_fim <= :data_fim))
                                     ) OR (
                                        -- Verificar usando campos data_agendamento/hora_inicio/hora_fim (formato separado)
                                        data_agendamento = :data_agendamento AND
                                        ((hora_inicio <= :hora_inicio AND hora_fim >= :hora_inicio) 
                                        OR (hora_inicio <= :hora_fim AND hora_fim >= :hora_fim) 
                                        OR (hora_inicio >= :hora_inicio AND hora_fim <= :hora_fim))
                                     ))");
                $stmt->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
                
                // Formatar datas e horas para ambos os formatos
                $data_inicio_completa = $data_hora_inicio->format('Y-m-d H:i:s');
                $data_fim_completa = $data_hora_fim->format('Y-m-d H:i:s');
                $data_apenas = $data_hora_inicio->format('Y-m-d');
                $hora_inicio_apenas = $data_hora_inicio->format('H:i:s');
                $hora_fim_apenas = $data_hora_fim->format('H:i:s');
                
                // Vincular os parâmetros para ambos os formatos
                $stmt->bindParam(':data_inicio', $data_inicio_completa);
                $stmt->bindParam(':data_fim', $data_fim_completa);
                $stmt->bindParam(':data_agendamento', $data_apenas);
                $stmt->bindParam(':hora_inicio', $hora_inicio_apenas);
                $stmt->bindParam(':hora_fim', $hora_fim_apenas);
                $stmt->execute();
                
                if ($stmt->fetchColumn() > 0) {
                    // Se o colaborador selecionado não está disponível, lançar erro
                    throw new Exception('O colaborador selecionado não está disponível neste horário. Por favor, selecione outro colaborador ou horário.');
                }
            } else {
                // Se não foi selecionado um colaborador, buscar qualquer um disponível
                $stmt = $pdo->prepare("SELECT id FROM colaboradores 
                                     WHERE tipo = 'instalador' AND status = 'ativo' 
                                     AND id NOT IN (
                                         SELECT DISTINCT instalador_id FROM agendamentos 
                                         WHERE status = 'instalacao_agendada' 
                                         AND ((
                                            -- Verificar usando campos data_inicio/data_fim (formato timestamp)
                                            (data_inicio IS NOT NULL AND data_fim IS NOT NULL) AND
                                            ((data_inicio <= :data_inicio AND data_fim >= :data_inicio) 
                                            OR (data_inicio <= :data_fim AND data_fim >= :data_fim) 
                                            OR (data_inicio >= :data_inicio AND data_fim <= :data_fim))
                                         ) OR (
                                            -- Verificar usando campos data_agendamento/hora_inicio/hora_fim (formato separado)
                                            data_agendamento = :data_agendamento AND
                                            ((hora_inicio <= :hora_inicio AND hora_fim >= :hora_inicio) 
                                            OR (hora_inicio <= :hora_fim AND hora_fim >= :hora_fim) 
                                            OR (hora_inicio >= :hora_inicio AND hora_fim <= :hora_fim))
                                         ))
                                     ) 
                                     LIMIT 1");
                
                // Formatar datas e horas para ambos os formatos
                $data_inicio_completa = $data_hora_inicio->format('Y-m-d H:i:s');
                $data_fim_completa = $data_hora_fim->format('Y-m-d H:i:s');
                $data_apenas = $data_hora_inicio->format('Y-m-d');
                $hora_inicio_apenas = $data_hora_inicio->format('H:i:s');
                $hora_fim_apenas = $data_hora_fim->format('H:i:s');
                
                // Vincular os parâmetros para ambos os formatos
                $stmt->bindParam(':data_inicio', $data_inicio_completa);
                $stmt->bindParam(':data_fim', $data_fim_completa);
                $stmt->bindParam(':data_agendamento', $data_apenas);
                $stmt->bindParam(':hora_inicio', $hora_inicio_apenas);
                $stmt->bindParam(':hora_fim', $hora_fim_apenas);
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
            $stmt = $pdo->prepare("INSERT INTO agendamentos (
                                orcamento_id, instalador_id, data_inicio, data_fim, 
                                status, codigo_confirmacao, cliente_agendou, criado_em, data_agendamento,
                                hora_inicio, hora_fim)
                                VALUES (
                                :orcamento_id, :instalador_id, :data_inicio, :data_fim, 
                                'instalacao_agendada', :codigo_confirmacao, TRUE, NOW(), :data_agendamento,
                                :hora_inicio, :hora_fim)");
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->bindParam(':instalador_id', $colaborador_id, PDO::PARAM_INT);
            
            $data_inicio_final = $data_hora_inicio->format('Y-m-d H:i:s');
            $data_fim_final = $data_hora_fim->format('Y-m-d H:i:s');
            $data_agendamento = $data_hora_inicio->format('Y-m-d'); // Apenas a data sem a hora
            $hora_inicio_apenas = $data_hora_inicio->format('H:i:s'); // Apenas a hora para o formato antigo
            $hora_fim_apenas = $data_hora_fim->format('H:i:s'); // Apenas a hora para o formato antigo
            
            $stmt->bindParam(':data_inicio', $data_inicio_final);
            $stmt->bindParam(':data_fim', $data_fim_final);
            $stmt->bindParam(':data_agendamento', $data_agendamento);
            $stmt->bindParam(':hora_inicio', $hora_inicio_apenas);
            $stmt->bindParam(':hora_fim', $hora_fim_apenas);
            $stmt->bindParam(':codigo_confirmacao', $codigo_confirmacao);
            $stmt->execute();
            
            // Atualizar status de execução do orçamento
            $stmt = $pdo->prepare("UPDATE orcamentos SET status_execucao = 'instalacao_agendada' WHERE id = :id");
            $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Commit da transação
            $pdo->commit();
            
            // Verificar se foi reagendamento ou novo agendamento
            $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM agendamentos WHERE orcamento_id = :orcamento_id AND status != 'instalacao_agendada'");
            $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
            $stmt->execute();
            $agendamentos_previos = $stmt->fetchColumn();
            
            if ($agendamentos_previos > 0) {
                $mensagem = 'Reagendamento realizado com sucesso!';
            } else {
                $mensagem = 'Agendamento realizado com sucesso!';
            }
            $tipo = 'success';
        }
    } catch (Exception $e) {
        // Rollback em caso de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensagem = 'Erro ao processar agendamento: ' . $e->getMessage();
    }
}

// Redirecionar de volta para a página de visualização com a mensagem
// Codificar a mensagem para evitar problemas no header
$mensagem_codificada = urlencode($mensagem);

// Recuperar o código de acesso do orçamento para gerar o link correto
try {
    $stmt = $pdo->prepare("SELECT codigo_acesso FROM orcamentos WHERE id = :id");
    $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $codigo_acesso = $resultado['codigo_acesso'];
    
    header("Location: orcamento_visualizar.php?codigo={$codigo_acesso}&mensagem_agendamento={$mensagem_codificada}&tipo={$tipo}");
} catch (Exception $e) {
    // Em caso de falha, usar o código enviado pelo formulário
    header("Location: orcamento_visualizar.php?codigo={$codigo}&mensagem_agendamento={$mensagem_codificada}&tipo={$tipo}");
}
exit;
