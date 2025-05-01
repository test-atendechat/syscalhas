<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar permissão
require_once('verificar_permissao.php');
if (!verificarPermissao('gerenciar_agendamentos') && $_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: dashboard.php?erro=' . urlencode('Você não tem permissão para acessar esta página.'));
    exit;
}

// Inicialização de variáveis
$titulo = "Novo Agendamento";

// Padrão de horas para cálculo de hora de fim
$tempo_previsto_horas = 2; // Padrão caso não tenha orçamento

// Se temos um orçamento definido, buscar seu tempo previsto
if (isset($_GET['orcamento_id']) && intval($_GET['orcamento_id']) > 0) {
    $orcamento_id = intval($_GET['orcamento_id']);
    $stmt = $db->prepare("SELECT tempo_previsto_horas FROM orcamentos WHERE id = :id");
    $stmt->bindParam(':id', $orcamento_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (isset($orcamento['tempo_previsto_horas']) && $orcamento['tempo_previsto_horas'] > 0) {
            $tempo_previsto_horas = $orcamento['tempo_previsto_horas'];
        }
    }
}

// Cálculo da hora de fim com base na hora de início e tempo previsto
$hora_inicio = '08:00';
$hora_fim = date('H:i', strtotime("+{$tempo_previsto_horas} hours", strtotime($hora_inicio)));

$agendamento = [
    'id' => 0,
    'orcamento_id' => isset($_GET['orcamento_id']) ? intval($_GET['orcamento_id']) : 0,
    'data_agendamento' => date('Y-m-d'),
    'hora_inicio' => $hora_inicio,
    'hora_fim' => $hora_fim,
    'status' => 'agendado',
    'previsao_tempo' => '',
    'temperatura' => '',
    'umidade' => '',
    'previsao_chuva' => false,
    'observacoes' => '',
    'usuario_id' => $_SESSION['usuario']['id'],
    'cliente_agendou' => false,
    'instalador_id' => [], // Agora é um array de instaladores
    'auxiliar_id' => null, // Não usado mais diretamente
    'tempo_previsto_horas' => $tempo_previsto_horas
];

// Verificar se é edição
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT * FROM agendamentos WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
        $titulo = "Editar Agendamento #" . $agendamento['id'];
        
        // Buscar instaladores selecionados na tabela relacionada
        $stmt = $db->prepare("SELECT instalador_id FROM agendamento_instaladores WHERE agendamento_id = :agendamento_id");
        $stmt->bindParam(':agendamento_id', $agendamento['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $agendamento['instalador_id'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'instalador_id');
        } else {
            // Se não houver registros na tabela relacionada, usar o instalador_id antigo como fallback
            if (!empty($agendamento['instalador_id'])) {
                $agendamento['instalador_id'] = [$agendamento['instalador_id']];
            } else {
                $agendamento['instalador_id'] = [];
            }
        }
    }
}

