<?php
// Script para configurar a atualização automática de status de agendamentos
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado e tem permissão de administrador
verificarAutenticacao();
if (!$_SESSION['usuario']['nivel'] === 'admin') {
    header('Location: index.php?erro=sem_permissao');
    exit;
}

$mensagem = '';

// Se o formulário for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $intervalo = isset($_POST['intervalo']) ? intval($_POST['intervalo']) : 5;
    $ativar = isset($_POST['ativar']) ? true : false;
    
    try {
        // Verificar se a tabela de configurações existe
        global $pdo;
        $stmt = $pdo->prepare("SELECT to_regclass('configuracoes')");
        $stmt->execute();
        $existe_tabela = $stmt->fetchColumn();
        
        if (!$existe_tabela) {
            // Criar tabela de configurações
            $pdo->exec("CREATE TABLE configuracoes (
                chave VARCHAR(50) PRIMARY KEY,
                valor TEXT,
                descricao TEXT,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $mensagem = alerta("Tabela de configurações criada com sucesso!", "success");
        }
        
        // Verificar se a configuração já existe
        $stmt = $pdo->prepare("SELECT chave FROM configuracoes WHERE chave = 'atualizacao_automatica_status'");
        $stmt->execute();
        $existe = $stmt->fetchColumn();
        
        if ($existe) {
            // Atualizar configuração existente
            $stmt = $pdo->prepare("UPDATE configuracoes 
                                SET valor = :valor, 
                                    data_atualizacao = CURRENT_TIMESTAMP 
                                WHERE chave = 'atualizacao_automatica_status'");
            $valor_json = json_encode(['ativo' => $ativar, 'intervalo' => $intervalo]);
            $stmt->bindParam(':valor', $valor_json);
            $stmt->execute();
            $mensagem = alerta("Configuração atualizada com sucesso!", "success");
        } else {
            // Inserir nova configuração
            $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor, descricao) 
                                VALUES ('atualizacao_automatica_status', :valor, 'Configuração de atualização automática de status de agendamentos')");
            $valor_json = json_encode(['ativo' => $ativar, 'intervalo' => $intervalo]);
            $stmt->bindParam(':valor', $valor_json);
            $stmt->execute();
            $mensagem = alerta("Configuração salva com sucesso!", "success");
        }
        
        // Verificar se a atualização está ativada
        if ($ativar) {
            // Executar o script manualmente para verificar se está funcionando
            require_once('atualizar_status_agendamentos.php');
        }
        
    } catch (Exception $e) {
        $mensagem = alerta("Erro ao configurar atualização automática: " . $e->getMessage(), "danger");
    }
}

// Buscar configuração atual
$config = ['ativo' => false, 'intervalo' => 5];
try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'atualizacao_automatica_status'");
    $stmt->execute();
    $valor = $stmt->fetchColumn();
    
    if ($valor) {
        $config = json_decode($valor, true);
    }
} catch (Exception $e) {
    // Ignora erro se a tabela não existir
}

// Incluir o cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-clock me-2"></i>Configuração de Atualização Automática</h1>
    <a href="configuracoes.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar para Configurações
    </a>
</div>

