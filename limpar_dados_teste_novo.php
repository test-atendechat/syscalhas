<?php
// Script simplificado para limpar dados e manter apenas usuários
require_once 'includes/config.php';
require_once 'includes/db.php';

// Verificar se o usuário está autenticado como administrador
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Processar formulário
$confirmado = false;
$mensagens = [];

if (isset($_POST['confirmar'])) {
    $confirmado = true;
    
    // Lista de tabelas a serem limpas (na ordem correta para evitar problemas de chave estrangeira)
    $tabelas = [
        'pagamentos_contas',
        'caixa',
        'estoque_movimentacoes', 
        'produtos_vendidos',
        'vendas',
        'orcamento_produtos',
        'orcamentos',
        'contas_pagar',
        'clientes',
        'produtos'
    ];

    // Tentar limpar as tabelas
    try {
        // Desativar constragentes de chave estrangeira temporariamente
        $db->exec("SET session_replication_role = 'replica'");
        
        $sucessos = 0;
        
        // Limpar cada tabela
        foreach ($tabelas as $tabela) {
            try {
                // Limpar a tabela
                $stmt = $db->prepare("DELETE FROM $tabela");
                $stmt->execute();
                $sucessos++;
            } catch (Exception $e) {
                // Exibe erro mas continua para outras tabelas
                $erro = "Erro ao limpar tabela $tabela: " . $e->getMessage();
            }
        }
        
        // Reativar constragentes
        $db->exec("SET session_replication_role = 'origin'");
        
        if ($sucessos > 0) {
            $mensagem = "$sucessos tabelas foram limpas com sucesso!";
        } else {
            $mensagem = "Nenhuma tabela pôde ser limpa. Verifique se o banco de dados está corretamente configurado.";
        }
    } catch (Exception $e) {
        $mensagem = "Erro geral: " . $e->getMessage();
    }
}

// Incluir o cabeçalho
$titulo_pagina = "Limpar Dados para Testes";
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Limpar Dados para Testes</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($mensagem)): ?>
                        <div class="alert alert-info">
                            <?php echo $mensagem; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$confirmado): ?>
                        <div class="alert alert-warning">
                            <h4 class="alert-heading"><i class="fas fa-exclamation-circle"></i> ATENÇÃO!</h4>
                            <p>Esta operação irá <strong>APAGAR PERMANENTEMENTE</strong> os seguintes dados do sistema:</p>
                            <ul>
                                <li>Todos os <strong>orçamentos</strong> e seus itens</li>
                                <li>Todas as <strong>vendas</strong> e produtos vendidos</li>
                                <li>Todos os <strong>clientes</strong> cadastrados</li>
                                <li>Todos os <strong>produtos</strong> cadastrados</li>
                                <li>Todas as <strong>movimentações de estoque</strong></li>
                                <li>Todas as <strong>movimentações de caixa</strong></li>
                                <li>Todas as <strong>contas a pagar</strong></li>
                            </ul>
                            <hr>
                            <p class="mb-0">Os <strong>usuários do sistema e suas permissões</strong> serão mantidos.</p>
                            <p class="mt-2"><strong>Esta ação NÃO pode ser desfeita!</strong></p>
                        </div>
                        
                        <form method="post" onsubmit="return confirm('Tem certeza que deseja limpar TODOS os dados? Esta ação não pode ser desfeita!')">
                            <div class="d-flex justify-content-between mt-4">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancelar e Voltar
                                </a>
                                <button type="submit" name="confirmar" value="1" class="btn btn-danger">
                                    <i class="fas fa-trash-alt"></i> Confirmar e Limpar Dados
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center mt-3">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Voltar para o Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
