<?php
// Script para limpar dados das tabelas mantendo os usuários
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Verificar se o usuário está logado e é admin
verificar_sessao();
if (!is_admin()) {
    set_flash_message('error', 'Você não tem permissão para executar esta operação. Apenas administradores podem limpar dados do sistema.');
    header('Location: dashboard.php');
    exit;
}

// Verificar se o formulário foi enviado e confirmado
$confirmado = isset($_POST['confirmar']) ? true : false;

// Função para executar uma query com tratamento de erro
function executar_query($pdo, $query, $descricao) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Erro ao $descricao: " . $e->getMessage() . "</div>";
        return false;
    }
}

// Limpar dados se confirmado
if ($confirmado) {
    try {
        $pdo->beginTransaction();
        
        // Lista de tabelas para limpar, na ordem correta (considerar dependências)
        $tabelas = [
            'caixa' => 'registros do caixa',
            'orcamento_produtos' => 'produtos dos orçamentos',
            'orcamentos' => 'orçamentos',
            'vendas' => 'vendas',
            'produtos_vendidos' => 'produtos vendidos',
            'estoque_movimentacoes' => 'movimentações de estoque',
            'contas_pagar' => 'contas a pagar',
            'clientes' => 'clientes',
            'produtos' => 'produtos'
        ];
        
        $sucessos = [];
        $falhas = [];
        
        // Desativar verificação de chaves estrangeiras temporariamente
        executar_query($pdo, "SET session_replication_role = 'replica';" , 'desativar verificação de chaves estrangeiras');
        
        // Limpar cada tabela
        foreach ($tabelas as $tabela => $descricao) {
            if (executar_query($pdo, "DELETE FROM $tabela", "limpar $descricao")) {
                $sucessos[] = $descricao;
            } else {
                $falhas[] = $descricao;
            }
        }
        
        // Redefinir sequências de auto incremento (specifico para PostgreSQL)
        $sequencias = [
            'caixa_id_seq',
            'orcamento_produtos_id_seq',
            'orcamentos_id_seq',
            'vendas_id_seq',
            'produtos_vendidos_id_seq',
            'estoque_movimentacoes_id_seq',
            'contas_pagar_id_seq',
            'clientes_id_seq',
            'produtos_id_seq'
        ];
        
        foreach ($sequencias as $sequencia) {
            executar_query($pdo, "ALTER SEQUENCE $sequencia RESTART WITH 1", "redefinir sequência $sequencia");
        }
        
        // Reativar verificação de chaves estrangeiras
        executar_query($pdo, "SET session_replication_role = 'origin';", 'reativar verificação de chaves estrangeiras');
        
        $pdo->commit();
        
        // Preparar mensagem de sucesso
        $mensagem_sucesso = "";
        if (count($sucessos) > 0) {
            $mensagem_sucesso .= "<p>Os seguintes dados foram limpos com sucesso:</p>";
            $mensagem_sucesso .= "<ul>";
            foreach ($sucessos as $sucesso) {
                $mensagem_sucesso .= "<li>$sucesso</li>";
            }
            $mensagem_sucesso .= "</ul>";
        }
        
        // Preparar mensagem de falha
        $mensagem_falha = "";
        if (count($falhas) > 0) {
            $mensagem_falha .= "<p>Os seguintes dados não puderam ser limpos:</p>";
            $mensagem_falha .= "<ul>";
            foreach ($falhas as $falha) {
                $mensagem_falha .= "<li>$falha</li>";
            }
            $mensagem_falha .= "</ul>";
        }
        
        set_flash_message('success', "Dados limpos com sucesso! " . $mensagem_sucesso);
        if (!empty($mensagem_falha)) {
            set_flash_message('warning', $mensagem_falha);
        }
        
        header('Location: dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash_message('error', 'Erro ao limpar dados: ' . $e->getMessage());
        header('Location: limpar_dados_teste.php');
        exit;
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
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
