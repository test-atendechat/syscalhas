<?php
require_once('../includes/config.php');
require_once('../includes/db.php');
require_once('../includes/functions.php');

// Garantir acesso às variáveis globais de conexão
global $db, $pdo;

// Se por algum motivo ainda não estiverem definidas, tentar inicializá-las
if (!isset($pdo)) {
    // Tentar criar uma nova conexão como último recurso
    try {
        if (DB_TYPE == 'mysql') {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT;
        } else if (DB_TYPE == 'pgsql') {
            $dsn = "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=" . DB_PORT;
        } else {
            throw new Exception("Tipo de banco de dados não suportado");
        }

        // Opções PDO
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        // Criar conexão
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de conexão com o banco de dados']);
        exit;
    }
}

// Inicializar resposta JSON
header('Content-Type: application/json');
$resposta = ['status' => 'erro', 'mensagem' => '', 'colaboradores' => []];

// Verificar se os dados foram enviados
$data = isset($_GET['data']) ? $_GET['data'] : '';
$hora = isset($_GET['hora']) ? $_GET['hora'] : '';
$tempo_previsto = isset($_GET['tempo_previsto']) ? (int)$_GET['tempo_previsto'] : 60;
$unidade_tempo = isset($_GET['unidade_tempo']) ? $_GET['unidade_tempo'] : 'minutos';

// Validar dados recebidos
if (empty($data) || empty($hora)) {
    $resposta['mensagem'] = 'Data e horário são obrigatórios';
    echo json_encode($resposta);
    exit;
}

// Obter configurações de horário de funcionamento e indisponibilidade automática
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_inicio', 'horario_fim', 'dias_funcionamento', 'aplicar_indisponibilidade_automatica', 'tempo_indisponivel_entrada', 'horario_inicio_almoco', 'horario_fim_almoco')");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Valores padrão caso não existam configurações
$horario_inicio = isset($config['horario_inicio']) ? $config['horario_inicio'] : '07:00';
$horario_fim = isset($config['horario_fim']) ? $config['horario_fim'] : '17:00';
$dias_funcionamento = isset($config['dias_funcionamento']) ? explode(',', $config['dias_funcionamento']) : [1, 2, 3, 4, 5]; // Padrão: Segunda a Sexta

// Configurações de indisponibilidade automática
$aplicar_indisponibilidade = isset($config['aplicar_indisponibilidade_automatica']) ? ($config['aplicar_indisponibilidade_automatica'] == 'sim') : false;
$tempo_indisponivel_entrada = isset($config['tempo_indisponivel_entrada']) ? (int)$config['tempo_indisponivel_entrada'] : 30;
$horario_inicio_almoco = isset($config['horario_inicio_almoco']) ? $config['horario_inicio_almoco'] : '11:00';
$horario_fim_almoco = isset($config['horario_fim_almoco']) ? $config['horario_fim_almoco'] : '12:00';

