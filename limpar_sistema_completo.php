<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';
?>

<div class="container py-4">
    <h1><i class="fas fa-broom me-2"></i>Limpeza Completa do Sistema</h1>
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Executando Limpeza Completa</h5>
        </div>
        <div class="card-body">

<?php
// Iniciando transação
$pdo->beginTransaction();

try {
    echo "<h3>Iniciando limpeza...</h3>";
    
    // Verificar ID do usuário admin para preservar
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = 'admin@admin.com'");
    $stmt->execute();
    $admin_id = $stmt->fetchColumn();
    
    if (!$admin_id) {
        throw new Exception("Usuário admin não encontrado. A limpeza foi cancelada.");
    }
    
    echo "<p>Preservando usuário admin (ID: $admin_id)</p>";
    
    // Não podemos usar SET session_replication_role pois não temos privilégios
    // Vamos limpar as tabelas na ordem correta para respeitar as chaves estrangeiras
    
    // Limpar todas as tabelas manualmente na ordem correta para evitar violações de chave estrangeira
    // 1. Primeiro limpar tabelas dependentes
    echo "<h4>Limpando tabelas de dados...</h4>";
    
    // Limpando tabelas de relacionamentos e dependências
    $pdo->exec("DELETE FROM agendamentos");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Agendamentos removidos</p>";
    
    $pdo->exec("DELETE FROM notificacoes");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Notificações removidas</p>";
    
    $pdo->exec("DELETE FROM colaborador_equipe");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Relacionamentos de equipes removidos</p>";
    
    $pdo->exec("DELETE FROM orcamento_itens");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Itens de orçamentos removidos</p>";
    
    $pdo->exec("DELETE FROM produtos_movimentacoes");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Movimentações de produtos removidas</p>";
    
    $pdo->exec("DELETE FROM caixa_movimentacoes");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Movimentações de caixa removidas</p>";
    
    // 2. Agora limpar tabelas principais
    $pdo->exec("DELETE FROM equipes");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Equipes removidas</p>";
    
    $pdo->exec("DELETE FROM colaboradores");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Colaboradores removidos</p>";
    
    $pdo->exec("DELETE FROM orcamentos");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Orçamentos removidos</p>";
    
    $pdo->exec("DELETE FROM produtos");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Produtos removidos</p>";
    
    $pdo->exec("DELETE FROM contas_pagar");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Contas a pagar removidas</p>";
    
    $pdo->exec("DELETE FROM caixa_controle");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Controle de caixa removido</p>";
    
    $pdo->exec("DELETE FROM clientes");
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Clientes removidos</p>";
    
    echo "<div class='alert alert-info mt-2 mb-3'>
           <i class='fas fa-info-circle me-2'></i>Todas as tabelas de dados foram limpas com sucesso.
         </div>";

    
    // Limpar outros usuários, manter apenas o admin
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id != :admin_id");
    $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuarios_removidos = $stmt->rowCount();
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>$usuarios_removidos usuários foram removidos. Apenas o admin foi preservado.</p>";
    
    // Garantir que o admin tenha a senha correta (admin123)
    $senha_hasheada = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :admin_id");
    $stmt->bindParam(':senha', $senha_hasheada);
    $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
    $stmt->execute();
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Senha do admin foi redefinida para <strong>admin123</strong></p>";
    
    // Não precisamos reativar verificações de chave estrangeira, já que não as desativamos
    
    // Adicionar dados iniciais necessários
    
    // Inserir um cliente de exemplo
    $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, endereco, data_cadastro) 
                           VALUES ('Cliente Exemplo', 'cliente@exemplo.com', '(11) 99999-9999', 'Rua Exemplo, 123', CURRENT_TIMESTAMP) RETURNING id");
    $stmt->execute();
    $cliente_id = $stmt->fetchColumn();
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Cliente exemplo inserido com ID: $cliente_id</p>";
    
    // Inserir colaboradores básicos
    $stmt = $pdo->prepare("INSERT INTO colaboradores (nome, telefone, tipo, status, data_cadastro) 
                          VALUES (?, ?, ?, 'ativo', CURRENT_TIMESTAMP) RETURNING id");
    
    // Inserir orçamentista
    $stmt->execute(['Carlos Orçamentista', '(11) 98765-4321', 'orcamentista']);
    $orcamentista_id = $stmt->fetchColumn();
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Orçamentista 'Carlos Orçamentista' inserido com ID: $orcamentista_id</p>";
    
    // Inserir instalador
    $stmt->execute(['José Instalador', '(11) 97654-3210', 'instalador']);
    $instalador_id = $stmt->fetchColumn();
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Instalador 'José Instalador' inserido com ID: $instalador_id</p>";
    
    // Inserir produtos de exemplo
    $produtos = [
        ['Calha Galvanizada 3m', 'Metro', 50, 45.90, 'Material de alta durabilidade'],
        ['Ralo Abacaxi', 'Unidade', 30, 18.50, 'Ralo para escoamento'],
        ['Condutor 3m', 'Unidade', 25, 35.75, 'Condutor para água pluvial'],
        ['Suporte para Calha', 'Unidade', 100, 8.25, 'Suporte de fixação']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO produtos (descricao, unidade, estoque_atual, preco, observacoes, data_cadastro) 
                           VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP) RETURNING id");
    
    foreach ($produtos as $produto) {
        $stmt->execute([$produto[0], $produto[1], $produto[2], $produto[3], $produto[4]]);
        $produto_id = $stmt->fetchColumn();
        echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Produto '" . $produto[0] . "' inserido com ID: $produto_id</p>";
    }
    
    // Criar um orçamento de exemplo
    $numero_orcamento = date('m/y') . '/0001';
    $stmt = $pdo->prepare("INSERT INTO orcamentos 
                          (cliente_id, numero, data_orcamento, status, status_execucao, descricao, 
                           valor_produtos, valor_total, desconto, data_validade, tempo_previsto, unidade_tempo, 
                           usuario_id, orcamentista_id) 
                          VALUES 
                          (:cliente_id, :numero, CURRENT_DATE, 'aprovado', 'pendente', 'Orçamento de exemplo para testes', 
                           220.50, 275.60, 0, CURRENT_DATE + interval '15 days', 3, 'horas', 
                           :admin_id, :orcamentista_id) 
                          RETURNING id");
    
    $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
    $stmt->bindParam(':numero', $numero_orcamento);
    $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
    $stmt->bindParam(':orcamentista_id', $orcamentista_id, PDO::PARAM_INT);
    $stmt->execute();
    $orcamento_id = $stmt->fetchColumn();
    
    // Adicionar itens ao orçamento
    // Buscar IDs dos primeiros produtos da lista
    $stmt = $pdo->query("SELECT id FROM produtos ORDER BY id LIMIT 3");
    $produto_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($produto_ids) >= 3) {
        $stmt = $pdo->prepare("INSERT INTO orcamento_itens (orcamento_id, produto_id, quantidade, valor_unitario, subtotal) 
                               VALUES (:orcamento_id, :produto_id, :quantidade, :valor_unitario, :subtotal)");
        
        // Item 1
        $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
        $stmt->bindParam(':produto_id', $produto_ids[0], PDO::PARAM_INT);
        $stmt->bindValue(':quantidade', 2);
        $stmt->bindValue(':valor_unitario', 45.90);
        $stmt->bindValue(':subtotal', 91.80);
        $stmt->execute();
        
        // Item 2
        $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
        $stmt->bindParam(':produto_id', $produto_ids[1], PDO::PARAM_INT);
        $stmt->bindValue(':quantidade', 3);
        $stmt->bindValue(':valor_unitario', 18.50);
        $stmt->bindValue(':subtotal', 55.50);
        $stmt->execute();
        
        // Item 3
        $stmt->bindParam(':orcamento_id', $orcamento_id, PDO::PARAM_INT);
        $stmt->bindParam(':produto_id', $produto_ids[2], PDO::PARAM_INT);
        $stmt->bindValue(':quantidade', 2);
        $stmt->bindValue(':valor_unitario', 35.75);
        $stmt->bindValue(':subtotal', 71.50);
        $stmt->execute();
    }
    
    echo "<p class='text-success'><i class='fas fa-check-circle me-2'></i>Orçamento exemplo #$numero_orcamento criado com ID: $orcamento_id</p>";
    
    // Confirmar as alterações
    $pdo->commit();
    
    echo "<div class='alert alert-success mt-4'>
            <i class='fas fa-check-circle me-2'></i><strong>Sucesso!</strong> Sistema limpo com sucesso. Mantido apenas o usuário admin@admin.com com senha admin123 e dados mínimos para testes.
          </div>";
    
    echo "<p>Você tem agora:</p>";
    echo "<ul>";
    echo "<li>1 cliente de exemplo</li>";
    echo "<li>2 colaboradores (1 orçamentista e 1 instalador)</li>";
    echo "<li>4 produtos de exemplo</li>";
    echo "<li>1 orçamento aprovado com 3 itens</li>";
    echo "</ul>";
    
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