<?php echo $mensagem; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Configuração de Atualização de Status</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="ativar" name="ativar" <?php echo $config['ativo'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="ativar">Ativar atualização automática de status</label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="intervalo" class="form-label">Intervalo de verificação (minutos)</label>
                        <select class="form-select" id="intervalo" name="intervalo">
                            <option value="1" <?php echo $config['intervalo'] == 1 ? 'selected' : ''; ?>>1 minuto</option>
                            <option value="5" <?php echo $config['intervalo'] == 5 ? 'selected' : ''; ?>>5 minutos</option>
                            <option value="10" <?php echo $config['intervalo'] == 10 ? 'selected' : ''; ?>>10 minutos</option>
                            <option value="15" <?php echo $config['intervalo'] == 15 ? 'selected' : ''; ?>>15 minutos</option>
                            <option value="30" <?php echo $config['intervalo'] == 30 ? 'selected' : ''; ?>>30 minutos</option>
                            <option value="60" <?php echo $config['intervalo'] == 60 ? 'selected' : ''; ?>>1 hora</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Salvar Configuração
                    </button>
                    <a href="atualizar_status_agendamentos.php" class="btn btn-success ms-2" target="_blank">
                        <i class="fas fa-play me-2"></i>Executar Atualização Agora
                    </a>
                </form>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Sobre a Atualização Automática</h5>
            </div>
            <div class="card-body">
                <p>A atualização automática permite que os status dos agendamentos sejam alterados com base no horário:</p>
                <ul>
                    <li><strong>Em Andamento:</strong> Quando chega a hora de início do agendamento.</li>
                    <li><strong>Concluído:</strong> Quando passa da hora de fim do agendamento.</li>
                </ul>
                <p><strong>Nota:</strong> A atualização só ocorrerá quando alguém acessar o sistema ou quando o script for executado manualmente.</p>
                <p>Para uma atualização totalmente automática, é recomendado configurar um agendador de tarefas (cron job) no servidor para executar o script <code>atualizar_status_agendamentos.php</code> no intervalo desejado.</p>
                
                <div class="alert alert-secondary">
                    <p class="mb-1"><strong>Exemplo de configuração de cron job:</strong></p>
                    <code>*/5 * * * * php /caminho/para/seu_sistema/atualizar_status_agendamentos.php &gt;&gt; /caminho/para/seu_sistema/logs/cron.log 2&gt;&1</code>
                    <p class="mb-0 mt-2 small">Isso executará o script a cada 5 minutos e registrará a saída em um arquivo de log.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Status dos Agendamentos</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    global $pdo;
                    // Contar total de agendamentos por status
                    $stmt = $pdo->query("SELECT status, COUNT(*) as total FROM agendamentos GROUP BY status ORDER BY status");
                    $totais = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($totais) > 0) {
                        echo '<h6>Total de agendamentos por status:</h6>';
                        echo '<ul class="list-group mb-4">';
                        $status_map = [
                            'agendado' => ['Agendado', 'primary'],
                            'em_andamento' => ['Em Andamento', 'warning'],
                            'concluido' => ['Concluído', 'success'],
                            'cancelado' => ['Cancelado', 'danger'],
                        ];
                        
                        $total_geral = 0;
                        foreach ($totais as $item) {
                            $status = $item['status'];
                            $total = $item['total'];
                            $total_geral += $total;
                            
                            $label = isset($status_map[$status]) ? $status_map[$status][0] : ucfirst($status);
                            $color = isset($status_map[$status]) ? $status_map[$status][1] : 'secondary';
                            
                            echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
                            echo $label;
                            echo '<span class="badge bg-'.$color.' rounded-pill">'.$total.'</span>';
                            echo '</li>';
                        }
                        
                        echo '<li class="list-group-item d-flex justify-content-between align-items-center fw-bold">';
                        echo 'Total';
                        echo '<span class="badge bg-dark rounded-pill">'.$total_geral.'</span>';
                        echo '</li>';
                        echo '</ul>';
                        
                        // Mostrar agendamentos para hoje
                        $data_atual = date('Y-m-d');
                        $stmt = $pdo->prepare("SELECT a.*, c.nome as cliente_nome 
                                            FROM agendamentos a 
                                            JOIN orcamentos o ON a.orcamento_id = o.id 
                                            JOIN clientes c ON o.cliente_id = c.id 
                                            WHERE a.data_agendamento = :data_atual 
                                            ORDER BY a.hora_inicio ASC");
                        $stmt->bindParam(':data_atual', $data_atual);
                        $stmt->execute();
                        $agendamentos_hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($agendamentos_hoje) > 0) {
                            echo '<h6>Agendamentos para hoje ('.date('d/m/Y').'):</h6>';
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-sm table-hover">';
                            echo '<thead><tr>';
                            echo '<th>Horário</th>';
                            echo '<th>Cliente</th>';
                            echo '<th>Status</th>';
                            echo '</tr></thead>';
                            echo '<tbody>';
                            
                            foreach ($agendamentos_hoje as $agenda) {
                                $status = $agenda['status'];
                                $color = isset($status_map[$status]) ? $status_map[$status][1] : 'secondary';
                                $label = isset($status_map[$status]) ? $status_map[$status][0] : ucfirst($status);
                                
                                echo '<tr>';
                                echo '<td>'.date('H:i', strtotime($agenda['hora_inicio'])).' - '.date('H:i', strtotime($agenda['hora_fim'])).'</td>';
                                echo '<td>'.$agenda['cliente_nome'].'</td>';
                                echo '<td><span class="badge bg-'.$color.'">'.$label.'</span></td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody></table></div>';
                        } else {
                            echo '<div class="alert alert-info">Nenhum agendamento para hoje.</div>';
                        }
                    } else {
                        echo '<div class="alert alert-info">Nenhum agendamento registrado no sistema.</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">Erro ao buscar informações: '.$e->getMessage().'</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php require_once('includes/footer.php'); ?>