<?php
// Inicialização de variáveis
$titulo = "Limpeza de Dados";
$mensagens = [];

// Incluir arquivos de configuração
require_once 'includes/config.php';
require_once 'includes/db.php';

// Verificar se o usuário está autenticado como administrador
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Processar formulário
$confirmado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar']) && $_POST['confirmar'] === 'sim') {
        $confirmado = true;
        
        // Lista de tabelas a serem limpas (na ordem correta para evitar problemas de chave estrangeira)
        $tabelas = [
            'estornos_pagamentos',
            'pagamentos_contas',
            'caixa',
            'caixa_controle',
            'estoque_movimentacoes',
            'vendas_itens',
            'vendas',
            'orcamento_itens',
            'orcamentos',
            'contas_pagar'
        ];

        // Tentar limpar as tabelas
        try {
            // Iniciar transação
            $db->beginTransaction();
            
            // Desabilitar verificação de chave estrangeira temporariamente
            $db->exec('SET CONSTRAINTS ALL DEFERRED');
            
            $tabelas_limpas = 0;
            foreach ($tabelas as $tabela) {
                try {
                    // Verificar se a tabela existe
                    $stmt = $db->query("SELECT to_regclass('public.$tabela') as exists");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['exists']) {
                        // Limpar a tabela
                        $db->exec("TRUNCATE TABLE $tabela RESTART IDENTITY CASCADE");
                        $mensagens[] = [
                            'tipo' => 'sucesso',
                            'texto' => "✅ Tabela '$tabela' limpa com sucesso."
                        ];
                        $tabelas_limpas++;
                    } else {
                        $mensagens[] = [
                            'tipo' => 'info',
                            'texto' => "ℹ️ Tabela '$tabela' não encontrada."
                        ];
                    }
                } catch (PDOException $e) {
                    $mensagens[] = [
                        'tipo' => 'erro',
                        'texto' => "❌ Erro ao limpar tabela '$tabela': " . $e->getMessage()
                    ];
                }
            }
            
            // Reabilitar verificação de chave estrangeira
            $db->exec('SET CONSTRAINTS ALL IMMEDIATE');
            
            // Confirmar transação
            $db->commit();
            
            if ($tabelas_limpas > 0) {
                $mensagens[] = [
                    'tipo' => 'sucesso',
                    'texto' => "✅ Limpeza concluída! $tabelas_limpas tabelas foram limpas com sucesso."
                ];
            } else {
                $mensagens[] = [
                    'tipo' => 'aviso',
                    'texto' => "⚠️ Nenhuma tabela foi limpa. Verifique se o banco de dados está corretamente instalado."
                ];
            }
        } catch (PDOException $e) {
            // Reverter transação em caso de erro
            $db->rollBack();
            $mensagens[] = [
                'tipo' => 'erro',
                'texto' => "❌ Erro durante a limpeza: " . $e->getMessage()
            ];
        }
    } else {
        $mensagens[] = [
            'tipo' => 'aviso',
            'texto' => "⚠️ Você precisa confirmar a limpeza marcando a caixa de seleção."
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-trash"></i> Limpeza de Dados</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($mensagens)): ?>
                            <?php foreach ($mensagens as $msg): ?>
                                <div class="alert alert-<?php 
                                    echo $msg['tipo'] === 'sucesso' ? 'success' : 
                                         ($msg['tipo'] === 'erro' ? 'danger' : 
                                         ($msg['tipo'] === 'aviso' ? 'warning' : 'info')); 
                                ?>">
                                    <?php echo $msg['texto']; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!$confirmado): ?>
                            <div class="alert alert-warning">
                                <h4><i class="bi bi-exclamation-triangle"></i> Atenção!</h4>
                                <p>Esta operação irá <strong>LIMPAR TODOS OS DADOS</strong> das seguintes tabelas:</p>
                                <ul>
                                    <li>Vendas e Itens de Vendas</li>
                                    <li>Orçamentos e Itens de Orçamentos</li>
                                    <li>Movimentações de Caixa</li>
                                    <li>Movimentações de Estoque</li>
                                    <li>Contas a Pagar e Pagamentos</li>
                                    <li>Estornos</li>
                                </ul>
                                <p><strong>Esta ação não pode ser desfeita!</strong></p>
                            </div>

                            <form method="post" class="mt-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="confirmar" value="sim" id="confirmarLimpeza">
                                    <label class="form-check-label" for="confirmarLimpeza">
                                        Eu entendo que esta ação irá limpar todos os dados operacionais do sistema e não pode ser desfeita.
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash"></i> Limpar Dados
                                </button>
                                
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Voltar
                                </a>
                            </form>
                        <?php else: ?>
                            <div class="text-center mt-3">
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="bi bi-house"></i> Voltar para o Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>