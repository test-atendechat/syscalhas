<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

/**
 * Atualiza as indisponibilidades recorrentes de todos os colaboradores
 * baseado nas configurações globais do sistema (horário de almoço e entrada)
 */
function atualizarIndisponibilidadesColaboradores($pdo, $config) {
    try {
        // Primeiro, configurações de horário
        $horario_inicio = $config['horario_inicio'];
        $horario_fim = $config['horario_fim'];
        $inicio_almoco = $config['horario_inicio_almoco'];
        $fim_almoco = $config['horario_fim_almoco'];
        $tempo_indisponivel_entrada = (int)$config['tempo_indisponivel_entrada'];
        
        // Definir a data atual para usar em todos os registros
        $data_atual = date('Y-m-d');
        
        // Verificar se a tabela existe
        $stmt = $pdo->prepare("SELECT to_regclass('colaborador_agenda')");
        $stmt->execute();
        $existe_tabela = $stmt->fetchColumn();
        
        if (!$existe_tabela) {
            // Se a tabela não existe, não há o que atualizar
            return;
        }
        
        // Calcular horário do fim da indisponibilidade de entrada
        list($entrada_hora, $entrada_min) = explode(':', $horario_inicio);
        $entrada_timestamp = mktime($entrada_hora, $entrada_min);
        $fim_entrada_timestamp = $entrada_timestamp + ($tempo_indisponivel_entrada * 60);
        $horario_fim_entrada_indisponivel = date('H:i:s', $fim_entrada_timestamp);
        
        // 1. Buscar todos os colaboradores ativos
        $stmt_colaboradores = $pdo->query("SELECT id, nome, tipo FROM colaboradores WHERE status = 'ativo'");
        $colaboradores = $stmt_colaboradores->fetchAll(PDO::FETCH_ASSOC);
        
        // 2. Para cada dia da semana, criar/atualizar as indisponibilidades recorrentes
        for ($dia_semana = 0; $dia_semana <= 6; $dia_semana++) {
            // Para cada colaborador
            foreach ($colaboradores as $colaborador) {
                $colaborador_id = $colaborador['id'];
                
                // 2.1 Verificar/Criar indisponibilidade para horário de entrada
                $stmt_entrada = $pdo->prepare("SELECT id FROM colaborador_agenda 
                                            WHERE colaborador_id = :colaborador_id 
                                            AND dia_semana = :dia_semana 
                                            AND recorrente = TRUE 
                                            AND disponivel = FALSE 
                                            AND hora_inicio = :hora_inicio 
                                            AND hora_fim = :hora_fim 
                                            AND descricao LIKE '%Indisponibilidade de entrada%'");
                                            
                $stmt_entrada->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
                $stmt_entrada->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
                $stmt_entrada->bindParam(':hora_inicio', $horario_inicio, PDO::PARAM_STR);
                $stmt_entrada->bindParam(':hora_fim', $horario_fim_entrada_indisponivel, PDO::PARAM_STR);
                $stmt_entrada->execute();
                
                $indisponibilidade_entrada_id = $stmt_entrada->fetchColumn();
                
                if (!$indisponibilidade_entrada_id) {
                    // Criar nova indisponibilidade de entrada
                    // Usar data atual como data_disponibilidade
                    $data_atual = date('Y-m-d');
                    
                    $stmt_criar_entrada = $pdo->prepare("INSERT INTO colaborador_agenda 
                                                    (colaborador_id, dia_semana, data_disponibilidade, hora_inicio, hora_fim, 
                                                    recorrente, disponivel, descricao, tipo) 
                                                    VALUES (:colaborador_id, :dia_semana, :data_disponibilidade, :hora_inicio, :hora_fim, 
                                                    TRUE, FALSE, 'Indisponibilidade de entrada (automático)', 'sistema')");
                                                    
                    $stmt_criar_entrada->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
                    $stmt_criar_entrada->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
                    $stmt_criar_entrada->bindParam(':data_disponibilidade', $data_atual, PDO::PARAM_STR);
                    $stmt_criar_entrada->bindParam(':hora_inicio', $horario_inicio, PDO::PARAM_STR);
                    $stmt_criar_entrada->bindParam(':hora_fim', $horario_fim_entrada_indisponivel, PDO::PARAM_STR);
                    $stmt_criar_entrada->execute();
                } else {
                    // Atualizar indisponibilidade de entrada existente
                    $stmt_atualizar_entrada = $pdo->prepare("UPDATE colaborador_agenda 
                                                        SET hora_inicio = :hora_inicio, 
                                                            hora_fim = :hora_fim 
                                                        WHERE id = :id");
                                                        
                    $stmt_atualizar_entrada->bindParam(':hora_inicio', $horario_inicio, PDO::PARAM_STR);
                    $stmt_atualizar_entrada->bindParam(':hora_fim', $horario_fim_entrada_indisponivel, PDO::PARAM_STR);
                    $stmt_atualizar_entrada->bindParam(':id', $indisponibilidade_entrada_id, PDO::PARAM_INT);
                    $stmt_atualizar_entrada->execute();
                }
                
                // 2.2 Verificar/Criar indisponibilidade para horário de almoço
                $stmt_almoco = $pdo->prepare("SELECT id FROM colaborador_agenda 
                                          WHERE colaborador_id = :colaborador_id 
                                          AND dia_semana = :dia_semana 
                                          AND recorrente = TRUE 
                                          AND disponivel = FALSE 
                                          AND hora_inicio = :hora_inicio 
                                          AND hora_fim = :hora_fim 
                                          AND descricao LIKE '%Horário de almoço%'");
                                          
                $stmt_almoco->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
                $stmt_almoco->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
                $stmt_almoco->bindParam(':hora_inicio', $inicio_almoco, PDO::PARAM_STR);
                $stmt_almoco->bindParam(':hora_fim', $fim_almoco, PDO::PARAM_STR);
                $stmt_almoco->execute();
                
                $indisponibilidade_almoco_id = $stmt_almoco->fetchColumn();
                
                if (!$indisponibilidade_almoco_id) {
                    // Criar nova indisponibilidade de almoço
                    // Usar a mesma data_atual também para o registro de almoço
                    $data_atual = date('Y-m-d');
                    
                    $stmt_criar_almoco = $pdo->prepare("INSERT INTO colaborador_agenda 
                                                    (colaborador_id, dia_semana, data_disponibilidade, hora_inicio, hora_fim, 
                                                    recorrente, disponivel, descricao, tipo) 
                                                    VALUES (:colaborador_id, :dia_semana, :data_disponibilidade, :hora_inicio, :hora_fim, 
                                                    TRUE, FALSE, 'Horário de almoço (automático)', 'sistema')");
                                                    
                    $stmt_criar_almoco->bindParam(':colaborador_id', $colaborador_id, PDO::PARAM_INT);
                    $stmt_criar_almoco->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
                    $stmt_criar_almoco->bindParam(':data_disponibilidade', $data_atual, PDO::PARAM_STR);
                    $stmt_criar_almoco->bindParam(':hora_inicio', $inicio_almoco, PDO::PARAM_STR);
                    $stmt_criar_almoco->bindParam(':hora_fim', $fim_almoco, PDO::PARAM_STR);
                    $stmt_criar_almoco->execute();
                } else {
                    // Atualizar indisponibilidade de almoço existente
                    $stmt_atualizar_almoco = $pdo->prepare("UPDATE colaborador_agenda 
                                                        SET hora_inicio = :hora_inicio, 
                                                            hora_fim = :hora_fim 
                                                        WHERE id = :id");
                                                        
                    $stmt_atualizar_almoco->bindParam(':hora_inicio', $inicio_almoco, PDO::PARAM_STR);
                    $stmt_atualizar_almoco->bindParam(':hora_fim', $fim_almoco, PDO::PARAM_STR);
                    $stmt_atualizar_almoco->bindParam(':id', $indisponibilidade_almoco_id, PDO::PARAM_INT);
                    $stmt_atualizar_almoco->execute();
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao atualizar indisponibilidades: " . $e->getMessage());
        return false;
    }
}

$titulo = "Configurações";
require_once('includes/header.php');

// Verificar se o usuário é administrador
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] != 'admin') {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>Acesso negado! Você não tem permissão para acessar esta página.
          </div>';
    require_once('includes/footer.php');
    exit;
}

// Inicializar variáveis
$mensagem = '';
$configuracoes = [
    'empresa_nome' => APP_NAME,
    'empresa_telefone' => '',
    'empresa_email' => '',
    'empresa_endereco' => '',
    'empresa_cnpj' => '',
    'taxa_padrao_mao_obra' => 100, // Valor padrão para a taxa de mão de obra (em %)
    'desconto_pagamento_vista' => 10, // Desconto para pagamento à vista (em %)
    'max_parcelas' => 12, // Número máximo de parcelas permitidas
    'dias_validade_orcamento' => 30, // Validade padrão dos orçamentos em dias
    // Configurações de tempo para visitas técnicas
    'tempo_visita_tecnica' => 30, // Tempo padrão para visitas técnicas em minutos
    'unidade_tempo_visita' => 'minutos', // Unidade de tempo para visitas técnicas (minutos, horas)
    // Horário de funcionamento
    'horario_inicio' => '07:00', // Horário de início do expediente
    'horario_fim' => '17:00', // Horário de término do expediente
    'dias_funcionamento' => '1,2,3,4,5', // Dias da semana (1=Segunda, 7=Domingo)
    // Tempos indisponíveis fixos (entrada e almoço)
    'tempo_indisponivel_entrada' => 30, // Tempo indisponível após horário de entrada (em minutos)
    'horario_inicio_almoco' => '11:00', // Horário de início do almoço
    'horario_fim_almoco' => '12:00', // Horário de término do almoço
    'aplicar_indisponibilidade_automatica' => 'sim', // Aplicar indisponibilidade automática (sim/nao)
    // Cores padrão do sistema
    'cor_principal' => '#0d6efd', // Azul bootstrap
    'cor_secundaria' => '#6c757d', // Secondary bootstrap
    'cor_aprovado' => '#198754', // Success bootstrap
    'cor_pendente' => '#ffc107', // Warning bootstrap
    'cor_rejeitado' => '#dc3545', // Danger bootstrap
    // Opção de tema
    'tema_sistema' => 'light', // light, dark
];

// Buscar configurações atuais do banco de dados
global $pdo;
try {
    // Verifica se a tabela existe primeiro
    $stmt = $pdo->prepare("SELECT to_regclass('configuracoes')");
    $stmt->execute();
    $existe_tabela = $stmt->fetchColumn();
    
    if ($existe_tabela) {
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
        $config_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } else {
        $config_db = [];
    }
} catch (Exception $e) {
    // Se ocorrer algum erro (tabela não existe, etc), inicia com array vazio
    $config_db = [];
}

// Mesclar configurações do banco com valores padrão
foreach ($config_db as $chave => $valor) {
    $configuracoes[$chave] = $valor;
}

// Processar botão atualizar indisponibilidades
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_indisponibilidades'])) {
    try {
        $pdo->beginTransaction();
        
        // Buscar configurações atuais para usar na função
        $config_atual = [];
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
        $config_atual = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Adicionar valores padrão se não existirem no banco
        $campos_necessarios = [
            'horario_inicio' => '07:00',
            'horario_fim' => '17:00',
            'horario_inicio_almoco' => '11:00',
            'horario_fim_almoco' => '12:00',
            'tempo_indisponivel_entrada' => '30'
        ];
        
        foreach ($campos_necessarios as $campo => $valor_padrao) {
            if (!isset($config_atual[$campo])) {
                $config_atual[$campo] = $valor_padrao;
            }
        }
        
        // Executar a função de atualização
        $resultado = atualizarIndisponibilidadesColaboradores($pdo, $config_atual);
        
        if ($resultado) {
            $mensagem = alerta('Indisponibilidades atualizadas com sucesso para todos os colaboradores!', 'success');
        } else {
            $mensagem = alerta('Houve um problema ao atualizar as indisponibilidades automáticas.', 'warning');
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollback();
        $mensagem = alerta('Erro ao atualizar indisponibilidades: ' . $e->getMessage(), 'danger');
    }
}
// Processar formulário geral quando enviado
else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Processar cada configuração
        foreach ($configuracoes as $chave => $valor) {
            if (isset($_POST[$chave])) {
                $novo_valor = trim($_POST[$chave]);
                
                // Verificar se a configuração já existe
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM configuracoes WHERE chave = :chave");
                $stmt->bindParam(':chave', $chave);
                $stmt->execute();
                $existe = $stmt->fetchColumn();
                
                if ($existe) {
                    // Atualizar
                    $stmt = $pdo->prepare("UPDATE configuracoes SET valor = :valor WHERE chave = :chave");
                } else {
                    // Inserir
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)");
                }
                
                $stmt->bindParam(':chave', $chave);
                $stmt->bindParam(':valor', $novo_valor);
                $stmt->execute();
                
                // Atualizar dados da empresa no config.php e salvar no banco simultaneamente
                if (in_array($chave, ['empresa_nome', 'empresa_telefone', 'empresa_email', 'empresa_endereco', 'empresa_cnpj'])) {
                    // Armazenar os dados na sessão para atualização imediata da interface
                    if ($chave === 'empresa_nome') {
                        $_SESSION['nome_empresa_temp'] = $novo_valor;
                    } else {
                        $_SESSION['empresa_' . str_replace('empresa_', '', $chave)] = $novo_valor;
                    }
                    
                    // Atualizar o arquivo config.php
                    $config_file = 'includes/config.php';
                    if (file_exists($config_file) && is_writable($config_file)) {
                        try {
                            // Escapar caracteres especiais no valor para evitar problemas com aspas
                            $novo_valor_escapado = str_replace("'", "\'", $novo_valor);
                            
                            // Ler o conteúdo do arquivo
                            $content = file_get_contents($config_file);
                            
                            // Determinar qual constante atualizar com base no campo
                            $constante = '';
                            if ($chave === 'empresa_nome') {
                                $constante = "APP_NAME";
                            } else if ($chave === 'empresa_telefone') {
                                $constante = "EMPRESA_TELEFONE";
                            } else if ($chave === 'empresa_email') {
                                $constante = "EMPRESA_EMAIL";
                            } else if ($chave === 'empresa_endereco') {
                                $constante = "EMPRESA_ENDERECO";
                            } else if ($chave === 'empresa_cnpj') {
                                $constante = "EMPRESA_CNPJ";
                            }
                            
                            // Substituir o valor da constante
                            if (!empty($constante)) {
                                $pattern = "/define\('$constante', '.*?'\);/";
                                $replacement = "define('$constante', '{$novo_valor_escapado}');";
                                $content = preg_replace($pattern, $replacement, $content);
                                
                                // Escrever o conteúdo atualizado no arquivo
                                file_put_contents($config_file, $content);
                            }
                        } catch (Exception $e) {
                            error_log('Erro ao atualizar config.php: ' . $e->getMessage());
                            throw new Exception('Não foi possível atualizar o arquivo de configuração: ' . $e->getMessage());
                        }
                    } else {
                        throw new Exception('Não foi possível atualizar o arquivo de configuração. Verifique as permissões.');
                    }
                }
                
                // Atualizar a variável local
                $configuracoes[$chave] = $novo_valor;
            }
        }
        
        // Verificar se houve mudanças nas configurações de horário
        $horario_alterado = false;
        $campos_horario = ['horario_inicio', 'horario_fim', 'horario_inicio_almoco', 'horario_fim_almoco', 'tempo_indisponivel_entrada'];
        
        foreach ($campos_horario as $campo) {
            if (isset($_POST[$campo]) && $_POST[$campo] != $config_db[$campo]) {
                $horario_alterado = true;
                break;
            }
        }
        
        // Se houve alteração nos horários e a configuração de indisponibilidade automática está ativada
        if ($horario_alterado && isset($_POST['aplicar_indisponibilidade_automatica']) && $_POST['aplicar_indisponibilidade_automatica'] == 'sim') {
            // Atualizar as indisponibilidades globais para todos os colaboradores
            try {
                $resultado = atualizarIndisponibilidadesColaboradores($pdo, $_POST);
                if ($resultado) {
                    $mensagem .= alerta('Horários atualizados e indisponibilidades recalculadas com sucesso para todos os colaboradores!', 'success');
                } else {
                    $mensagem .= alerta('Horários atualizados, mas houve um problema ao atualizar as indisponibilidades automáticas.', 'warning');
                }
            } catch (Exception $ex) {
                $mensagem .= alerta('Aviso: Houve um problema ao atualizar as indisponibilidades automáticas: ' . $ex->getMessage(), 'warning');
            }
        } elseif ($horario_alterado) {
            // Se os horários foram alterados mas a indisponibilidade automática está desativada
            $mensagem .= alerta('Os horários foram atualizados. Para atualizar as indisponibilidades dos colaboradores, ative a opção "Aplicar indisponibilidade automática" ou use o botão "Atualizar Indisponibilidades Agora".', 'info');
        }
        
        // Confirmar transação
        $pdo->commit();
        // Mantemos as mensagens anteriores para exibir corretamente os alertas de indisponibilidade
        if (empty($mensagem)) {
            $mensagem = alerta('Configurações atualizadas com sucesso!', 'success');
        }
    } catch (Exception $e) {
        // Reverter em caso de erro
        $pdo->rollback();
        $mensagem = alerta('Erro ao atualizar configurações: ' . $e->getMessage(), 'danger');
    }
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-cog me-2"></i>Configurações do Sistema</h1>
    <a href="dashboard.php" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Configurações Gerais</h5>
    </div>
    <div class="card-body">
        <form method="post">
            <!-- Dados da Empresa -->
            <div class="mb-4">
                <h5><i class="fas fa-building me-2"></i>Dados da Empresa</h5>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="empresa_nome" class="form-label">Nome da Empresa</label>
                        <input type="text" class="form-control" id="empresa_nome" name="empresa_nome" value="<?php echo $configuracoes['empresa_nome']; ?>">
                        <div class="form-text"><i class="fas fa-info-circle me-1"></i> As informações da empresa são salvas tanto no banco de dados quanto no arquivo de configuração para uso em todo o sistema.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="empresa_cnpj" class="form-label">CNPJ</label>
                        <input type="text" class="form-control" id="empresa_cnpj" name="empresa_cnpj" value="<?php echo $configuracoes['empresa_cnpj']; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="empresa_telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="empresa_telefone" name="empresa_telefone" value="<?php echo $configuracoes['empresa_telefone']; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="empresa_email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="empresa_email" name="empresa_email" value="<?php echo $configuracoes['empresa_email']; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="empresa_endereco" class="form-label">Endereço</label>
                        <textarea class="form-control" id="empresa_endereco" name="empresa_endereco" rows="3"><?php echo $configuracoes['empresa_endereco']; ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Configurações de Agendamentos -->
            <div class="mb-4">
                <h5><i class="fas fa-calendar-alt me-2"></i>Configurações de Agendamentos</h5>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="tempo_visita_tecnica" class="form-label">Tempo Padrão para Visitas Técnicas</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="tempo_visita_tecnica" name="tempo_visita_tecnica" min="15" value="<?php echo $configuracoes['tempo_visita_tecnica']; ?>">
                            <select class="form-select" id="unidade_tempo_visita" name="unidade_tempo_visita" style="max-width: 120px;">
                                <option value="minutos" <?php echo $configuracoes['unidade_tempo_visita'] == 'minutos' ? 'selected' : ''; ?>>Minutos</option>
                                <option value="horas" <?php echo $configuracoes['unidade_tempo_visita'] == 'horas' ? 'selected' : ''; ?>>Horas</option>
                            </select>
                        </div>
                        <div class="form-text">Este tempo é usado para calcular a duração de visitas técnicas agendadas pelo site. Sempre defina um tempo maior que o previsto para permitir agendamentos consecutivos sem conflitos.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="d-grid">
                            <a href="configurar_atualizacao_automatica.php" class="btn btn-primary">
                                <i class="fas fa-clock me-2"></i>Configurar Atualização Automática de Status
                            </a>
                            <div class="form-text mt-2">Configure a atualização automática de status de agendamentos com base no horário.</div>
                        </div>
                    </div>
                </div>
                
                <!-- Horários de Funcionamento -->
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6><i class="fas fa-clock me-2"></i>Horários de Funcionamento</h6>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="horario_inicio" class="form-label">Horário de Abertura</label>
                        <input type="time" class="form-control" id="horario_inicio" name="horario_inicio" value="<?php echo $configuracoes['horario_inicio']; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="horario_fim" class="form-label">Horário de Fechamento</label>
                        <input type="time" class="form-control" id="horario_fim" name="horario_fim" value="<?php echo $configuracoes['horario_fim']; ?>">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Dias de Funcionamento</label>
                        <div class="row">
                            <?php 
                            $dias_semana = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
                            $dias_funcionamento = explode(',', $configuracoes['dias_funcionamento']);
                            for ($i = 1; $i <= 7; $i++): 
                                $checked = in_array($i, $dias_funcionamento) ? 'checked' : '';
                            ?>
                            <div class="col-auto">
                                <div class="form-check">
                                    <input class="form-check-input dia-funcionamento" type="checkbox" value="<?php echo $i; ?>" id="dia_<?php echo $i; ?>" <?php echo $checked; ?>>
                                    <label class="form-check-label" for="dia_<?php echo $i; ?>">
                                        <?php echo $dias_semana[$i-1]; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endfor; ?>
                            <input type="hidden" name="dias_funcionamento" id="dias_funcionamento" value="<?php echo $configuracoes['dias_funcionamento']; ?>">
                        </div>
                    </div>
                </div>

                <!-- Configurações de Indisponibilidade Automática -->
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h6><i class="fas fa-ban me-2"></i>Configurações de Indisponibilidade Automática</h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="aplicar_indisponibilidade_switch" 
                                <?php echo $configuracoes['aplicar_indisponibilidade_automatica'] == 'sim' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="aplicar_indisponibilidade_switch">Aplicar indisponibilidade automática</label>
                            <input type="hidden" name="aplicar_indisponibilidade_automatica" id="aplicar_indisponibilidade_automatica" 
                                value="<?php echo $configuracoes['aplicar_indisponibilidade_automatica']; ?>">
                        </div>
                    </div>
                </div>
                
                <div id="indisponibilidade_config" class="<?php echo $configuracoes['aplicar_indisponibilidade_automatica'] == 'sim' ? '' : 'd-none'; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tempo_indisponivel_entrada" class="form-label">Tempo Indisponível Após Chegada</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="tempo_indisponivel_entrada" name="tempo_indisponivel_entrada" min="0" value="<?php echo $configuracoes['tempo_indisponivel_entrada']; ?>">
                                <span class="input-group-text">minutos</span>
                            </div>
                            <div class="form-text">Tempo indisponível no início do expediente (ex: 30 minutos após a chegada)</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Período de Almoço</label>
                            <div class="input-group mb-2">
                                <span class="input-group-text">Início</span>
                                <input type="time" class="form-control" id="horario_inicio_almoco" name="horario_inicio_almoco" value="<?php echo $configuracoes['horario_inicio_almoco']; ?>">
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">Fim</span>
                                <input type="time" class="form-control" id="horario_fim_almoco" name="horario_fim_almoco" value="<?php echo $configuracoes['horario_fim_almoco']; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Estas configurações serão aplicadas automaticamente a todos os colaboradores. 
                        A indisponibilidade de entrada considera o tempo após o horário de abertura, e o período de almoço bloqueia agendamentos nesse intervalo.
                        O sistema também considerará serviços em andamento que se estendam durante o período de almoço, permitindo agendar novos serviços somente após o término destes.
                    </div>
                    <button type="submit" name="atualizar_indisponibilidades" value="1" class="btn btn-warning">
                        <i class="fas fa-sync-alt me-2"></i>Atualizar Indisponibilidades Agora
                    </button>
                    <div class="form-text">Clique para atualizar imediatamente as indisponibilidades de todos os colaboradores ativos sem precisar alterar outras configurações.</div>
                </div>
            </div>
            
            <!-- Configurações de Orçamentos -->
            <div class="mb-4">
                <h5><i class="fas fa-file-invoice-dollar me-2"></i>Configurações de Orçamentos</h5>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="desconto_pagamento_vista" class="form-label">Desconto para Pagamento à Vista (%)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="desconto_pagamento_vista" name="desconto_pagamento_vista" step="0.01" min="0" max="100" value="<?php echo $configuracoes['desconto_pagamento_vista']; ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">Percentual de desconto aplicado em pagamentos à vista.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="taxa_padrao_mao_obra" class="form-label">Taxa Padrão de Mão de Obra (%)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="taxa_padrao_mao_obra" name="taxa_padrao_mao_obra" step="0.01" min="0" value="<?php echo $configuracoes['taxa_padrao_mao_obra']; ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">Este valor será usado como padrão ao criar novos orçamentos.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="dias_validade_orcamento" class="form-label">Validade Padrão dos Orçamentos (dias)</label>
                        <input type="number" class="form-control" id="dias_validade_orcamento" name="dias_validade_orcamento" min="1" value="<?php echo $configuracoes['dias_validade_orcamento']; ?>">
                        <div class="form-text">Número de dias em que os orçamentos permanecem válidos a partir da data de criação.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="max_parcelas" class="form-label">Número Máximo de Parcelas</label>
                        <input type="number" class="form-control" id="max_parcelas" name="max_parcelas" min="1" max="12" value="<?php echo $configuracoes['max_parcelas']; ?>">
                        <div class="form-text">Número máximo de parcelas permitidas para pagamento a prazo.</div>
                    </div>
                </div>
            </div>
            
            <!-- Configurações de Horário de Funcionamento -->
            <div class="mb-4">
                <h5><i class="fas fa-clock me-2"></i>Horário de Funcionamento</h5>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="horario_inicio" class="form-label">Horário de Início do Expediente</label>
                        <input type="time" class="form-control" id="horario_inicio" name="horario_inicio" value="<?php echo $configuracoes['horario_inicio']; ?>">
                        <div class="form-text">Horário em que a empresa inicia os trabalhos diariamente.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="horario_fim" class="form-label">Horário de Término do Expediente</label>
                        <input type="time" class="form-control" id="horario_fim" name="horario_fim" value="<?php echo $configuracoes['horario_fim']; ?>">
                        <div class="form-text">Horário em que a empresa encerra os trabalhos diariamente.</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Dias de Funcionamento</label>
                        <div class="d-flex flex-wrap">
                            <?php
                            $dias_semana = [
                                1 => 'Segunda-feira',
                                2 => 'Terça-feira',
                                3 => 'Quarta-feira',
                                4 => 'Quinta-feira',
                                5 => 'Sexta-feira',
                                6 => 'Sábado',
                                7 => 'Domingo'
                            ];
                            
                            $dias_selecionados = explode(',', $configuracoes['dias_funcionamento']);
                            
                            foreach ($dias_semana as $num => $nome) {
                                $checked = in_array($num, $dias_selecionados) ? 'checked' : '';
                                echo '<div class="form-check me-4 mb-2">
                                        <input class="form-check-input dia-funcionamento" type="checkbox" value="' . $num . '" id="dia_' . $num . '" ' . $checked . '>
                                        <label class="form-check-label" for="dia_' . $num . '">
                                            ' . $nome . '
                                        </label>
                                    </div>';
                            }
                            ?>
                            <input type="hidden" name="dias_funcionamento" id="dias_funcionamento" value="<?php echo $configuracoes['dias_funcionamento']; ?>">
                        </div>
                        <div class="form-text">Dias da semana em que a empresa realiza atendimentos e serviços.</div>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Estas configurações serão usadas para determinar a disponibilidade dos colaboradores e os horários válidos para agendamento de serviços.
                </div>
            </div>
            
            <!-- Configurações de Aparência -->
            <div class="mb-4">
                <h5><i class="fas fa-palette me-2"></i>Configurações de Aparência</h5>
                <hr>
                
                <!-- Tema -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <label for="tema_sistema" class="form-label">Tema do Sistema</label>
                        <select class="form-select" id="tema_sistema" name="tema_sistema">
                            <option value="light" <?php echo ($configuracoes['tema_sistema'] == 'light') ? 'selected' : ''; ?>>Tema Claro (Light)</option>
                            <option value="dark" <?php echo ($configuracoes['tema_sistema'] == 'dark') ? 'selected' : ''; ?>>Tema Escuro (Dark)</option>
                        </select>
                        <div class="form-text">Define a aparência geral do sistema (claro ou escuro).</div>
                        <div class="mt-2 form-text"><i class="fas fa-info-circle"></i> Você também pode alternar o tema rapidamente usando o botão de sol/lua no menu superior.</div>
                    </div>
                </div>
                
                <!-- Cores -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="cor_principal" class="form-label">Cor Principal do Sistema</label>
                        <input type="color" class="form-control form-control-color" id="cor_principal" name="cor_principal" value="<?php echo $configuracoes['cor_principal'] ?? '#0d6efd'; ?>" title="Escolha a cor principal">
                        <div class="form-text">Esta cor será usada para o cabeçalho e elementos principais do sistema.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="cor_secundaria" class="form-label">Cor Secundária do Sistema</label>
                        <input type="color" class="form-control form-control-color" id="cor_secundaria" name="cor_secundaria" value="<?php echo $configuracoes['cor_secundaria'] ?? '#6c757d'; ?>" title="Escolha a cor secundária">
                        <div class="form-text">Esta cor será usada para complementar a cor principal em botões e elementos secundários.</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="cor_aprovado" class="form-label">Cor para Status "Aprovado"</label>
                        <input type="color" class="form-control form-control-color" id="cor_aprovado" name="cor_aprovado" value="<?php echo $configuracoes['cor_aprovado'] ?? '#198754'; ?>" title="Escolha a cor para status aprovado">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="cor_pendente" class="form-label">Cor para Status "Pendente"</label>
                        <input type="color" class="form-control form-control-color" id="cor_pendente" name="cor_pendente" value="<?php echo $configuracoes['cor_pendente'] ?? '#ffc107'; ?>" title="Escolha a cor para status pendente">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="cor_rejeitado" class="form-label">Cor para Status "Rejeitado"</label>
                        <input type="color" class="form-control form-control-color" id="cor_rejeitado" name="cor_rejeitado" value="<?php echo $configuracoes['cor_rejeitado'] ?? '#dc3545'; ?>" title="Escolha a cor para status rejeitado">
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>As mudanças de tema e menu serão aplicadas após salvar as configurações. Para alternar o tema rapidamente, use o botão de sol/lua no canto superior direito da página.
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="button" id="btn-restaurar-cores" class="btn btn-outline-secondary me-2">Restaurar Cores Padrão</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Salvar Configurações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para CNPJ
    const cnpjInput = document.getElementById('empresa_cnpj');
    if (cnpjInput) {
        IMask(cnpjInput, {
            mask: '00.000.000/0000-00'
        });
    }
    
    // Máscara para telefone
    const telefoneInput = document.getElementById('empresa_telefone');
    if (telefoneInput) {
        IMask(telefoneInput, {
            mask: [
                {
                    mask: '(00) 0000-0000'
                },
                {
                    mask: '(00) 00000-0000'
                }
            ]
        });
    }
    
    // Gerenciar seleção de dias de funcionamento
    const diasFuncionamentoCheckboxes = document.querySelectorAll('.dia-funcionamento');
    const diasFuncionamentoInput = document.getElementById('dias_funcionamento');
    
    // Função para atualizar o campo hidden com os dias selecionados
    function atualizarDiasFuncionamento() {
        const diasSelecionados = [];
        diasFuncionamentoCheckboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                diasSelecionados.push(checkbox.value);
            }
        });
        diasFuncionamentoInput.value = diasSelecionados.join(',');
    }
    
    // Adicionar evento de mudança para cada checkbox
    diasFuncionamentoCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', atualizarDiasFuncionamento);
    });
    
    // Botão para restaurar cores padrão
    const btnRestaurarCores = document.getElementById('btn-restaurar-cores');
    if (btnRestaurarCores) {
        btnRestaurarCores.addEventListener('click', function() {
            // Cores padrão do Bootstrap
            document.getElementById('cor_principal').value = '#0d6efd';
            document.getElementById('cor_secundaria').value = '#6c757d';
            document.getElementById('cor_aprovado').value = '#198754';
            document.getElementById('cor_pendente').value = '#ffc107';
            document.getElementById('cor_rejeitado').value = '#dc3545';
            
            // Efeito visual para indicar a mudança
            const coresInputs = document.querySelectorAll('input[type="color"]');
            coresInputs.forEach(function(input) {
                input.classList.add('border-primary');
                setTimeout(function() {
                    input.classList.remove('border-primary');
                }, 800);
            });
        });
    }
    
    // Gerenciar configurações de indisponibilidade automática
    const switchIndisponibilidade = document.getElementById('aplicar_indisponibilidade_switch');
    const inputIndisponibilidade = document.getElementById('aplicar_indisponibilidade_automatica');
    const configIndisponibilidade = document.getElementById('indisponibilidade_config');
    
    if (switchIndisponibilidade && inputIndisponibilidade && configIndisponibilidade) {
        // Quando o switch mudar
        switchIndisponibilidade.addEventListener('change', function() {
            inputIndisponibilidade.value = this.checked ? 'sim' : 'nao';
            if (this.checked) {
                configIndisponibilidade.classList.remove('d-none');
            } else {
                configIndisponibilidade.classList.add('d-none');
            }
        });
    }
});
</script>


<?php require_once('includes/footer.php'); ?>