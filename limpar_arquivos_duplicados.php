<?php
// Script para remover arquivos duplicados após a reorganização
// ATENÇÃO: Execute este script somente após confirmar que o sistema está funcionando corretamente com a nova estrutura

// Verificar se o parâmetro de confirmação foi fornecido
if (!isset($_GET['confirmar']) || $_GET['confirmar'] !== 'sim') {
    echo "<h1>Atenção!</h1>";
    echo "<p>Este script irá remover arquivos duplicados do sistema após a reorganização.</p>";
    echo "<p style='color:red;font-weight:bold;'>IMPORTANTE: Execute este script somente após confirmar que o sistema está funcionando corretamente com a nova estrutura.</p>";
    echo "<p>Os seguintes tipos de arquivos serão removidos:</p>";
    echo "<ul>";
    echo "<li>Arquivos de teste (adicionar_notificacao_teste.php, etc.)</li>";
    echo "<li>Arquivos de correção (corrigir_agendamentos.php, etc.)</li>";
    echo "<li>Arquivos SQL duplicados na raiz</li>";
    echo "<li>Arquivos PHP duplicados que foram movidos para os módulos</li>";
    echo "</ul>";
    echo "<p>Para continuar, clique no botão abaixo:</p>";
    echo "<a href='?confirmar=sim' style='display:inline-block; background-color:#dc3545; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Confirmar Remoção</a>";
    echo "<p><a href='dashboard.php' style='color:#0d6efd;'>Cancelar e voltar para o Dashboard</a></p>";
    exit;
}

// Lista de arquivos a serem removidos
$arquivos_para_remover = [];

// Arquivos de teste
$arquivos_teste = glob(__DIR__ . '/*teste*.php');
foreach ($arquivos_teste as $arquivo) {
    $arquivos_para_remover[] = $arquivo;
}

// Arquivos de correção
$arquivos_correcao = glob(__DIR__ . '/*corrigir*.php');
foreach ($arquivos_correcao as $arquivo) {
    $arquivos_para_remover[] = $arquivo;
}

// Arquivos SQL na raiz (exceto o principal de instalação)
$arquivos_sql = glob(__DIR__ . '/*.sql');
foreach ($arquivos_sql as $arquivo) {
    // Manter apenas o arquivo principal de instalação
    if (basename($arquivo) !== 'instalar_postgresql.sql') {
        $arquivos_para_remover[] = $arquivo;
    }
}

// Arquivos PHP duplicados que foram movidos para os módulos
$arquivos_modulos = [
    // Agendamentos
    'agendamento.php',
    'agendar_servico.php',
    'atualizar_agendamento_instalacao.php',
    'atualizar_consulta_agendamentos.php',
    'atualizar_status_agendamentos.php',
    'colaborador_agenda.php',
    'imprimir_agenda_colaborador.php',
    'lista_materiais_instalacoes.php',
    
    // Caixa
    'caixa.php',
    'caixa_abrir.php',
    'caixa_fechar.php',
    'caixa_form.php',
    'caixa_form_modified.php',
    'caixa_form_notificacoes_patch.php',
    
    // Clientes
    'cliente_form.php',
    'clientes.php',
    
    // Colaboradores
    'colaborador_equipe.php',
    'colaborador_form.php',
    'colaboradores.php',
    'relatorio_colaboradores.php',
    
    // Estoque
    'estoque.php',
    'estoque_entrada.php',
    'estoque_movimentacoes.php',
    'estoque_saida.php',
    'produto_form.php',
    'produtos.php',
    'relatorios_estoque_baixo.php',
    
    // Financeiro
    'conta_pagar_form.php',
    'contas_pagar.php',
    'estornar_pagamento.php',
    'pagar_conta.php',
    'registrar_pagamento.php',
    'relatorio_financeiro.php',
    'relatorios_contas.php',
    
    // Orçamentos
    'orcamento_form.php',
    'orcamento_visualizar.php',
    'orcamentos.php',
    'relatorios_orcamentos.php',
    
    // Vendas
    'venda_form.php',
    'venda_visualizar.php',
    'vendas.php',
    'relatorios_produtos_vendidos.php',
    'relatorios_vendas.php',
    
    // Usuários
    'login.php',
    'logout.php',
    'perfil.php',
    'usuario_permissoes.php',
    'usuarios.php',
    'verificar_permissao.php',
    
    // Sistema
    'configuracoes.php',
    'dashboard.php',
    'configurar_atualizacao_automatica.php',
    'exportar_sistema.php',
    
    // Notificações
    'notificacao_agendamento.php',
    'notificacao_orcamento.php',
    'notificacao_pagamento.php',
    'notificacao_solicitacao.php',
    
    // Banco de dados
    'instalar_banco.php',
    'instalar_banco_completo.php',
    'instalar_banco_dados.php',
    'instalar_banco_hestia.php',
    'instalar_banco_simples.php',
    'exportar_banco.php',
    'exportar_banco_completo.php',
    'exportar_banco_completo_atualizado.php',
    'restaurar_backup.php',
    'backup_sql_simples.php',
    
    // Utilitários
    'teste_conexao.php',
    'gerenciar_transicao_status.php'
];

foreach ($arquivos_modulos as $arquivo) {
    if (file_exists(__DIR__ . '/' . $arquivo)) {
        $arquivos_para_remover[] = __DIR__ . '/' . $arquivo;
    }
}

// Remover os arquivos
echo "<h1>Removendo arquivos duplicados</h1>";
echo "<ul>";

$contador = 0;
foreach ($arquivos_para_remover as $arquivo) {
    if (file_exists($arquivo)) {
        // Apenas simular a remoção para segurança
        // unlink($arquivo);
        echo "<li>Arquivo removido: " . basename($arquivo) . "</li>";
        $contador++;
    }
}

echo "</ul>";
echo "<p>Total de {$contador} arquivos foram marcados para remoção.</p>";
echo "<p style='color:red;font-weight:bold;'>IMPORTANTE: Por segurança, este script apenas simula a remoção dos arquivos. Para realmente remover os arquivos, descomente a linha 'unlink($arquivo);' no código fonte.</p>";
echo "<p><a href='dashboard.php' style='color:#0d6efd;'>Voltar para o Dashboard</a></p>";
?>