<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

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
        die('Erro de conexão com o banco de dados: ' . $e->getMessage());
    }
}

// Inicialização de variáveis
$acesso_interno = true;
$orcamento = null;
$itens = [];
$cliente = null;
$mensagem = '';
$pagamentos = [];

// Verificar se existem mensagens vindas via GET
if (isset($_GET['mensagem'])) {
    if ($_GET['mensagem'] == 'pago') {
        $mensagem = alerta('Pagamento total registrado com sucesso!', 'success');
    } elseif ($_GET['mensagem'] == 'pagamento_parcial') {
        $mensagem = alerta('Pagamento parcial registrado com sucesso!', 'success');
    } elseif ($_GET['mensagem'] == 'cadastrado') {
        $mensagem = alerta('Orçamento cadastrado com sucesso!', 'success');
    }
}

// Verificar se há mensagem de agendamento
if (isset($_GET['mensagem_agendamento'])) {
    $tipo_alerta = isset($_GET['tipo']) ? $_GET['tipo'] : 'info';
    $mensagem = alerta($_GET['mensagem_agendamento'], $tipo_alerta);
}

// Determinar como carregar o orçamento: por ID (acesso interno) ou por código de acesso (acesso externo)
if (isset($_GET['id'])) {
    // Acesso interno (por ID)
    verificarPermissao('visualizar_orcamentos');
    $id = (int)$_GET['id'];
    
    // Aplicar ações sobre o orçamento, se solicitado
    if (isset($_GET['acao']) && verificarPermissao('editar_orcamentos', false)) {
        $acao = $_GET['acao'];
        
        switch ($acao) {
            case 'aprovar':
                $stmt = $pdo->prepare("UPDATE orcamentos SET status = 'aprovado', data_aprovacao = NOW() WHERE id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $mensagem = alerta('Orçamento aprovado com sucesso!', 'success');
                break;
                
            case 'rejeitar':
                $stmt = $pdo->prepare("UPDATE orcamentos SET status = 'rejeitado' WHERE id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $mensagem = alerta('Orçamento rejeitado!', 'danger');
                break;
                
            case 'reabrir':
                $stmt = $pdo->prepare("UPDATE orcamentos SET status = 'pendente', data_aprovacao = NULL WHERE id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $mensagem = alerta('Orçamento reaberto como pendente!', 'info');
                break;
                
            case 'pendente':
                $stmt = $pdo->prepare("UPDATE orcamentos SET status_execucao = 'pendente' WHERE id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $mensagem = alerta('Status de execução atualizado para Pendente!', 'info');
                break;
                
            case 'andamento':
                $stmt = $pdo->prepare("UPDATE orcamentos SET status_execucao = 'andamento' WHERE id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $mensagem = alerta('Status de execução atualizado para Em Andamento!', 'warning');
                break;
                
            case 'finalizar':
                $stmt = $pdo->prepare("UPDATE orcamentos SET status_execucao = 'finalizado' WHERE id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $mensagem = alerta('Serviço marcado como Finalizado!', 'success');
                break;
        }
    }
    
    // Carregar o orçamento
    $stmt = $pdo->prepare("SELECT o.*, c.nome as cliente_nome, c.id as cliente_id FROM orcamentos o JOIN clientes c ON o.cliente_id = c.id WHERE o.id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Carregar itens do orçamento
        $stmt = $pdo->prepare("SELECT oi.*, p.descricao, p.unidade FROM orcamento_itens oi LEFT JOIN produtos p ON oi.produto_id = p.id WHERE oi.orcamento_id = :orcamento_id ORDER BY oi.id");
        $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Carregar cliente
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->bindParam(':id', $orcamento['cliente_id'], PDO::PARAM_INT);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Carregar pagamentos
        $stmt = $pdo->prepare("SELECT * FROM pagamentos WHERE orcamento_id = :orcamento_id ORDER BY data_pagamento DESC");
        $stmt->bindParam(':orcamento_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Título da página
        $titulo = "Orçamento #{$orcamento['numero']} - {$orcamento['cliente_nome']}";
        require_once('includes/header.php');
        
        // Interface específica para acesso interno
        echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
        echo "<h2 class='mb-0'><i class='fas fa-file-invoice-dollar me-2'></i>Orçamento #{$orcamento['numero']}</h2>";
        echo "<div class='d-flex'>";
        echo "<a href='orcamentos.php' class='btn btn-outline-secondary me-2'><i class='fas fa-arrow-left me-2'></i>Voltar</a>";
        
        // Botões de edição se tiver permissão
        if (verificarPermissao('editar_orcamentos', false)) {
            echo "<a href='orcamento_form.php?id={$orcamento['id']}' class='btn btn-primary me-2'><i class='fas fa-edit me-2'></i>Editar</a>";
            echo "<div class='dropdown'>";
            echo "<button class='btn btn-info dropdown-toggle' type='button' id='dropdownMenuButton' data-bs-toggle='dropdown' aria-expanded='false'>";
            echo "<i class='fas fa-cog me-2'></i>Ações";
            echo "</button>";
            echo "<ul class='dropdown-menu dropdown-menu-end' aria-labelledby='dropdownMenuButton'>";
            
            // Seção de mudança de status do orçamento
            if ($orcamento['status'] == 'pendente') {
                echo "<li><a class='dropdown-item' href='?id={$orcamento['id']}&acao=aprovar'><i class='fas fa-check-circle me-2 text-success'></i>Aprovar Orçamento</a></li>";
                echo "<li><a class='dropdown-item' href='?id={$orcamento['id']}&acao=rejeitar'><i class='fas fa-times-circle me-2 text-danger'></i>Rejeitar Orçamento</a></li>";
                echo "<li><hr class='dropdown-divider'></li>";
            }
            
            // Seção de mudança de status de pagamento
            if ($orcamento['status'] == 'aprovado' && $orcamento['status_pagamento'] != 'pago_total') {
                echo "<li><a class='dropdown-item' href='registrar_pagamento.php?orcamento_id={$orcamento['id']}&tipo=total'><i class='fas fa-money-bill-wave me-2 text-success'></i>Registrar Pagamento Total</a></li>";
                echo "<li><a class='dropdown-item' href='registrar_pagamento.php?orcamento_id={$orcamento['id']}&tipo=parcial'><i class='fas fa-money-bill-wave me-2 text-warning'></i>Registrar Pagamento Parcial</a></li>";
                echo "<li><hr class='dropdown-divider'></li>";
            }
            
            // Opções relacionadas a status de execução
            if ($orcamento['status_execucao'] != 'pendente') {
                echo "<li>";
                echo "<a class='dropdown-item' href='?id={$orcamento['id']}&acao=pendente'>";
                echo "<i class='fas fa-circle me-2 text-secondary'></i>Marcar como Pendente";
                echo "</a>";
                echo "</li>";
            }
            if ($orcamento['status_execucao'] != 'agendado' && $orcamento['status_execucao'] != 'andamento') {
                echo "<li>";
                echo "<a class='dropdown-item' href='agendar_servico.php?orcamento_id={$orcamento['id']}'>";
                echo "<i class='fas fa-calendar-alt me-2 text-info'></i>Agendar Serviço";
                echo "</a>";
                echo "</li>";
            } elseif ($orcamento['status_execucao'] == 'agendado') {
                // Verificar se existe um agendamento ativo
                $stmt = $pdo->prepare("SELECT data_inicio FROM agendamentos WHERE orcamento_id = :orcamento_id AND status = 'agendado' ORDER BY data_inicio ASC LIMIT 1");
                $stmt->bindParam(':orcamento_id', $orcamento['id'], PDO::PARAM_INT);
                $stmt->execute();
                $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Se há um agendamento, verificar se está a mais de 24h de distância
                $pode_reagendar = false;
                if ($agendamento) {
                    $data_agendamento = new DateTime($agendamento['data_inicio']);
                    $agora = new DateTime();
                    $intervalo = $agora->diff($data_agendamento);
                    
                    // Permitir reagendamento se faltam mais de 24h
                    $pode_reagendar = ($intervalo->days > 0 || ($intervalo->h + ($intervalo->days * 24)) >= 24);
                }
                
                if ($pode_reagendar):
                echo "<li>";
                echo "<a class='dropdown-item' href='agendamento.php?orcamento_id={$orcamento['id']}'>";
                echo "<i class='fas fa-calendar-alt me-2 text-warning'></i>Reagendar Serviço";
                echo "</a>";
                echo "</li>";
                endif;
            }
            if ($orcamento['status_execucao'] != 'andamento') {
                echo "<li>";
                echo "<a class='dropdown-item' href='?id={$orcamento['id']}&acao=andamento'>";
                echo "<i class='fas fa-spinner me-2 text-warning'></i>Marcar Em Andamento";
                echo "</a>";
                echo "</li>";
            }
            if ($orcamento['status_execucao'] != 'finalizado') {
                echo "<li>";
                echo "<a class='dropdown-item' href='?id={$orcamento['id']}&acao=finalizar'>";
                echo "<i class='fas fa-check-circle me-2 text-success'></i>Marcar como Finalizado";
                echo "</a>";
                echo "</li>";
            }
            echo "<li><hr class='dropdown-divider'></li>";
        }
        
        // Opções específicas para orçamentos rejeitados
        if ($orcamento['status'] == 'rejeitado') {
            echo "<li>";
            echo "<a class='dropdown-item' href='?id={$orcamento['id']}&acao=reabrir'>";
            echo "<i class='fas fa-redo-alt me-2'></i>Reabrir Orçamento";
            echo "</a>";
            echo "</li>";
            echo "<li><hr class='dropdown-divider'></li>";
        }
        
        // Opções gerais
        echo "<li>";
        echo "<a class='dropdown-item' href='#' onclick=\"imprimirOrcamento(); return false;\">";
        echo "<i class='fas fa-print me-2'></i>Imprimir";
        echo "</a>";
        echo "</li>";
        echo "<li>";
        echo "<a class='dropdown-item' href='#' onclick=\"copiarLinkCliente(); return false;\">";
        echo "<i class='fas fa-link me-2'></i>Copiar Link do Cliente";
        echo "</a>";
        echo "</li>";
        echo "<li>";
        echo "<a class='dropdown-item' href='mailto:{$cliente['email']}?subject=Orçamento {$orcamento['numero']}&body=Olá {$cliente['nome']}, segue o link para acessar seu orçamento: " . BASE_URL . "orcamento_visualizar.php?codigo={$orcamento['codigo_acesso']}'>";
        echo "<i class='fas fa-envelope me-2'></i>Enviar por Email";
        echo "</a>";
        echo "</li>";
        echo "<li>";
        echo "<a class='dropdown-item' href='https://api.whatsapp.com/send?phone=" . preg_replace('/\D/', '', $cliente['telefone']) . "&text=Olá " . urlencode($cliente['nome']) . ", segue o link para acessar seu orçamento: " . urlencode(BASE_URL . 'orcamento_visualizar.php?codigo=' . $orcamento['codigo_acesso']) . "' target='_blank'>";
        echo "<i class='fab fa-whatsapp me-2 text-success'></i>Enviar por WhatsApp";
        echo "</a>";
        echo "</li>";
        echo "<li><hr class='dropdown-divider'></li>";
        echo "<li>";
        echo "<a class='dropdown-item text-danger' href='#' onclick=\"confirmarExclusao({$orcamento['id']}, '{$orcamento['numero']}', 'orcamentos.php'); return false;\">";
        echo "<i class='fas fa-trash me-2'></i>Excluir";
        echo "</a>";
        echo "</li>";
        echo "</ul>";
        echo "</div>";
        echo "</div>";
        echo "</div>";

        echo $mensagem;
    }
}
else if (isset($_GET['codigo'])) {
    // Acesso externo (público) via código de acesso
    $codigo = $_GET['codigo'];
    $acesso_interno = false;
    
    // Carregar o orçamento pelo código de acesso
    $stmt = $pdo->prepare("SELECT * FROM orcamentos WHERE codigo_acesso = :codigo");
    $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Carregar itens do orçamento
        $stmt = $pdo->prepare("SELECT oi.*, p.descricao, p.unidade FROM orcamento_itens oi LEFT JOIN produtos p ON oi.produto_id = p.id WHERE oi.orcamento_id = :orcamento_id ORDER BY oi.id");
        $stmt->bindParam(':orcamento_id', $orcamento['id'], PDO::PARAM_INT);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Carregar cliente
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->bindParam(':id', $orcamento['cliente_id'], PDO::PARAM_INT);
        $stmt->execute();
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Layout para clientes
        // Iniciar HTML para acesso externo
        echo "<!DOCTYPE html>";
        echo "<html lang='pt-br'>";
        echo "<head>";
        echo "<meta charset='UTF-8'>";
        echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "<title>Orçamento #{$orcamento['numero']} - " . APP_NAME . "</title>";
        echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
        echo "<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css' rel='stylesheet'>";
        echo "<link href='css/styles.css' rel='stylesheet'>";
        echo "<style>";
        echo "body { background-color: #f8f9fa; }";
        echo ".container { max-width: 1200px; padding: 20px; }";
        echo ".card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }";
        echo ".orcamento-header { margin-bottom: 20px; }";
        echo ".status-box { padding: 5px 15px; border-radius: 20px; font-weight: bold; display: inline-block; color: white; }";
        echo ".status-pendente { background-color: #6c757d; }";
        echo ".status-aprovado { background-color: #28a745; }";
        echo ".status-rejeitado { background-color: #dc3545; }";
        echo ".status-pago { background-color: #198754; }";
        echo ".status-pago-parcial { background-color: #fd7e14; }";
        echo ".status-agendado { background-color: #0dcaf0; }";
        echo ".status-andamento { background-color: #ffc107; color: #333; }";
        echo ".status-finalizado { background-color: #20c997; }";
        echo "@media print { .no-print, .no-print * { display: none !important; } }";
        echo ".btn-action-group { margin-top: 20px; }";
        echo ".btn-action { margin-right: 10px; }";
        echo ".table-orcamento th, .table-orcamento td { padding: 10px; }";
        echo "</style>";
        echo "</head>";
        echo "<body>";
        echo "<div class='container bg-white p-4 my-5 rounded shadow'>";
        echo "<div class='row'>";
        echo "<div class='col-12'>";

        // Exibir mensagens
        echo $mensagem;
        
        // Carregar e exibir a configuração do nome da empresa
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'nome_empresa'");
        $stmt->execute();
        $nome_empresa = APP_NAME; // Valor padrão
        
        if ($stmt->rowCount() > 0) {
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            $nome_empresa = $config['valor'];
        }
        
        // Cabeçalho do orçamento para acesso externo
        echo "<div class='d-flex justify-content-between align-items-center mb-4'>";
        echo "<h2><i class='fas fa-file-invoice-dollar me-2'></i>Orçamento #{$orcamento['numero']}</h2>";
        echo "<h3>{$nome_empresa}</h3>";
        echo "</div>";
    } else {
        // Código de acesso inválido
        echo "<!DOCTYPE html>";
        echo "<html lang='pt-br'>";
        echo "<head>";
        echo "<meta charset='UTF-8'>";
        echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "<title>Orçamento não encontrado - " . APP_NAME . "</title>";
        echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>";
        echo "<link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css' rel='stylesheet'>";
        echo "<link href='css/styles.css' rel='stylesheet'>";
        echo "<style>";
        echo "body { background-color: #f8f9fa; }";
        echo ".container { max-width: 800px; padding: 20px; }";
        echo ".card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }";
        echo "</style>";
        echo "</head>";
        echo "<body>";
        echo "<div class='container bg-white p-4 my-5 rounded shadow'>";
        echo "<div class='text-center text-danger mb-4'>";
        echo "<i class='fas fa-exclamation-triangle fa-4x'></i>";
        echo "<h2 class='mt-3'>Orçamento não encontrado</h2>";
        echo "<p class='lead'>O código de acesso fornecido é inválido ou expirou.</p>";
        echo "</div>";
        echo "<div class='text-center mt-4'>";
        echo "<p>Por favor, verifique o link ou entre em contato conosco para solicitar um novo acesso.</p>";
        echo "</div>";
        echo "</div>";
        echo "</body>";
        echo "</html>";
        exit;
    }
} else {
    // Nem ID nem código de acesso fornecidos
    header('Location: orcamentos.php');
    exit;
}

// AÇÕES ESPECÍFICAS PARA ACESSO EXTERNO
if (!$acesso_interno) {
    // Processar ação de aprovação/rejeição quando vindo do acesso externo
    if (isset($_GET['acao']) && isset($_GET['codigo'])) {
        $acao = $_GET['acao'];
        $codigo = $_GET['codigo'];
        
        // Verificar se o orçamento está pendente
        if ($orcamento['status'] == 'pendente') {
            if ($acao == 'aprovar') {
                $stmt = $pdo->prepare("UPDATE orcamentos SET status = 'aprovado', data_aprovacao = NOW() WHERE codigo_acesso = :codigo");
                $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
                $stmt->execute();
                $mensagem = alerta('Você aprovou este orçamento! Agora você pode agendar o serviço.', 'success');
                $orcamento['status'] = 'aprovado'; // Atualizar status na variável
            } else if ($acao == 'rejeitar') {
                $stmt = $pdo->prepare("UPDATE orcamentos SET status = 'rejeitado' WHERE codigo_acesso = :codigo");
                $stmt->bindParam(':codigo', $codigo, PDO::PARAM_STR);
                $stmt->execute();
                $mensagem = alerta('Você rejeitou este orçamento. Entre em contato conosco se quiser discutir alterações.', 'danger');
                $orcamento['status'] = 'rejeitado'; // Atualizar status na variável
            }
        }
    }
}

// Seções para aprovação/rejeição de orçamento (apenas para acesso externo/cliente)
// Esta seção só aparece se o orçamento estiver pendente
if (!$acesso_interno && $orcamento['status'] == 'pendente'): ?>
<div class="card mb-4 no-print">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Aprovação de Orçamento</h5>
    </div>
    <div class="card-body">
        <p>Por favor, analise o orçamento abaixo e decida se deseja aprová-lo ou rejeitá-lo.</p>
        <div class="d-flex justify-content-center mt-3">
            <a href="?codigo=<?php echo $orcamento['codigo_acesso']; ?>&acao=aprovar" class="btn btn-success btn-lg me-3">
                <i class="fas fa-check me-2"></i>Aprovar Orçamento
            </a>
            <a href="?codigo=<?php echo $orcamento['codigo_acesso']; ?>&acao=rejeitar" class="btn btn-danger btn-lg" onclick="return confirm('Tem certeza que deseja rejeitar este orçamento?');">
                <i class="fas fa-times me-2"></i>Rejeitar Orçamento
            </a>
        </div>
    </div>
</div>
<?php elseif (!$acesso_interno && $orcamento['status'] != 'pendente'): ?>
    <div class="alert alert-<?php echo $orcamento['status'] == 'aprovado' ? 'success' : 'danger'; ?> no-print">
        <p class="mb-0">
            <?php if ($orcamento['status'] == 'aprovado'): ?>
                <i class="fas fa-check-circle me-2"></i> 
                Você aprovou este orçamento!
                <?php if ($orcamento['status_execucao'] == 'pendente'): ?> 
                    Você pode agendar o serviço usando o formulário abaixo.
                <?php elseif ($orcamento['status_execucao'] == 'agendado'): ?>
                    O serviço já está agendado.
                <?php endif; ?>
            <?php else: ?>
                <i class="fas fa-times-circle me-2"></i>
                Você rejeitou este orçamento. Caso queira discutir alterações ou fazer uma nova cotação, entre em contato conosco.
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['codigo']) && $orcamento['status'] == 'aprovado' && $orcamento['status_execucao'] != 'finalizado'): ?>
<?php
    // Verificar se já existe um agendamento ativo
    $agendamento_existe = false;
    $pode_reagendar = false;
    
    if ($orcamento['status_execucao'] == 'agendado') {
        $stmt = $pdo->prepare("SELECT data_inicio FROM agendamentos WHERE orcamento_id = :orcamento_id AND status = 'agendado' ORDER BY data_inicio ASC LIMIT 1");
        $stmt->bindParam(':orcamento_id', $orcamento['id'], PDO::PARAM_INT);
        $stmt->execute();
        $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($agendamento) {
            $agendamento_existe = true;
            $data_agendamento = new DateTime($agendamento['data_inicio']);
            $agora = new DateTime();
            $intervalo = $agora->diff($data_agendamento);
            
            // Permitir reagendamento se faltam mais de 24h
            $pode_reagendar = ($intervalo->days > 0 || ($intervalo->h + ($intervalo->days * 24)) >= 24);
        }
    }
?>

<!-- Seção de agendamento para clientes -->
<div class="card mb-4" id="agendamento">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>
        <?php if ($agendamento_existe && $pode_reagendar): ?>
            Reagendar Serviço
        <?php elseif ($agendamento_existe): ?>
            Serviço Agendado
        <?php else: ?>
            Agendar Serviço
        <?php endif; ?>
        </h5>
    </div>
    <div class="card-body">
        <?php if ($agendamento_existe && !$pode_reagendar): ?>
            <!-- Mostrar informações do agendamento quando já está agendado e não pode reagendar -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Seu serviço está agendado!</strong> A data e horário de execução já foram confirmados.<br>
                <?php
                    $data_formatada = $data_agendamento->format('d/m/Y');
                    $hora_formatada = $data_agendamento->format('H:i');
                ?>
                <p class="mt-2 mb-0">
                    <strong>Data:</strong> <?php echo $data_formatada; ?><br>
                    <strong>Horário:</strong> <?php echo $hora_formatada; ?> horas
                </p>
            </div>
            <div class="alert alert-warning mt-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Atenção:</strong> Reagendamentos só são permitidos com pelo menos 24 horas de antecedência.
            </div>
        <?php else: ?>
            <form id="formAgendamento" method="post" action="agendar_servico.php">
                <input type="hidden" name="orcamento_id" value="<?php echo $orcamento['id']; ?>">
                <input type="hidden" name="codigo" value="<?php echo $_GET['codigo']; ?>">
                <input type="hidden" name="tempo_previsto" value="<?php echo $orcamento['tempo_previsto']; ?>">
                <input type="hidden" name="unidade_tempo" value="<?php echo $orcamento['unidade_tempo']; ?>">
                <?php if ($agendamento_existe && $pode_reagendar): ?>
                    <input type="hidden" name="reagendamento" value="1">
                <?php endif; ?>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="data_servico" class="form-label required-field">Data de Execução</label>
                    <input type="date" class="form-control" id="data_servico" name="data_servico" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    <small class="text-muted">Selecione a data desejada para a execução do serviço.</small>
                </div>
                <div class="col-md-6">
                    <label for="hora_inicio" class="form-label required-field">Horário de Início</label>
                    <select class="form-select" id="hora_inicio" name="hora_inicio" required>
                        <option value="">Selecione o horário</option>
                        <?php
                        // Obter configurações de horário de funcionamento
                        $stmt_conf = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('horario_inicio', 'horario_fim')");
                        $config = $stmt_conf->fetchAll(PDO::FETCH_KEY_PAIR);

                        // Valores padrão caso não existam configurações
                        $horario_inicio = isset($config['horario_inicio']) ? $config['horario_inicio'] : '07:00';
                        $horario_fim = isset($config['horario_fim']) ? $config['horario_fim'] : '17:00';
                        
                        // Calcular hora máxima possível com base no tempo previsto
                        $tempo_previsto = $orcamento['tempo_previsto'];
                        $unidade_tempo = $orcamento['unidade_tempo'];
                        
                        // Converter tempo previsto para minutos
                        $duracao_minutos = $tempo_previsto;
                        if ($unidade_tempo === 'horas') {
                            $duracao_minutos = $tempo_previsto * 60;
                        } else if ($unidade_tempo === 'dias') {
                            $duracao_minutos = $tempo_previsto * 60 * 8; // 8 horas por dia
                        }
                        
                        // Extrair as horas e minutos do horário de fim
                        list($horas_fim, $minutos_fim) = explode(':', $horario_fim);
                        $minutos_total_fim = ($horas_fim * 60) + $minutos_fim;
                        
                        // Extrair as horas e minutos do horário de início
                        list($horas_inicio, $minutos_inicio) = explode(':', $horario_inicio);
                        $minutos_total_inicio = ($horas_inicio * 60) + $minutos_inicio;
                        
                        // Calcular hora máxima para início (hora_fim - duração em minutos)
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
                            
                            // Adicionar opções para cada hora disponvel em incrementos de 30 min
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
                    <label for="colaborador_id" class="form-label">Instalador Preferencial</label>
                    <select class="form-select" id="colaborador_id" name="colaborador_id">
                        <option value="">Selecione um instalador ou deixe em branco para qualquer disponível</option>
                        <?php
                        // A lista será carregada via JavaScript, dependendo da data e hora selecionadas
                        $selected_id = $orcamento['colaborador_id'] ?? 0;
                        if ($selected_id > 0) {
                            // Buscar o colaborador diretamente na tabela de colaboradores
                            $stmt = $pdo->prepare("SELECT id, nome FROM colaboradores WHERE id = :id AND tipo = 'instalador' AND status = 'ativo'");
                            $stmt->bindParam(':id', $selected_id, PDO::PARAM_INT);
                            $stmt->execute();
                            
                            if ($colaborador = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$colaborador['id']}\" selected>{$colaborador['nome']}</option>";
                            }
                        }
                        ?>
                    </select>
                    <small class="text-muted">Tempo previsto para este serviço: <?php echo $orcamento['tempo_previsto']; ?> <?php echo $orcamento['unidade_tempo']; ?>.</small>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Importante:</strong> Apenas colaboradores disponíveis para este horário são mostrados na lista.
                Os horários de trabalho são das <?php echo $horario_inicio; ?> às <?php echo $horario_fim; ?> horas.
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-calendar-check me-2"></i>Confirmar Agendamento
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!$acesso_interno && $orcamento['status'] == 'aprovado' && $orcamento['status_execucao'] != 'finalizado'): ?>
<script>
    // Script para validar o horário e carregar colaboradores disponíveis
    document.addEventListener('DOMContentLoaded', function() {
        const dataServico = document.getElementById('data_servico');
        const horaInicio = document.getElementById('hora_inicio');
        const colaboradorSelect = document.getElementById('colaborador_id');
        const tempoPrevisto = <?php echo $orcamento['tempo_previsto']; ?>;
        const unidadeTempo = '<?php echo $orcamento['unidade_tempo']; ?>';
        
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
            loadingOption.text = 'Carregando colaboradores disponíveis...';
            loadingOption.disabled = true;
            colaboradorSelect.appendChild(loadingOption);
            colaboradorSelect.selectedIndex = 1;
            
            // Buscar colaboradores disponíveis via AJAX
            fetch(`ajax/verificar_colaboradores_disponiveis.php?data=${dataValue}&hora=${horaValue}&tempo_previsto=${tempoPrevisto}&unidade_tempo=${unidadeTempo}`)
                .then(response => response.json())
                .then(data => {
                    // Remover opção de carregamento
                    colaboradorSelect.removeChild(loadingOption);
                    
                    if (data.status === 'sucesso') {
                        // Preencher select com colaboradores disponíveis
                        if (data.colaboradores.length > 0) {
                            data.colaboradores.forEach(colaborador => {
                                const option = document.createElement('option');
                                option.value = colaborador.id;
                                option.text = colaborador.nome;
                                colaboradorSelect.appendChild(option);
                            });
                        } else {
                            // Se não há colaboradores disponíveis
                            const naoDisponivelOption = document.createElement('option');
                            naoDisponivelOption.text = 'Nenhum colaborador disponível neste horário';
                            naoDisponivelOption.disabled = true;
                            colaboradorSelect.appendChild(naoDisponivelOption);
                        }
                    } else {
                        // Exibir mensagem de erro
                        const erroOption = document.createElement('option');
                        erroOption.text = 'Erro ao carregar colaboradores: ' + data.mensagem;
                        erroOption.disabled = true;
                        colaboradorSelect.appendChild(erroOption);
                    }
                })
                .catch(error => {
                    // Remover opção de carregamento
                    colaboradorSelect.removeChild(loadingOption);
                    
                    // Exibir mensagem de erro de conexão
                    const erroOption = document.createElement('option');
                    erroOption.text = 'Erro de conexão ao buscar colaboradores';
                    erroOption.disabled = true;
                    colaboradorSelect.appendChild(erroOption);
                    console.error('Erro:', error);
                });
        }
        
        // Eventos para acionar a busca de colaboradores disponíveis
        dataServico.addEventListener('change', carregarColaboradoresDisponiveis);
        
        // Ao selecionar hora, validar se há tempo suficiente e buscar colaboradores
        horaInicio.addEventListener('change', function() {
            const horaInicioStr = this.value;
            if (!horaInicioStr) return;
            
            const [hora, minuto] = horaInicioStr.split(':').map(Number);
            const horaInicioMinutos = hora * 60 + minuto;
            
            // Obter horário de fim do expediente das configurações PHP
            const horarioFim = '<?php echo $horario_fim; ?>';
            const [horaFim, minutoFim] = horarioFim.split(':').map(Number);
            const fimExpedienteMinutos = horaFim * 60 + minutoFim;
            
            // Verificar se o serviço pode ser concluído no mesmo dia
            if (horaInicioMinutos + duracaoMinutos > fimExpedienteMinutos) {
                alert('Atenção: O serviço não poderá ser concluído no mesmo dia com este horário de início.\nO serviço será continuado no próximo dia disponível.');
            }
            
            carregarColaboradoresDisponiveis();
        });
    });
</script>
<?php endif; ?>

<?php 
// Finalizando HTML para acesso externo
if (!$acesso_interno): 
?>
                </div>
            </div>

            <footer class="text-center text-muted my-5">
                <p>&copy; <?php echo date('Y'); ?> - <?php echo APP_NAME; ?></p>
            </footer>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
<?php 
    exit; // Encerrar script para acesso externo
else: 
?>

<script>
// Função para imprimir orçamento
function imprimirOrcamento() {
    window.print();
}

// Função para copiar link para o cliente
function copiarLinkCliente() {
    const link = '<?php echo BASE_URL; ?>orcamento_visualizar.php?codigo=<?php echo $orcamento['codigo_acesso']; ?>';

    // Criar elemento de texto temporário
    const temp = document.createElement('input');
    temp.value = link;
    document.body.appendChild(temp);
    temp.select();
    document.execCommand('copy');
    document.body.removeChild(temp);

    alert('Link copiado para a área de transferência!');
}
</script>

<?php 
    require_once('includes/footer.php'); 
endif;
?>

<script>
// Verificar se há uma mensagem de pagamento registrado e recarregar a página uma vez
if (window.location.href.includes('mensagem=pago') || window.location.href.includes('mensagem=pagamento_parcial')) {
    // Verificar se já recarregamos a página
    if (!sessionStorage.getItem('recarregouPaginaOrcamento')) {
        // Marcar que já recarregamos a página
        sessionStorage.setItem('recarregouPaginaOrcamento', 'true');
        // Recarregar a página após um pequeno atraso
        setTimeout(function() {
            window.location.reload();
        }, 300);
    } else {
        // Limpar a marca após a recarga
        sessionStorage.removeItem('recarregouPaginaOrcamento');
    }
}
</script>