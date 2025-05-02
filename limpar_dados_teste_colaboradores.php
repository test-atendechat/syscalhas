<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
?>

<div class="container py-4">
    <h1><i class="fas fa-broom me-2"></i>Limpeza de Dados para Teste</h1>
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Executando Limpeza e Recriando Colaboradores</h5>
        </div>
        <div class="card-body">

<?php
// Iniciando transação
$pdo->beginTransaction();

try {
    // Limpar as tabelas na ordem correta para evitar problemas com chaves estrangeiras
    echo "<h3>Limpando dados...</h3>";
    
    // 1. Redefinir status dos orçamentos
    $stmt = $pdo->prepare("UPDATE orcamentos SET status_execucao = 'pendente' WHERE id IN (SELECT DISTINCT orcamento_id FROM agendamentos)");
    $stmt->execute();
    $orcamentos_atualizados = $stmt->rowCount();
    echo "<p><span class='badge bg-info'>$orcamentos_atualizados orçamentos</span> com status redefinido para 'pendente'.</p>";
    
    // 2. Limpar agendamentos primeiro já que eles referenciam orçamentos
    $pdo->exec("DELETE FROM agendamentos WHERE 1=1");
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> Tabela agendamentos limpa.</p>";
    
    // 3. Limpar notificações relacionadas a agendamentos
    $pdo->exec("DELETE FROM notificacoes WHERE tipo LIKE '%agendamento%'");
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> Notificações de agendamentos removidas.</p>";
    
    // 4. Limpar colaboradores existentes
    $pdo->exec("DELETE FROM colaboradores WHERE 1=1");
    echo "<p class='text-success'><i class='fas fa-check-circle'></i> Tabela colaboradores limpa.</p>";
    
    // Inserir novos colaboradores
    echo "<h3 class='mt-4'><i class='fas fa-user-plus me-2'></i>Inserindo novos colaboradores</h3>";
    
    echo "<div class='table-responsive'>";
    echo "<table class='table table-striped table-bordered'>";
    echo "<thead class='bg-primary text-white'><tr><th>ID</th><th>Nome</th><th>Tipo</th><th>Telefone</th></tr></thead>";
    echo "<tbody>";
    
    // Inserir orçamentistas
    $stmt = $pdo->prepare("INSERT INTO colaboradores (nome, telefone, tipo, status, data_cadastro) 
                          VALUES (?, ?, 'orcamentista', 'ativo', CURRENT_TIMESTAMP)");
    
    // Orçamentista 1
    $stmt->execute(['Carlos Orçamentos', '(11) 98765-4321']);
    $id = $pdo->lastInsertId();
    echo "<tr><td>$id</td><td>Carlos Orçamentos</td><td><span class='badge bg-info'>orçamentista</span></td><td>(11) 98765-4321</td></tr>";
    
    // Orçamentista 2
    $stmt->execute(['Maria Técnica', '(11) 99876-5432']);
    $id = $pdo->lastInsertId();
    echo "<tr><td>$id</td><td>Maria Técnica</td><td><span class='badge bg-info'>orçamentista</span></td><td>(11) 99876-5432</td></tr>";
    
    // Inserir instaladores
    $stmt = $pdo->prepare("INSERT INTO colaboradores (nome, telefone, tipo, status, data_cadastro) 
                          VALUES (?, ?, 'instalador', 'ativo', CURRENT_TIMESTAMP)");
    
    // Instalador 1
    $stmt->execute(['José Instalações', '(11) 97654-3210']);
    $id = $pdo->lastInsertId();
    echo "<tr><td>$id</td><td>José Instalações</td><td><span class='badge bg-success'>instalador</span></td><td>(11) 97654-3210</td></tr>";
    
    // Instalador 2
    $stmt->execute(['Ana Montagem', '(11) 96543-2109']);
    $id = $pdo->lastInsertId();
    echo "<tr><td>$id</td><td>Ana Montagem</td><td><span class='badge bg-success'>instalador</span></td><td>(11) 96543-2109</td></tr>";
    
    echo "</tbody></table></div>";
    
    // Confirmar as alterações
    $pdo->commit();
    
    echo "<div class='alert alert-success mt-4'>
            <i class='fas fa-check-circle me-2'></i><strong>Sucesso!</strong> Dados limpos e novos colaboradores inseridos com sucesso.
          </div>";
    
    // Listar orçamentos ativos para testes
    $stmt = $pdo->query("SELECT id, numero, status, status_execucao, valor_total FROM orcamentos WHERE status = 'aprovado' ORDER BY id DESC LIMIT 5");
    $orcamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($orcamentos) > 0) {
        echo "<h3 class='mt-4'><i class='fas fa-file-invoice-dollar me-2'></i>Orçamentos Aprovados para Teste</h3>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped table-bordered'>";
        echo "<thead class='bg-primary text-white'><tr><th>ID</th><th>Número</th><th>Status</th><th>Execução</th><th>Valor</th><th>Ações</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($orcamentos as $orcamento) {
            echo "<tr>";
            echo "<td>{$orcamento['id']}</td>";
            echo "<td>{$orcamento['numero']}</td>";
            echo "<td><span class='badge bg-success'>{$orcamento['status']}</span></td>";
            echo "<td><span class='badge bg-secondary'>{$orcamento['status_execucao']}</span></td>";
            echo "<td>R$ " . number_format($orcamento['valor_total'], 2, ',', '.') . "</td>";
            echo "<td><a href='agendamento.php?orcamento_id={$orcamento['id']}' class='btn btn-sm btn-primary'><i class='fas fa-calendar-plus me-1'></i>Agendar</a></td>";
            echo "</tr>";
        }
        
        echo "</tbody></table></div>";
    } else {
        echo "<div class='alert alert-warning mt-4'>
                <i class='fas fa-exclamation-triangle me-2'></i>Não há orçamentos aprovados para teste. Por favor, crie alguns orçamentos.
              </div>";
    }
    
} catch (Exception $e) {
    // Reverter em caso de erro
    $pdo->rollBack();
    
    echo "<div class='alert alert-danger'>
            <i class='fas fa-exclamation-circle me-2'></i><strong>Erro!</strong> " . $e->getMessage() . "
          </div>";
}
?>

        </div>
        <div class="card-footer">
            <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-home me-1"></i>Voltar para o Dashboard</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>