// Processar formulário quando enviado via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agendamento['orcamento_id'] = intval($_POST['orcamento_id']);
    $agendamento['data_agendamento'] = $_POST['data_agendamento'];
    $agendamento['hora_inicio'] = $_POST['hora_inicio'];
    $agendamento['status'] = $_POST['status'];
    $agendamento['observacoes'] = $_POST['observacoes'] ?? '';
    $agendamento['usuario_id'] = $_SESSION['usuario']['id'];
    $agendamento['instalador_id'] = !empty($_POST['instalador_id']) ? $_POST['instalador_id'] : [];
    
    // Buscar tempo previsto do orçamento
    if ($agendamento['orcamento_id'] > 0) {
        $stmt = $db->prepare("SELECT tempo_previsto_horas FROM orcamentos WHERE id = :id");
        $stmt->bindParam(':id', $agendamento['orcamento_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
            $tempo_previsto_horas = isset($orcamento['tempo_previsto_horas']) && $orcamento['tempo_previsto_horas'] > 0 ? $orcamento['tempo_previsto_horas'] : 2;
        }
    }
    
    // Calcular hora de fim com base no tempo previsto
    $hora_fim_timestamp = strtotime("+{$tempo_previsto_horas} hours", strtotime("{$agendamento['data_agendamento']} {$agendamento['hora_inicio']}"));
    $agendamento['hora_fim'] = date('H:i', $hora_fim_timestamp);
    // Auxiliar não é mais selecionado diretamente, é associado automaticamente ao instalador
    
    // Validar campos obrigatórios
    $erros = [];
    if (empty($agendamento['orcamento_id'])) {
        $erros[] = "O orçamento é obrigatório.";
    }
    if (empty($agendamento['data_agendamento'])) {
        $erros[] = "A data do agendamento é obrigatória.";
    }
    if (empty($agendamento['hora_inicio'])) {
        $erros[] = "O horário de início é obrigatório.";
    }
    
    // Validar se o horário está disponível
    if (empty($erros)) {
        // Verificar se o horário está disponível para o dia da semana
        $dia_semana = date('w', strtotime($agendamento['data_agendamento']));
        
        $stmt = $db->prepare("SELECT * FROM horarios_disponiveis 
                             WHERE dia_semana = :dia_semana 
                             AND hora_inicio = :hora_inicio 
                             AND hora_fim = :hora_fim 
                             AND disponivel = true");
        $stmt->bindParam(':dia_semana', $dia_semana, PDO::PARAM_INT);
        $stmt->bindParam(':hora_inicio', $agendamento['hora_inicio']);
        $stmt->bindParam(':hora_fim', $agendamento['hora_fim']);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            $erros[] = "O horário selecionado não está disponível para agendamento.";
        } else {
            // Verificar se já existe outro agendamento no mesmo horário (exceto o atual em caso de edição)
            $sql = "SELECT * FROM agendamentos 
                   WHERE data_agendamento = :data_agendamento 
                   AND hora_inicio = :hora_inicio 
                   AND status NOT IN ('cancelado', 'reagendado')";
            $params = [
                ':data_agendamento' => $agendamento['data_agendamento'],
                ':hora_inicio' => $agendamento['hora_inicio']
            ];
            
            if ($agendamento['id'] > 0) {
                $sql .= " AND id != :id";
                $params[':id'] = $agendamento['id'];
            }
            
            $stmt = $db->prepare($sql);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $erros[] = "Já existe um agendamento para o horário selecionado.";
            }
        }
        
        // Verificar se o dia está bloqueado por indisponibilidade
        $stmt = $db->prepare("SELECT * FROM indisponibilidades 
                             WHERE :data_agendamento BETWEEN data_inicio AND data_fim");
        $stmt->bindParam(':data_agendamento', $agendamento['data_agendamento']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $indisponibilidade = $stmt->fetch(PDO::FETCH_ASSOC);
            $erros[] = "O dia selecionado está bloqueado para agendamentos. Motivo: {$indisponibilidade['motivo']}";
        }
    }
    
    // Se não houver erros, consultar a previsão do tempo
    if (empty($erros)) {
        // Buscar configurações da API de previsão do tempo
        $stmt = $db->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('api_previsao_tempo', 'api_previsao_tempo_key', 'cidade_previsao_tempo')");
        $stmt->execute();
        $configuracoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converter resultado para array associativo
        $configs = [];
        foreach ($configuracoes as $config) {
            $configs[$config['chave']] = $config['valor'];
        }
        
        $api_ativa = isset($configs['api_previsao_tempo']) && $configs['api_previsao_tempo'] === 'true';
        $api_key = $configs['api_previsao_tempo_key'] ?? '';
        $cidade = $configs['cidade_previsao_tempo'] ?? 'São Paulo';
        
        // Se a API estiver ativa e tivermos uma chave, consultar a previsão
        if ($api_ativa && !empty($api_key)) {
            // Simular dados de previsão do tempo
            // Em produção, aqui faria uma chamada real para a API de previsão do tempo
            $previsoes = [
                'sol' => [
                    'descricao' => 'Dia ensolarado',
                    'temperatura' => rand(25, 32),
                    'umidade' => rand(30, 60),
                    'chuva' => false
                ],
                'nublado' => [
                    'descricao' => 'Parcialmente nublado',
                    'temperatura' => rand(20, 28),
                    'umidade' => rand(50, 70),
                    'chuva' => false
                ],
                'chuva' => [
                    'descricao' => 'Possibilidade de chuva',
                    'temperatura' => rand(18, 25),
                    'umidade' => rand(70, 95),
                    'chuva' => true
                ]
            ];
            
            // Selecionar uma previsão aleatória (apenas para demonstração)
            $tipos = array_keys($previsoes);
            $tipo_aleatorio = $tipos[array_rand($tipos)];
            $previsao = $previsoes[$tipo_aleatorio];
            
            $agendamento['previsao_tempo'] = $previsao['descricao'];
            $agendamento['temperatura'] = $previsao['temperatura'];
            $agendamento['umidade'] = $previsao['umidade'];
            $agendamento['previsao_chuva'] = $previsao['chuva'];
        }
    }
    
    // Se não houver erros, salvar no banco de dados
    if (empty($erros)) {
        // Nova abordagem: excluir associações anteriores de instaladores (se houver) e inserir as novas
        $transaction_success = true;
        
        // Iniciar transação para garantir consistência dos dados
        $db->beginTransaction();
        
        try {
            // Primeiro, inserir ou atualizar o agendamento principal
            if ($agendamento['id'] > 0) {
                // Atualizar agendamento existente
                $stmt = $db->prepare("UPDATE agendamentos SET 
                                    orcamento_id = :orcamento_id,
                                    data_agendamento = :data_agendamento,
                                    hora_inicio = :hora_inicio,
                                    hora_fim = :hora_fim,
                                    status = :status,
                                    previsao_tempo = :previsao_tempo,
                                    temperatura = :temperatura,
                                    umidade = :umidade,
                                    previsao_chuva = :previsao_chuva,
                                    observacoes = :observacoes,
                                    usuario_id = :usuario_id,
                                    atualizado_em = CURRENT_TIMESTAMP
                                    WHERE id = :id");
                $stmt->bindParam(':id', $agendamento['id'], PDO::PARAM_INT);
                $agendamento_id = $agendamento['id'];
            } else {
                // Inserir novo agendamento
                $stmt = $db->prepare("INSERT INTO agendamentos (
                                    orcamento_id, data_agendamento, hora_inicio, hora_fim, 
                                    status, previsao_tempo, temperatura, umidade, previsao_chuva, 
                                    observacoes, usuario_id, cliente_agendou
                                    ) VALUES (
                                    :orcamento_id, :data_agendamento, :hora_inicio, :hora_fim, 
                                    :status, :previsao_tempo, :temperatura, :umidade, :previsao_chuva, 
                                    :observacoes, :usuario_id, :cliente_agendou
                                    )");
                $stmt->bindParam(':cliente_agendou', $agendamento['cliente_agendou'], PDO::PARAM_BOOL);
            }
            
            // Bind parameters comuns para inserção e atualização do agendamento principal
            $stmt->bindParam(':orcamento_id', $agendamento['orcamento_id'], PDO::PARAM_INT);
            $stmt->bindParam(':data_agendamento', $agendamento['data_agendamento']);
            $stmt->bindParam(':hora_inicio', $agendamento['hora_inicio']);
            $stmt->bindParam(':hora_fim', $agendamento['hora_fim']);
            $stmt->bindParam(':status', $agendamento['status']);
            $stmt->bindParam(':previsao_tempo', $agendamento['previsao_tempo']);
            
            // Corrigir campos numéricos: garantir que valores vazios sejam convertidos para NULL ou 0
            $temperatura = !empty($agendamento['temperatura']) ? $agendamento['temperatura'] : NULL;
            $umidade = !empty($agendamento['umidade']) ? $agendamento['umidade'] : NULL;
            
            $stmt->bindParam(':temperatura', $temperatura);
            $stmt->bindParam(':umidade', $umidade);
            $stmt->bindParam(':previsao_chuva', $agendamento['previsao_chuva'], PDO::PARAM_BOOL);
            $stmt->bindParam(':observacoes', $agendamento['observacoes']);
            $stmt->bindParam(':usuario_id', $agendamento['usuario_id'], PDO::PARAM_INT);
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao salvar agendamento principal");
            }
            
            // Obter o ID do agendamento se for uma inserção
            if (!$agendamento['id'] > 0) {
                $agendamento_id = $db->lastInsertId();
            }
            
            // Agora lidamos com os instaladores e auxiliares numa tabela relacionada
            // Primeiro, excluir quaisquer relacionamentos existentes para este agendamento
            $stmt = $db->prepare("DELETE FROM agendamento_instaladores WHERE agendamento_id = :agendamento_id");
            $stmt->bindParam(':agendamento_id', $agendamento_id, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                throw new Exception("Erro ao limpar instaladores anteriores");
            }
            
            // Agora inserir os novos instaladores selecionados
            if (!empty($agendamento['instalador_id']) && is_array($agendamento['instalador_id'])) {
                foreach ($agendamento['instalador_id'] as $instalador_id) {
                    $stmt = $db->prepare("INSERT INTO agendamento_instaladores 
                                          (agendamento_id, instalador_id) 
                                          VALUES (:agendamento_id, :instalador_id)");
                    $stmt->bindParam(':agendamento_id', $agendamento_id, PDO::PARAM_INT);
                    $stmt->bindParam(':instalador_id', $instalador_id, PDO::PARAM_INT);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Erro ao vincular instalador ao agendamento");
                    }
                    
                    // Verificar se o instalador tem auxiliar e vinculá-lo automaticamente
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
                            
                            if (!$stmt->execute()) {
                                throw new Exception("Erro ao vincular auxiliar automaticamente");
                            }
                        }
                    }
                }
            }
            
            // Se chegou até aqui, confirmar transação
            $db->commit();
            
            // Formatar data e hora para exibição
            $data_formatada = date('d/m/Y', strtotime($agendamento['data_agendamento']));
            $hora_formatada = substr($agendamento['hora_inicio'], 0, 5);
            
            // Se veio do link direto do orçamento, redirecionar de volta para visualização do orçamento
            if ($agendamento['orcamento_id'] > 0) {
                // Obter o ID do orçamento
                $orcamento_id = $agendamento['orcamento_id'];
                
                // Redirecionar para a visualização do orçamento com mensagem de sucesso
                header("Location: orcamento_visualizar.php?id={$orcamento_id}&mensagem=agendado&data={$data_formatada}&hora={$hora_formatada}");
                exit;
            } else {
                // Redirecionar para página de agendamentos
                header("Location: agendamentos.php?mensagem=Agendamento " . ($agendamento['id'] > 0 ? 'atualizado' : 'criado') . " com sucesso!");
                exit;
            }
            
        } catch (Exception $e) {
            // Se ocorrer um erro, reverter transação
            $db->rollBack();
            $erros[] = "Erro ao salvar o agendamento: " . $e->getMessage();
        }
    }
}