try {
    // Calcular data e hora de início
    // Verificar se o formato da hora já inclui segundos
    if (substr_count($hora, ':') >= 2) {
        $data_inicio = $data . ' ' . $hora;
    } else if (substr_count($hora, ':') == 1) {
        $data_inicio = $data . ' ' . $hora . ':00';
    } else {
        $data_inicio = $data . ' ' . $hora . ':00:00';
    }
    
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
    $dia_semana = date('N', strtotime($data)); // Retorna 1 (segunda) a 7 (domingo)
    
    if (!in_array($dia_semana, $dias_funcionamento)) {
        $resposta['mensagem'] = 'O dia selecionado não é um dia de funcionamento.';
        echo json_encode($resposta);
        exit;
    }
    
    // Verificar se está dentro do horário de funcionamento
    $hora_inicio_expediente = new DateTime($data . ' ' . $horario_inicio);
    $hora_fim_expediente = new DateTime($data . ' ' . $horario_fim);
    
    // Se a hora de início for anterior ao expediente ou se a hora do fim for posterior ao expediente
    if ($data_hora_inicio < $hora_inicio_expediente || $data_hora_fim > $hora_fim_expediente) {
        $resposta['mensagem'] = 'O horário selecionado está fora do horário de funcionamento (' . 
                                $horario_inicio . ' - ' . $horario_fim . ').';
        echo json_encode($resposta);
        exit;
    }
    
    // Verificar se a tabela agendamentos tem registros
    $resultado = $pdo->query("SELECT COUNT(*) FROM agendamentos");
    $tem_agendamentos = ($resultado->fetchColumn() > 0);
    
    $colaboradores_ocupados = [];
    
    // 1. Verificar colaboradores com agendamentos neste horário
    if ($tem_agendamentos) {
        // Verificar colunas na tabela
        $stmt_colunas = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'agendamentos'");
        $colunas = $stmt_colunas->fetchAll(PDO::FETCH_COLUMN);
        
        // Consulta combinada usando ambos os formatos de data/hora da tabela agendamentos
        // Uma vez que tanto os campos data_inicio/data_fim quanto data_agendamento/hora_inicio/hora_fim existem na tabela
        $data_apenas = $data_hora_inicio->format('Y-m-d');
        $hora_inicio_apenas = $data_hora_inicio->format('H:i:s');
        $hora_fim_apenas = $data_hora_fim->format('H:i:s');
        $data_inicio_completa = $data_hora_inicio->format('Y-m-d H:i:s');
        $data_fim_completa = $data_hora_fim->format('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("SELECT DISTINCT instalador_id FROM agendamentos 
                         WHERE status = 'agendado' AND 
                         ((
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
        
        // Vincular os parâmetros para ambos os formatos
        $stmt->bindParam(':data_inicio', $data_inicio_completa);
        $stmt->bindParam(':data_fim', $data_fim_completa);
        $stmt->bindParam(':data_agendamento', $data_apenas);
        $stmt->bindParam(':hora_inicio', $hora_inicio_apenas);
        $stmt->bindParam(':hora_fim', $hora_fim_apenas);
        $stmt->execute();
        
        $colaboradores_ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // 2. Verificar colaboradores com indisponibilidades registradas na tabela colaborador_agenda
    $data_apenas = $data_hora_inicio->format('Y-m-d');

    // Consultar colaboradores indisponíveis em colaborador_agenda
    $stmt_indisponibilidade = $pdo->prepare("SELECT DISTINCT colaborador_id FROM colaborador_agenda 
                               WHERE data_disponibilidade = :data_disponibilidade 
                               AND disponivel = FALSE 
                               AND (
                                   (hora_inicio <= :hora_inicio AND hora_fim >= :hora_inicio) 
                                   OR (hora_inicio <= :hora_fim AND hora_fim >= :hora_fim) 
                                   OR (hora_inicio >= :hora_inicio AND hora_fim <= :hora_fim)
                               )");
    // Nota: colaborador_id refere-se aos IDs da tabela colaboradores, não instaladores
    $stmt_indisponibilidade->bindParam(':data_disponibilidade', $data_apenas);
    $hora_inicio_str = $data_hora_inicio->format('H:i:s');
    $hora_fim_str = $data_hora_fim->format('H:i:s');
    $stmt_indisponibilidade->bindParam(':hora_inicio', $hora_inicio_str);
    $stmt_indisponibilidade->bindParam(':hora_fim', $hora_fim_str);
    $stmt_indisponibilidade->execute();
    
    // Adicionar colaboradores indisponíveis ao array de ocupados
    $indisponiveis = $stmt_indisponibilidade->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($indisponiveis)) {
        $colaboradores_ocupados = array_merge($colaboradores_ocupados, $indisponiveis);
        
        // Remover duplicatas
        $colaboradores_ocupados = array_unique($colaboradores_ocupados);
    }
    
    // 3. Verificar indisponibilidades recorrentes (baseadas no dia da semana)
    $dia_semana = (int)$data_hora_inicio->format('w'); // 0 (domingo) até 6 (sábado)
    $stmt_recorrente = $pdo->prepare("SELECT DISTINCT colaborador_id FROM colaborador_agenda 
                              WHERE recorrente = TRUE 
                              AND dia_semana = :dia_semana 
                              AND disponivel = FALSE 
                              AND (
                                  (hora_inicio <= :hora_inicio AND hora_fim >= :hora_inicio) 
                                  OR (hora_inicio <= :hora_fim AND hora_fim >= :hora_fim) 
                                  OR (hora_inicio >= :hora_inicio AND hora_fim <= :hora_fim)
                              )");
    $stmt_recorrente->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
    $hora_inicio_recorrente = $data_hora_inicio->format('H:i:s');
    $hora_fim_recorrente = $data_hora_fim->format('H:i:s');
    $stmt_recorrente->bindParam(':hora_inicio', $hora_inicio_recorrente);
    $stmt_recorrente->bindParam(':hora_fim', $hora_fim_recorrente);
    $stmt_recorrente->execute();
    
    // Adicionar colaboradores com indisponibilidade recorrente ao array de ocupados
    $indisponiveis_recorrentes = $stmt_recorrente->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($indisponiveis_recorrentes)) {
        $colaboradores_ocupados = array_merge($colaboradores_ocupados, $indisponiveis_recorrentes);
        
        // Remover duplicatas
        $colaboradores_ocupados = array_unique($colaboradores_ocupados);
    }
    
    // 4. Verificar indisponibilidades automáticas (horário de almoço e tempo após chegada)
    // Forçar aplicar_indisponibilidade para true, pois é importante bloquear horário de almoço
    $aplicar_indisponibilidade = true;
    if ($aplicar_indisponibilidade) {
        // Verificar conflito com horário de almoço
        $inicio_almoco = new DateTime($data . ' ' . $horario_inicio_almoco);
        $fim_almoco = new DateTime($data . ' ' . $horario_fim_almoco);
        
        $conflito_almoco = (
            ($data_hora_inicio <= $inicio_almoco && $data_hora_fim > $inicio_almoco) || // Serviço começa antes do almoço e termina durante
            ($data_hora_inicio >= $inicio_almoco && $data_hora_inicio < $fim_almoco) || // Serviço começa durante o almoço
            ($data_hora_inicio < $inicio_almoco && $data_hora_fim > $fim_almoco)        // Serviço engloba todo o almoço
        );
        
        // Verificar conflito com tempo indisponível após chegada
        $hora_chegada = new DateTime($data . ' ' . $horario_inicio); // Horário de abertura da empresa
        $hora_disponivel = clone $hora_chegada;
        $hora_disponivel->add(new DateInterval('PT' . $tempo_indisponivel_entrada . 'M'));
        
        $conflito_entrada = ($data_hora_inicio < $hora_disponivel);
        
        // Se houver conflito com almoço ou entrada, não há colaboradores disponíveis
        if ($conflito_almoco) {
            $resposta['status'] = 'erro';
            $resposta['mensagem'] = 'Este horário coincide com o período de almoço (' . $horario_inicio_almoco . ' - ' . $horario_fim_almoco . '). Caso o serviço seja longo, considere a duração completa depois do período de almoço.';
            $resposta['colaboradores'] = [];
            echo json_encode($resposta);
            exit;
        }
        
        if ($conflito_entrada) {
            $hora_disponivel_formatada = $hora_disponivel->format('H:i');
            $resposta['status'] = 'erro';
            $resposta['mensagem'] = 'Este horário coincide com o período indisponível na abertura da empresa. Disponível a partir de ' . $hora_disponivel_formatada . '.';
            $resposta['colaboradores'] = [];
            echo json_encode($resposta);
            exit;
        }

        // Calcular tempo restante de serviço após o almoço (se o serviço comecer antes e terminar depois do almoço)
        if ($data_hora_inicio < $inicio_almoco && $data_hora_fim > $fim_almoco) {
            // Calcular quanto tempo do serviço será antes do almoço
            $tempo_antes_almoco = $inicio_almoco->getTimestamp() - $data_hora_inicio->getTimestamp();
            // Calcular o tempo restante que deve ser adicionado após o almoço
            $tempo_apos_almoco = $data_hora_fim->getTimestamp() - $fim_almoco->getTimestamp();
            
            // Calcular novo horário de fim considerando a pausa para almoço
            $novo_data_hora_fim = clone $fim_almoco;
            $novo_data_hora_fim->add(new DateInterval('PT' . (int)($tempo_apos_almoco / 60) . 'M'));
            
            $resposta['mensagem'] = 'Atenção: O serviço com duração de ' . $duracao_minutos . ' minutos será interrompido pelo almoço e irá terminar às ' . $novo_data_hora_fim->format('H:i') . '.';
        }
    }
    
    // Verificar o tipo de orçamento para mostrar apenas colaboradores adequados
    // Se temos um parâmetro 'orcamento_id', verificar status do orçamento
    $orcamento_id = isset($_GET['orcamento_id']) ? (int)$_GET['orcamento_id'] : 0;
    $tipo_colaborador = 'orcamentista'; // Padrão para visitas técnicas
    
    if ($orcamento_id > 0) {
        $stmt_orc = $pdo->prepare("SELECT status FROM orcamentos WHERE id = :id");
        $stmt_orc->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
        $stmt_orc->execute();
        $status_orcamento = $stmt_orc->fetchColumn();
        
        // Se o orçamento está aprovado, mostrar apenas instaladores
        if ($status_orcamento == 'aprovado') {
            $tipo_colaborador = 'instalador';
        }
    }
    
    // Buscar colaboradores do tipo apropriado
    $sql = "SELECT id, nome, tipo FROM colaboradores WHERE tipo = :tipo_colaborador AND status = 'ativo'";
    
    // Se há colaboradores ocupados, excluí-los da busca
    if (!empty($colaboradores_ocupados)) {
        $sql .= " AND id NOT IN (" . implode(',', $colaboradores_ocupados) . ")";
    }
    
    // Adicionar a cláusula ORDER BY depois de todas as condições
    $sql .= " ORDER BY nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':tipo_colaborador', $tipo_colaborador, PDO::PARAM_STR);
    $stmt->execute();
    $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retornar resultado
    $resposta = [
        'status' => 'sucesso',
        'colaboradores' => $colaboradores
    ];
    
} catch (Exception $e) {
    $resposta['mensagem'] = 'Erro ao verificar disponibilidade: ' . $e->getMessage();
}

echo json_encode($resposta);
