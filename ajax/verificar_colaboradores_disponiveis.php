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
$hora = isset($_GET['hora_inicio']) ? $_GET['hora_inicio'] : (isset($_GET['hora']) ? $_GET['hora'] : '');
$tempo_previsto = isset($_GET['tempo_previsto']) ? (int)$_GET['tempo_previsto'] : 60;
$unidade_tempo = isset($_GET['unidade_tempo']) ? $_GET['unidade_tempo'] : 'minutos';

// Validar dados recebidos
if (empty($data) || empty($hora)) {
    $resposta['mensagem'] = 'Data e horário são obrigatórios';
    echo json_encode($resposta);
    exit;
}

// Obter configurações de horário de funcionamento
$stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_inicio', 'horario_fim', 'dias_funcionamento', 'horario_entrada_disponivel', 'horario_almoco_inicio', 'horario_almoco_fim', 'tempo_previsto_visita')");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Valores padrão caso não existam configurações
$horario_inicio = isset($config['horario_inicio']) ? $config['horario_inicio'] : '07:00';
$horario_fim = isset($config['horario_fim']) ? $config['horario_fim'] : '17:00';
$horario_entrada_disponivel = isset($config['horario_entrada_disponivel']) ? $config['horario_entrada_disponivel'] : '07:30';
$horario_almoco_inicio = isset($config['horario_almoco_inicio']) ? $config['horario_almoco_inicio'] : '12:00';
$horario_almoco_fim = isset($config['horario_almoco_fim']) ? $config['horario_almoco_fim'] : '13:00';
$tempo_previsto_visita = isset($config['tempo_previsto_visita']) ? $config['tempo_previsto_visita'] : '60';
$dias_funcionamento = isset($config['dias_funcionamento']) ? explode(',', $config['dias_funcionamento']) : [1, 2, 3, 4, 5]; // Padrão: Segunda a Sexta

// Usar tempo previsto configurado para visitas se não for especificado
if (isset($_GET['tipo']) && $_GET['tipo'] === 'orcamentista' && empty($_GET['tempo_previsto'])) {
    $tempo_previsto = (int)$tempo_previsto_visita;
    $unidade_tempo = 'minutos';
}

try {
    // Calcular data e hora de início
    $data_inicio = $data . ' ' . $hora . ':00';
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
    
    // Verificar período de preparação na entrada
    $hora_entrada_disponivel = new DateTime($data . ' ' . $horario_entrada_disponivel);
    if ($data_hora_inicio < $hora_entrada_disponivel && $data_hora_inicio >= $hora_inicio_expediente) {
        $resposta['mensagem'] = 'O horário selecionado está no período de preparação inicial (' . 
                                $horario_inicio . ' - ' . $horario_entrada_disponivel . '). Por favor, escolha um horário a partir de ' . $horario_entrada_disponivel . '.';
        echo json_encode($resposta);
        exit;
    }
    
    // Verificar período de almoço
    $hora_almoco_inicio = new DateTime($data . ' ' . $horario_almoco_inicio);
    $hora_almoco_fim = new DateTime($data . ' ' . $horario_almoco_fim);
    
    // Se o período solicitado começar durante o horário de almoço
    if ($data_hora_inicio >= $hora_almoco_inicio && $data_hora_inicio < $hora_almoco_fim) {
        $resposta['mensagem'] = 'O horário selecionado interfere com o período de almoço (' . 
                                $horario_almoco_inicio . ' - ' . $horario_almoco_fim . '). Por favor, escolha um horário antes ou depois deste período.';
        echo json_encode($resposta);
        exit;
    }
    
    // Ajustar a duração do serviço considerando o horário de almoço
    // Se o serviço começar antes do almoço e terminar durante ou depois do almoço
    if ($data_hora_inicio < $hora_almoco_inicio && $data_hora_fim > $hora_almoco_inicio) {
        // Calcular quanto tempo do serviço aconteceria durante o almoço
        $hora_fim_original = clone $data_hora_fim;
        
        // Se o serviço terminaria depois do final do almoço
        if ($hora_fim_original > $hora_almoco_fim) {
            // Adicionar todo o período de almoço ao tempo de término
            $diferenca_almoco = $hora_almoco_inicio->diff($hora_almoco_fim);
            $minutos_almoco = ($diferenca_almoco->h * 60) + $diferenca_almoco->i;
            $data_hora_fim->add(new DateInterval('PT' . $minutos_almoco . 'M'));
        } else {
            // O serviço terminaria durante o almoço
            // Calcular quanto tempo seria durante o almoço
            $tempo_durante_almoco = $hora_almoco_inicio->diff($hora_fim_original);
            $minutos_durante_almoco = ($tempo_durante_almoco->h * 60) + $tempo_durante_almoco->i;
            
            // Ajustar fim para logo após o almoço + o tempo que seria durante o almoço
            $data_hora_fim = clone $hora_almoco_fim;
            $data_hora_fim->add(new DateInterval('PT' . $minutos_durante_almoco . 'M'));
        }
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
                         WHERE (status = 'instalacao_agendada' OR status = 'orcamento_agendado' OR status = 'pendente') AND 
                         ((
                            -- Verificar usando campos data_inicio/data_fim (formato timestamp)
                            (data_inicio IS NOT NULL AND data_fim IS NOT NULL) AND
                            ((data_inicio < :data_fim AND data_fim > :data_inicio))
                         ) OR (
                            -- Verificar usando campos data_agendamento/hora_inicio/hora_fim (formato separado)
                            data_agendamento = :data_agendamento AND
                            ((hora_inicio < :hora_fim AND hora_fim > :hora_inicio))
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
                                   (hora_inicio < :hora_fim AND hora_fim > :hora_inicio)
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
                                  (hora_inicio < :hora_fim AND hora_fim > :hora_inicio)
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
    
    // Definir o tipo de colaborador (instalador ou orçamentista)
    $tipo_colaborador = 'instalador'; // Valor padrão
    if (isset($_GET['tipo']) && $_GET['tipo'] === 'orcamentista') {
        $tipo_colaborador = 'orcamentista';
    }

    // Buscar colaboradores do tipo especificado
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