// Incluir cabeçalho
require_once('includes/header.php');

// Exibir mensagens de erro, se houver
if (!empty($erros)) {
    echo '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($erros as $erro) {
        echo '<li>' . htmlspecialchars($erro) . '</li>';
    }
    echo '</ul></div>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo $titulo; ?></h1>
    <a href="agendamentos.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-calendar-plus me-2"></i>Formulário de Agendamento
    </div>
    <div class="card-body">
        <form method="post" class="needs-validation" novalidate>
            <input type="hidden" name="id" value="<?php echo $agendamento['id']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="orcamento_id" class="form-label required">Orçamento</label>
                    <select class="form-select" id="orcamento_id" name="orcamento_id" required>
                        <option value="">Selecione um orçamento...</option>
                        <?php
                        // Listar orçamentos aprovados
                        $stmt = $db->prepare("SELECT o.id, o.numero, c.nome as cliente_nome 
                                             FROM orcamentos o 
                                             INNER JOIN clientes c ON o.cliente_id = c.id 
                                             WHERE o.status = 'aprovado' 
                                             ORDER BY o.numero DESC");
                        $stmt->execute();
                        $orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($orcamentos as $orcamento) {
                            $selected = ($agendamento['orcamento_id'] == $orcamento['id']) ? 'selected' : '';
                            echo "<option value=\"{$orcamento['id']}\" {$selected}>#{$orcamento['numero']} - {$orcamento['cliente_nome']}</option>";
                        }
                        ?>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione um orçamento.</div>
                </div>
                <div class="col-md-3">
                    <label for="data_agendamento" class="form-label required">Data</label>
                    <input type="date" class="form-control" id="data_agendamento" name="data_agendamento" value="<?php echo $agendamento['data_agendamento']; ?>" required min="<?php echo date('Y-m-d'); ?>">
                    <div class="invalid-feedback">Por favor, selecione uma data válida.</div>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label required">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="agendado" <?php echo $agendamento['status'] === 'agendado' ? 'selected' : ''; ?>>Agendado</option>
                        <option value="concluido" <?php echo $agendamento['status'] === 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                        <option value="cancelado" <?php echo $agendamento['status'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                        <option value="reagendado" <?php echo $agendamento['status'] === 'reagendado' ? 'selected' : ''; ?>>Reagendado</option>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione um status.</div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="hora_inicio" class="form-label required">Horário Início</label>
                    <select class="form-select" id="hora_inicio" name="hora_inicio" required>
                        <?php
                        // Listar horários disponíveis
                        $horarios_inicio = [
                            '08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'
                        ];
                        
                        foreach ($horarios_inicio as $hora) {
                            $selected = ($agendamento['hora_inicio'] === $hora) ? 'selected' : '';
                            echo "<option value=\"{$hora}\" {$selected}>{$hora}</option>";
                        }
                        ?>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione um horário de início.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Horário Fim (Calculado)</label>
                    <input type="text" class="form-control" id="hora_fim_display" value="<?php echo $agendamento['hora_fim']; ?>" readonly>
                    <input type="hidden" id="hora_fim" name="hora_fim" value="<?php echo $agendamento['hora_fim']; ?>">
                    <div class="form-text">Calculado automaticamente com base no tempo previsto do orçamento.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Instaladores</label>
                    <div class="mb-2">
                        <div class="input-group">
                            <select class="form-select" id="instalador_select">
                                <option value="">Selecione um instalador...</option>
                                <?php
                                // Listar instaladores ativos
                                $stmt = $db->prepare("SELECT id, nome FROM instaladores WHERE ativo = true ORDER BY nome ASC");
                                $stmt->execute();
                                $instaladores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Converter string de IDs para array se necessário
                                $instaladores_selecionados = [];
                                if (isset($agendamento['instalador_id']) && !empty($agendamento['instalador_id'])) {
                                    if (is_array($agendamento['instalador_id'])) {
                                        $instaladores_selecionados = $agendamento['instalador_id'];
                                    } else {
                                        $instaladores_selecionados = [$agendamento['instalador_id']];
                                    }
                                }
                                
                                foreach ($instaladores as $instalador) {
                                    echo "<option value=\"{$instalador['id']}\">{$instalador['nome']}</option>";
                                }
                                ?>
                            </select>
                            <button type="button" class="btn btn-primary" id="adicionar_instalador">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div id="instaladores_selecionados" class="list-group">
                        <?php if (!empty($instaladores_selecionados)): ?>
                            <?php foreach ($instaladores_selecionados as $instalador_id): ?>
                                <?php 
                                // Buscar dados do instalador
                                foreach ($instaladores as $instalador) {
                                    if ($instalador['id'] == $instalador_id) {
                                        echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
                                        echo '<div><i class="fas fa-hard-hat me-2"></i>' . $instalador['nome'] . '</div>';
                                        echo '<input type="hidden" name="instalador_id[]" value="' . $instalador_id . '">';
                                        echo '<button type="button" class="btn btn-sm btn-danger remover-instalador"><i class="fas fa-times"></i></button>';
                                        echo '</div>';
                                        break;
                                    }
                                }
                                ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-text">Adicione um ou mais instaladores. Auxiliares serão atribuídos automaticamente.</div>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label d-block">Previsão do Tempo</label>
                    <?php if (!empty($agendamento['previsao_tempo'])): ?>
                        <div class="alert alert-<?php echo $agendamento['previsao_chuva'] ? 'warning' : 'info'; ?> mb-0">
                            <strong><?php echo $agendamento['previsao_tempo']; ?></strong>
                            <?php if (!empty($agendamento['temperatura'])): ?>
                                <br>Temperatura: <?php echo $agendamento['temperatura']; ?>°C
                            <?php endif; ?>
                            <?php if (!empty($agendamento['umidade'])): ?>
                                <br>Umidade: <?php echo $agendamento['umidade']; ?>%
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border mb-0">
                            <i class="fas fa-cloud-sun me-2"></i>A previsão do tempo será consultada ao salvar.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $agendamento['observacoes']; ?></textarea>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Salvar Agendamento
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Informações sobre o Orçamento -->
<?php if ($agendamento['orcamento_id'] > 0): ?>
    <?php
    // Buscar dados do orçamento
    $stmt = $db->prepare("SELECT o.*, c.nome as cliente_nome, c.telefone, c.email 
                         FROM orcamentos o 
                         INNER JOIN clientes c ON o.cliente_id = c.id 
                         WHERE o.id = :id");
    $stmt->bindParam(':id', $agendamento['orcamento_id'], PDO::PARAM_INT);
    $stmt->execute();
    $orcamento_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($orcamento_info):
    ?>
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <i class="fas fa-file-invoice-dollar me-2"></i>Detalhes do Orçamento #<?php echo $orcamento_info['numero']; ?>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Dados do Orçamento</h5>
                    <p class="mb-1"><strong>Número:</strong> <?php echo $orcamento_info['numero']; ?></p>
                    <p class="mb-1"><strong>Data:</strong> <?php echo dataParaBr($orcamento_info['data_criacao']); ?></p>
                    <p class="mb-1"><strong>Valor Total:</strong> <?php echo formataValor($orcamento_info['valor_total']); ?></p>
                    <p class="mb-1"><strong>Status:</strong> <span class="badge status-<?php echo $orcamento_info['status']; ?>"><?php echo ucfirst($orcamento_info['status']); ?></span></p>
                </div>
                <div class="col-md-6">
                    <h5>Dados do Cliente</h5>
                    <p class="mb-1"><strong>Nome:</strong> <?php echo $orcamento_info['cliente_nome']; ?></p>
                    <p class="mb-1"><strong>Telefone:</strong> <?php echo $orcamento_info['telefone']; ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo $orcamento_info['email']; ?></p>
                </div>
            </div>
            <div class="text-end mt-3">
                <a href="orcamento_visualizar.php?id=<?php echo $agendamento['orcamento_id']; ?>" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-eye me-2"></i>Ver Orçamento Completo
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Horários Disponíveis -->
<div class="card mt-4">
    <div class="card-header bg-success text-white">
        <i class="fas fa-clock me-2"></i>Horários Disponíveis para Agendamento
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Aqui será exibida uma tabela com os horários disponíveis para a data selecionada.
        </div>
        <div id="horarios-disponiveis" class="mt-3">
            <!-- Será preenchido via AJAX ao selecionar a data -->
        </div>
    </div>
</div>

<?php require_once('includes/footer.php'); ?>

<script>
// Validar formulário
document.addEventListener('DOMContentLoaded', function() {
    // Formulário de validação Bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Carregar horários disponíveis ao selecionar a data
    const dataInput = document.getElementById('data_agendamento');
    dataInput.addEventListener('change', function() {
        const data = this.value;
        // Aqui deve ser implementada uma chamada AJAX para buscar horários disponíveis
        // Exemplo básico para ilustrar funcionalidade
        
        document.getElementById('horarios-disponiveis').innerHTML = `
            <div class="alert alert-success">
                <strong><i class="fas fa-check-circle me-2"></i>Horários disponíveis para ${data}:</strong>
                <ul class="mb-0 mt-2">
                    <li>Manhã: 08:00 - 12:00</li>
                    <li>Tarde: 13:00 - 17:00</li>
                </ul>
            </div>
        `;
    });
    
    // Removido código anterior que manipulava o select de hora_fim, agora usamos a função calcularHoraFim()
    
    // Funcionalidade para adicionar e remover instaladores
    const instaladorSelect = document.getElementById('instalador_select');
    const btnAdicionarInstalador = document.getElementById('adicionar_instalador');
    const instaladoresSelecionados = document.getElementById('instaladores_selecionados');
    
    // Função para adicionar um instalador à lista
    function adicionarInstalador() {
        const instaladorId = instaladorSelect.value;
        const instaladorNome = instaladorSelect.options[instaladorSelect.selectedIndex].text;
        
        // Verificar se já foi selecionado e se um instalador foi escolhido
        if(!instaladorId) {
            alert('Por favor, selecione um instalador.');
            return;
        }
        
        // Verificar se este instalador já foi adicionado
        const instaladoresExistentes = document.querySelectorAll('input[name="instalador_id[]"]');
        for(let i = 0; i < instaladoresExistentes.length; i++) {
            if(instaladoresExistentes[i].value === instaladorId) {
                alert('Este instalador já foi adicionado.');
                return;
            }
        }
        
        // Criar elemento para o instalador selecionado
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex justify-content-between align-items-center';
        item.innerHTML = `
            <div><i class="fas fa-hard-hat me-2"></i>${instaladorNome}</div>
            <input type="hidden" name="instalador_id[]" value="${instaladorId}">
            <button type="button" class="btn btn-sm btn-danger remover-instalador"><i class="fas fa-times"></i></button>
        `;
        
        // Adicionar à lista
        instaladoresSelecionados.appendChild(item);
        
        // Resetar o select
        instaladorSelect.value = '';
    }
    
    // Adicionar evento de clique ao botão
    btnAdicionarInstalador.addEventListener('click', adicionarInstalador);
    
    // Permitir pressionar Enter no select para adicionar
    
    // Lógica para atualizar hora_fim automaticamente com base no tempo previsto do orçamento
    const horaInicio = document.getElementById('hora_inicio');
    const horaFim = document.getElementById('hora_fim');
    const horaFimDisplay = document.getElementById('hora_fim_display');
    const orcamentoSelect = document.getElementById('orcamento_id');
    
    // Armazenar o tempo previsto em horas do orçamento (inicialmente vazio)
    let tempoPrevisto = 0;
    
    // Função para formatar a exibição do tempo previsto (em horas ou dias)
    function formatarTempoPrevisto(horas) {
        if (horas <= 24) {
            return horas + ' hora' + (horas > 1 ? 's' : '');
        } else {
            const dias = Math.ceil(horas / 24);
            return dias + ' dia' + (dias > 1 ? 's' : '');
        }
    }
    
    // Função para calcular novo horário de fim com base na hora de início e tempo previsto
    function calcularHoraFim() {
        // Obter hora selecionada
        const horaInicioSelecionada = horaInicio.value;
        if (!horaInicioSelecionada) return;
        
        // Verificar se um orçamento foi selecionado
        if (!tempoPrevisto || tempoPrevisto <= 0) {
            horaFim.value = '';
            horaFimDisplay.value = 'Selecione um orçamento para calcular';
            return;
        }
        
        // Converter para objeto Date para facilitar cálculos
        const [horas, minutos] = horaInicioSelecionada.split(':');
        const dataBase = new Date();
        dataBase.setHours(parseInt(horas));
        dataBase.setMinutes(parseInt(minutos));
        
        // Adicionar o tempo previsto em horas
        dataBase.setTime(dataBase.getTime() + (tempoPrevisto * 60 * 60 * 1000));
        
        // Formatar a nova hora
        const horasNovas = String(dataBase.getHours()).padStart(2, '0');
        const minutosNovos = String(dataBase.getMinutes()).padStart(2, '0');
        const horaFimCalculada = `${horasNovas}:${minutosNovos}`;
        
        // Atualizar o valor do campo oculto e do display
        horaFim.value = horaFimCalculada;
        horaFimDisplay.value = horaFimCalculada + ' (tempo previsto: ' + formatarTempoPrevisto(tempoPrevisto) + ')';
    }
    
    // Atualizar hora de fim quando mudar hora de início
    horaInicio.addEventListener('change', calcularHoraFim);
    
    // Atualizar hora de fim quando mudar orçamento (buscar o tempo previsto via AJAX)
    orcamentoSelect.addEventListener('change', function() {
        const orcamentoId = this.value;
        if (!orcamentoId) return;
        
        // Buscar o tempo previsto do orçamento via AJAX
        fetch(`ajax/buscar_tempo_previsto.php?orcamento_id=${orcamentoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    // Atualizar o tempo previsto e recalcular hora fim
                    tempoPrevisto = data.tempo_previsto_horas;
                    calcularHoraFim();
                }
            })
            .catch(error => {
                console.error('Erro ao buscar o tempo previsto:', error);
            });
    });
    instaladorSelect.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            adicionarInstalador();
        }
    });
    
    // Lidar com a remoção de instaladores (delegação de eventos)
    instaladoresSelecionados.addEventListener('click', function(e) {
        if(e.target.classList.contains('remover-instalador') || e.target.parentElement.classList.contains('remover-instalador')) {
            // Encontrar o elemento pai mais próximo que é um item da lista
            const item = e.target.closest('.list-group-item');
            if(item) {
                item.remove();
            }
        }
    });
    
    // Iniciar o carregamento dos horários disponíveis se há uma data selecionada
    if (dataInput.value) {
        // Simular o evento para carregar horários disponíveis na inicialização
        const event = new Event('change');
        dataInput.dispatchEvent(event);
    }
    
    // Calcular hora_fim na inicialização
    if (horaInicio.value) {
        calcularHoraFim();
    }
});
</script>