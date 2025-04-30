<?php
// Inicialização de variáveis
$titulo = "Limpeza de Dados para Testes";
$mensagens = [];

// Incluir arquivos de configuração
require_once 'includes/config.php';
require_once 'includes/db.php';

// Verificar se o usuário está autenticado como administrador
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['nivel'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Processar formulário
$confirmado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar'])) {
        $confirmado = true;
        
        // Lista de tabelas a serem limpas (na ordem correta para evitar problemas de chave estrangeira)
        $tabelas = [
            'estornos_pagamentos',
            'pagamentos_contas',
            'caixa',
            'caixa_controle',
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
            // Iniciar transação
            $db->beginTransaction();
            
            // Desabilitar verificação de chave estrangeira temporariamente (PostgreSQL)
            $db->exec("SET session_replication_role = 'replica'");
            
            $tabelas_limpas = 0;
            foreach ($tabelas as $tabela) {
                try {
                    // Verificar se a tabela existe
                    $stmt = $db->query("SELECT to_regclass('public.$tabela') as exists");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result['exists']) {
                        // Limpar a tabela
                        $db->exec("DELETE FROM $tabela");
                        
                        // Redefinir sequência de ID se existir
                        try {
                            $db->exec("ALTER SEQUENCE {$tabela}_id_seq RESTART WITH 1");
                        } catch (PDOException $se) {
                            // Ignora erro se a sequência não existir
                        }
                        
                        $mensagens[] = [
                            'tipo' => 'sucesso',
                            'texto' => "\u2705 Tabela '$tabela' limpa com sucesso."
                        ];
                        $tabelas_limpas++;
                    } else {
                        $mensagens[] = [
                            'tipo' => 'info',
                            'texto' => "\u2139\ufe0f Tabela '$tabela' não encontrada."
                        ];
                    }
                } catch (PDOException $e) {
                    $mensagens[] = [
                        'tipo' => 'erro',
                        'texto' => "\u274c Erro ao limpar tabela '$tabela': " . $e->getMessage()
                    ];
                }
            }
            
            // Reabilitar verificação de chave estrangeira
            $db->exec("SET session_replication_role = 'origin'");
            
            // Confirmar transação
            $db->commit();
            
            if ($tabelas_limpas > 0) {
                $mensagens[] = [
                    'tipo' => 'sucesso',
                    'texto' => "\u2705 Limpeza concluída! $tabelas_limpas tabelas foram limpas com sucesso."
                ];
            } else {
                $mensagens[] = [
                    'tipo' => 'aviso',
                    'texto' => "\u26a0\ufe0f Nenhuma tabela foi limpa. Verifique se o banco de dados está corretamente instalado."
                ];
            }
        } catch (PDOException $e) {
            // Reverter transação em caso de erro
            $db->rollBack();
            $mensagens[] = [
                'tipo' => 'erro',
                'texto' => "\u274c Erro durante a limpeza: " . $e->getMessage()
            ];
        }
    } else {
        $mensagens[] = [
            'tipo' => 'aviso',
            'texto' => "\u26a0\ufe0f Você precisa confirmar a limpeza clicando no botão de confirmação."
        ];
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
                                <button type="submit" name="confirmar" class="btn btn-danger">
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
