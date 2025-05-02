<?php
/**
 * Script para facilitar a migração completa do sistema para a nova estrutura
 * Este script executa todas as etapas necessárias para migrar o sistema
 */

// Definir diretório raiz
$root_dir = __DIR__;

// Verificar se o parâmetro de etapa foi fornecido
$etapa = isset($_GET['etapa']) ? intval($_GET['etapa']) : 0;

// Verificar se o parâmetro de confirmação foi fornecido
$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === 'sim';

// Função para criar diretórios
function criarDiretorios() {
    global $root_dir;
    
    $diretorios = [
        $root_dir . '/modules/agendamentos',
        $root_dir . '/modules/caixa',
        $root_dir . '/modules/clientes',
        $root_dir . '/modules/colaboradores',
        $root_dir . '/modules/estoque',
        $root_dir . '/modules/financeiro',
        $root_dir . '/modules/orcamentos',
        $root_dir . '/modules/produtos',
        $root_dir . '/modules/relatorios',
        $root_dir . '/modules/sistema',
        $root_dir . '/modules/usuarios',
        $root_dir . '/modules/vendas',
        $root_dir . '/database/backups',
        $root_dir . '/database/scripts',
        $root_dir . '/utils',
        $root_dir . '/notificacoes'
    ];
    
    $resultados = [];
    
    foreach ($diretorios as $diretorio) {
        if (!is_dir($diretorio)) {
            $resultado = mkdir($diretorio, 0755, true);
            $resultados[$diretorio] = $resultado;
        } else {
            $resultados[$diretorio] = true;
        }
    }
    
    return $resultados;
}

// Função para copiar arquivos
function copiarArquivos() {
    global $root_dir;
    
    $arquivos = [
        // Agendamentos
        $root_dir . '/agendamento.php' => $root_dir . '/modules/agendamentos/agendamento.php',
        $root_dir . '/agendar_servico.php' => $root_dir . '/modules/agendamentos/agendar_servico.php',
        $root_dir . '/atualizar_agendamento_instalacao.php' => $root_dir . '/modules/agendamentos/atualizar_agendamento_instalacao.php',
        $root_dir . '/atualizar_consulta_agendamentos.php' => $root_dir . '/modules/agendamentos/atualizar_consulta_agendamentos.php',
        $root_dir . '/atualizar_status_agendamentos.php' => $root_dir . '/modules/agendamentos/atualizar_status_agendamentos.php',
        $root_dir . '/colaborador_agenda.php' => $root_dir . '/modules/agendamentos/colaborador_agenda.php',
        $root_dir . '/imprimir_agenda_colaborador.php' => $root_dir . '/modules/agendamentos/imprimir_agenda_colaborador.php',
        $root_dir . '/lista_materiais_instalacoes.php' => $root_dir . '/modules/agendamentos/lista_materiais_instalacoes.php',
        
        // Caixa
        $root_dir . '/caixa.php' => $root_dir . '/modules/caixa/caixa.php',
        $root_dir . '/caixa_abrir.php' => $root_dir . '/modules/caixa/caixa_abrir.php',
        $root_dir . '/caixa_fechar.php' => $root_dir . '/modules/caixa/caixa_fechar.php',
        $root_dir . '/caixa_form.php' => $root_dir . '/modules/caixa/caixa_form.php',
        
        // Clientes
        $root_dir . '/cliente_form.php' => $root_dir . '/modules/clientes/cliente_form.php',
        $root_dir . '/clientes.php' => $root_dir . '/modules/clientes/clientes.php',
        
        // Colaboradores
        $root_dir . '/colaborador_equipe.php' => $root_dir . '/modules/colaboradores/colaborador_equipe.php',
        $root_dir . '/colaborador_form.php' => $root_dir . '/modules/colaboradores/colaborador_form.php',
        $root_dir . '/colaboradores.php' => $root_dir . '/modules/colaboradores/colaboradores.php',
        $root_dir . '/relatorio_colaboradores.php' => $root_dir . '/modules/colaboradores/relatorio_colaboradores.php',
        
        // Estoque
        $root_dir . '/estoque.php' => $root_dir . '/modules/estoque/estoque.php',
        $root_dir . '/estoque_entrada.php' => $root_dir . '/modules/estoque/estoque_entrada.php',
        $root_dir . '/estoque_movimentacoes.php' => $root_dir . '/modules/estoque/estoque_movimentacoes.php',
        $root_dir . '/estoque_saida.php' => $root_dir . '/modules/estoque/estoque_saida.php',
        $root_dir . '/produto_form.php' => $root_dir . '/modules/estoque/produto_form.php',
        $root_dir . '/produtos.php' => $root_dir . '/modules/estoque/produtos.php',
        $root_dir . '/relatorios_estoque_baixo.php' => $root_dir . '/modules/estoque/relatorios_estoque_baixo.php',
        
        // Financeiro
        $root_dir . '/conta_pagar_form.php' => $root_dir . '/modules/financeiro/conta_pagar_form.php',
        $root_dir . '/contas_pagar.php' => $root_dir . '/modules/financeiro/contas_pagar.php',
        $root_dir . '/estornar_pagamento.php' => $root_dir . '/modules/financeiro/estornar_pagamento.php',
        $root_dir . '/pagar_conta.php' => $root_dir . '/modules/financeiro/pagar_conta.php',
        $root_dir . '/registrar_pagamento.php' => $root_dir . '/modules/financeiro/registrar_pagamento.php',
        $root_dir . '/relatorio_financeiro.php' => $root_dir . '/modules/financeiro/relatorio_financeiro.php',
        $root_dir . '/relatorios_contas.php' => $root_dir . '/modules/financeiro/relatorios_contas.php',
        
        // Orçamentos
        $root_dir . '/orcamento_form.php' => $root_dir . '/modules/orcamentos/orcamento_form.php',
        $root_dir . '/orcamento_visualizar.php' => $root_dir . '/modules/orcamentos/orcamento_visualizar.php',
        $root_dir . '/orcamentos.php' => $root_dir . '/modules/orcamentos/orcamentos.php',
        $root_dir . '/relatorios_orcamentos.php' => $root_dir . '/modules/orcamentos/relatorios_orcamentos.php',
        
        // Vendas
        $root_dir . '/venda_form.php' => $root_dir . '/modules/vendas/venda_form.php',
        $root_dir . '/venda_visualizar.php' => $root_dir . '/modules/vendas/venda_visualizar.php',
        $root_dir . '/vendas.php' => $root_dir . '/modules/vendas/vendas.php',
        $root_dir . '/relatorios_produtos_vendidos.php' => $root_dir . '/modules/vendas/relatorios_produtos_vendidos.php',
        $root_dir . '/relatorios_vendas.php' => $root_dir . '/modules/vendas/relatorios_vendas.php',
        
        // Usuários
        $root_dir . '/login.php' => $root_dir . '/modules/usuarios/login.php',
        $root_dir . '/logout.php' => $root_dir . '/modules/usuarios/logout.php',
        $root_dir . '/perfil.php' => $root_dir . '/modules/usuarios/perfil.php',
        $root_dir . '/usuario_permissoes.php' => $root_dir . '/modules/usuarios/usuario_permissoes.php',
        $root_dir . '/usuarios.php' => $root_dir . '/modules/usuarios/usuarios.php',
        $root_dir . '/verificar_permissao.php' => $root_dir . '/modules/usuarios/verificar_permissao.php',
        
        // Sistema
        $root_dir . '/configuracoes.php' => $root_dir . '/modules/sistema/configuracoes.php',
        $root_dir . '/dashboard.php' => $root_dir . '/modules/sistema/dashboard.php',
        $root_dir . '/configurar_atualizacao_automatica.php' => $root_dir . '/modules/sistema/configurar_atualizacao_automatica.php',
        $root_dir . '/exportar_sistema.php' => $root_dir . '/modules/sistema/exportar_sistema.php',
        
        // Notificações
        $root_dir . '/notificacao_agendamento.php' => $root_dir . '/notificacoes/notificacao_agendamento.php',
        $root_dir . '/notificacao_orcamento.php' => $root_dir . '/notificacoes/notificacao_orcamento.php',
        $root_dir . '/notificacao_pagamento.php' => $root_dir . '/notificacoes/notificacao_pagamento.php',
        $root_dir . '/notificacao_solicitacao.php' => $root_dir . '/notificacoes/notificacao_solicitacao.php',
        $root_dir . '/includes/notificacoes.php' => $root_dir . '/notificacoes/notificacoes.php',
        $root_dir . '/js/notificacoes.js' => $root_dir . '/notificacoes/notificacoes.js',
        $root_dir . '/ajax/obter_notificacoes.php' => $root_dir . '/notificacoes/obter_notificacoes.php',
        
        // Banco de dados
        $root_dir . '/instalar_banco.php' => $root_dir . '/database/instalar_banco.php',
        $root_dir . '/instalar_banco_completo.php' => $root_dir . '/database/instalar_banco_completo.php',
        $root_dir . '/instalar_banco_dados.php' => $root_dir . '/database/instalar_banco_dados.php',
        $root_dir . '/instalar_banco_hestia.php' => $root_dir . '/database/instalar_banco_hestia.php',
        $root_dir . '/instalar_banco_simples.php' => $root_dir . '/database/instalar_banco_simples.php',
        $root_dir . '/exportar_banco.php' => $root_dir . '/database/exportar_banco.php',
        $root_dir . '/exportar_banco_completo.php' => $root_dir . '/database/exportar_banco_completo.php',
        $root_dir . '/exportar_banco_completo_atualizado.php' => $root_dir . '/database/exportar_banco_completo_atualizado.php',
        $root_dir . '/restaurar_backup.php' => $root_dir . '/database/restaurar_backup.php',
        $root_dir . '/backup_sql_simples.php' => $root_dir . '/database/backup_sql_simples.php',
        
        // Utilitários
        $root_dir . '/teste_conexao.php' => $root_dir . '/utils/teste_conexao.php',
        $root_dir . '/gerenciar_transicao_status.php' => $root_dir . '/utils/gerenciar_transicao_status.php'
    ];
    
    // Copiar arquivos SQL para a pasta de scripts
    $arquivos_sql = glob($root_dir . '/*.sql');
    foreach ($arquivos_sql as $arquivo_sql) {
        $arquivos[$arquivo_sql] = $root_dir . '/database/scripts/' . basename($arquivo_sql);
    }
    
    // Copiar arquivos de backup para a pasta de backups
    $arquivos_backup = glob($root_dir . '/db_backup_*.sql');
    foreach ($arquivos_backup as $arquivo_backup) {
        $arquivos[$arquivo_backup] = $root_dir . '/database/backups/' . basename($arquivo_backup);
    }
    
    $resultados = [];
    
    foreach ($arquivos as $origem => $destino) {
        if (file_exists($origem)) {
            $resultado = copy($origem, $destino);
            $resultados[$origem] = $resultado;
        } else {
            $resultados[$origem] = false;
        }
    }
    
    return $resultados;
}

// Função para atualizar caminhos de inclusão
function atualizarCaminhosInclusao() {
    global $root_dir;
    
    $modulos = glob($root_dir . '/modules/*', GLOB_ONLYDIR);
    $resultados = [];
    
    foreach ($modulos as $modulo) {
        $arquivos = glob($modulo . '/*.php');
        
        foreach ($arquivos as $arquivo) {
            // Obter o conteúdo do arquivo
            $conteudo = file_get_contents($arquivo);
            
            // Verificar se o arquivo já foi processado
            if (strpos($conteudo, '__DIR__ . \'/../../includes') !== false) {
                $resultados[$arquivo] = 'já processado';
                continue;
            }
            
            // Calcular o caminho relativo do arquivo para a raiz
            $caminho_relativo = str_replace($root_dir, '', $arquivo);
            $nivel_diretorio = substr_count($caminho_relativo, '/') - 1;
            
            // Construir o caminho para o diretório includes
            $caminho_includes = '';
            for ($i = 0; $i < $nivel_diretorio; $i++) {
                $caminho_includes .= '../';
            }
            
            // Substituir os caminhos de inclusão
            $conteudo_modificado = $conteudo;
            
            // Padrões de inclusão a serem substituídos
            $padroes = [
                '/require_once\s*\(\s*[\'"]includes\/([^\'"]+)[\'"]\s*\)/' => 'require_once(__DIR__ . \'/' . $caminho_includes . 'includes/$1\')',
                '/require\s*\(\s*[\'"]includes\/([^\'"]+)[\'"]\s*\)/' => 'require(__DIR__ . \'/' . $caminho_includes . 'includes/$1\')',
                '/include_once\s*\(\s*[\'"]includes\/([^\'"]+)[\'"]\s*\)/' => 'include_once(__DIR__ . \'/' . $caminho_includes . 'includes/$1\')',
                '/include\s*\(\s*[\'"]includes\/([^\'"]+)[\'"]\s*\)/' => 'include(__DIR__ . \'/' . $caminho_includes . 'includes/$1\')',
            ];
            
            foreach ($padroes as $padrao => $substituicao) {
                $conteudo_modificado = preg_replace($padrao, $substituicao, $conteudo_modificado);
            }
            
            // Verificar se houve alterações
            if ($conteudo !== $conteudo_modificado) {
                // Salvar o arquivo modificado
                file_put_contents($arquivo, $conteudo_modificado);
                $resultados[$arquivo] = 'atualizado';
            } else {
                $resultados[$arquivo] = 'sem alterações';
            }
        }
    }
    
    return $resultados;
}

// Função para atualizar links para URLs amigáveis
function atualizarLinksUrlsAmigaveis() {
    global $root_dir;
    
    $modulos = glob($root_dir . '/modules/*', GLOB_ONLYDIR);
    $resultados = [];
    
    foreach ($modulos as $modulo) {
        $arquivos = glob($modulo . '/*.php');
        
        foreach ($arquivos as $arquivo) {
            // Obter o conteúdo do arquivo
            $conteudo = file_get_contents($arquivo);
            
            // Verificar se o arquivo já foi processado
            if (strpos($conteudo, 'CLIENTES_URL') !== false || 
                strpos($conteudo, 'ORCAMENTOS_URL') !== false || 
                strpos($conteudo, 'VENDAS_URL') !== false) {
                $resultados[$arquivo] = 'já processado';
                continue;
            }
            
            // Substituir os links
            $conteudo_modificado = $conteudo;
            
            // Padrões de links a serem substituídos
            $substituicoes = [
                // Links para páginas de clientes
                '/href\s*=\s*[\'"]clientes\.php([\'"]|[\?])/' => 'href="<?php echo CLIENTES_URL; ?>$1',
                '/href\s*=\s*[\'"]cliente_form\.php([\'"]|[\?])/' => 'href="<?php echo CLIENTES_URL; ?>/novo$1',
                
                // Links para páginas de orçamentos
                '/href\s*=\s*[\'"]orcamentos\.php([\'"]|[\?])/' => 'href="<?php echo ORCAMENTOS_URL; ?>$1',
                '/href\s*=\s*[\'"]orcamento_form\.php([\'"]|[\?])/' => 'href="<?php echo ORCAMENTOS_URL; ?>/novo$1',
                '/href\s*=\s*[\'"]orcamento_visualizar\.php\?id=([0-9]+)([\'"]|[\&])/' => 'href="<?php echo ORCAMENTOS_URL; ?>/visualizar/$1$2',
                
                // Links para páginas de vendas
                '/href\s*=\s*[\'"]vendas\.php([\'"]|[\?])/' => 'href="<?php echo VENDAS_URL; ?>$1',
                '/href\s*=\s*[\'"]venda_form\.php([\'"]|[\?])/' => 'href="<?php echo VENDAS_URL; ?>/nova$1',
                '/href\s*=\s*[\'"]venda_visualizar\.php\?id=([0-9]+)([\'"]|[\&])/' => 'href="<?php echo VENDAS_URL; ?>/visualizar/$1$2',
                
                // Links para páginas de produtos e estoque
                '/href\s*=\s*[\'"]produtos\.php([\'"]|[\?])/' => 'href="<?php echo PRODUTOS_URL; ?>$1',
                '/href\s*=\s*[\'"]produto_form\.php([\'"]|[\?])/' => 'href="<?php echo PRODUTOS_URL; ?>/novo$1',
                '/href\s*=\s*[\'"]estoque\.php([\'"]|[\?])/' => 'href="<?php echo ESTOQUE_URL; ?>$1',
                '/href\s*=\s*[\'"]estoque_entrada\.php([\'"]|[\?])/' => 'href="<?php echo ESTOQUE_URL; ?>/entrada$1',
                '/href\s*=\s*[\'"]estoque_saida\.php([\'"]|[\?])/' => 'href="<?php echo ESTOQUE_URL; ?>/saida$1',
                
                // Links para páginas de agendamentos
                '/href\s*=\s*[\'"]agendamento\.php([\'"]|[\?])/' => 'href="<?php echo AGENDAMENTOS_URL; ?>$1',
                '/href\s*=\s*[\'"]agendar_servico\.php([\'"]|[\?])/' => 'href="<?php echo AGENDAMENTOS_URL; ?>/agendar$1',
                
                // Links para páginas de colaboradores
                '/href\s*=\s*[\'"]colaboradores\.php([\'"]|[\?])/' => 'href="<?php echo COLABORADORES_URL; ?>$1',
                '/href\s*=\s*[\'"]colaborador_form\.php([\'"]|[\?])/' => 'href="<?php echo COLABORADORES_URL; ?>/novo$1',
                
                // Links para páginas de caixa e financeiro
                '/href\s*=\s*[\'"]caixa\.php([\'"]|[\?])/' => 'href="<?php echo CAIXA_URL; ?>$1',
                '/href\s*=\s*[\'"]contas_pagar\.php([\'"]|[\?])/' => 'href="<?php echo CONTAS_URL; ?>$1',
                
                // Links para páginas de relatórios
                '/href\s*=\s*[\'"]relatorios_vendas\.php([\'"]|[\?])/' => 'href="<?php echo RELATORIOS_URL; ?>/vendas$1',
                '/href\s*=\s*[\'"]relatorios_orcamentos\.php([\'"]|[\?])/' => 'href="<?php echo RELATORIOS_URL; ?>/orcamentos$1',
                '/href\s*=\s*[\'"]relatorio_financeiro\.php([\'"]|[\?])/' => 'href="<?php echo RELATORIOS_URL; ?>/financeiro$1',
                
                // Links para páginas de usuários
                '/href\s*=\s*[\'"]usuarios\.php([\'"]|[\?])/' => 'href="<?php echo USUARIOS_URL; ?>$1',
                '/href\s*=\s*[\'"]login\.php([\'"]|[\?])/' => 'href="<?php echo BASE_URL; ?>login$1',
                '/href\s*=\s*[\'"]logout\.php([\'"]|[\?])/' => 'href="<?php echo BASE_URL; ?>logout$1',
                
                // Links para páginas do sistema
                '/href\s*=\s*[\'"]dashboard\.php([\'"]|[\?])/' => 'href="<?php echo DASHBOARD_URL; ?>$1',
                '/href\s*=\s*[\'"]configuracoes\.php([\'"]|[\?])/' => 'href="<?php echo CONFIGURACOES_URL; ?>$1',
            ];
            
            foreach ($substituicoes as $padrao => $substituicao) {
                $conteudo_modificado = preg_replace($padrao, $substituicao, $conteudo_modificado);
            }
            
            // Verificar se houve alterações
            if ($conteudo !== $conteudo_modificado) {
                // Salvar o arquivo modificado
                file_put_contents($arquivo, $conteudo_modificado);
                $resultados[$arquivo] = 'atualizado';
            } else {
                $resultados[$arquivo] = 'sem alterações';
            }
        }
    }
    
    return $resultados;
}

// Função para remover arquivos duplicados
function removerArquivosDuplicados() {
    global $root_dir;
    
    $arquivos_para_remover = [];

    // Arquivos de teste
    $arquivos_teste = glob($root_dir . '/*teste*.php');
    foreach ($arquivos_teste as $arquivo) {
        $arquivos_para_remover[] = $arquivo;
    }

    // Arquivos de correção
    $arquivos_correcao = glob($root_dir . '/*corrigir*.php');
    foreach ($arquivos_correcao as $arquivo) {
        $arquivos_para_remover[] = $arquivo;
    }

    // Arquivos SQL na raiz (exceto o principal de instalação)
    $arquivos_sql = glob($root_dir . '/*.sql');
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
        if (file_exists($root_dir . '/' . $arquivo)) {
            $arquivos_para_remover[] = $root_dir . '/' . $arquivo;
        }
    }
    
    $resultados = [];
    
    foreach ($arquivos_para_remover as $arquivo) {
        if (file_exists($arquivo)) {
            // Apenas simular a remoção para segurança
            // unlink($arquivo);
            $resultados[$arquivo] = 'marcado para remoção';
        } else {
            $resultados[$arquivo] = 'não encontrado';
        }
    }
    
    return $resultados;
}

// Exibir cabeçalho
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrar Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .step-container { margin-bottom: 30px; }
        .step-header { 
            padding: 10px; 
            background-color: #f8f9fa; 
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .step-content { padding: 0 15px; }
        .step-inactive { opacity: 0.5; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Migrar Sistema para Nova Estrutura</h1>
        <p>Este script executa todas as etapas necessárias para migrar o sistema para a nova estrutura.</p>
        
        <div class="alert alert-warning">
            <strong>Atenção!</strong> Certifique-se de fazer um backup completo do sistema antes de iniciar a migração.
        </div>
        
        <div class="progress mb-4">
            <div class="progress-bar" role="progressbar" style="width: <?php echo min(100, $etapa * 20); ?>%" aria-valuenow="<?php echo min(100, $etapa * 20); ?>" aria-valuemin="0" aria-valuemax="100"><?php echo min(100, $etapa * 20); ?>%</div>
        </div>
        
        <!-- Etapa 1: Criar Diretórios -->
        <div class="step-container <?php echo $etapa < 1 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 1: Criar Diretórios</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 1): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá criar os diretórios necessários para a nova estrutura do sistema.</p>
                        <a href="?etapa=1&confirmar=sim" class="btn btn-primary">Criar Diretórios</a>
                    <?php else: ?>
                        <h3>Criando diretórios...</h3>
                        <?php
                        $resultados = criarDiretorios();
                        echo '<ul>';
                        foreach ($resultados as $diretorio => $resultado) {
                            $status = $resultado ? '<span class="success">Criado com sucesso</span>' : '<span class="error">Erro ao criar</span>';
                            echo "<li>$diretorio: $status</li>";
                        }
                        echo '</ul>';
                        ?>
                        <a href="?etapa=2" class="btn btn-primary">Próxima Etapa</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 1): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Diretórios criados com sucesso.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá criar os diretórios necessários para a nova estrutura do sistema.</p>
                    <a href="?etapa=1" class="btn btn-outline-primary">Iniciar</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 2: Copiar Arquivos -->
        <div class="step-container <?php echo $etapa < 2 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 2: Copiar Arquivos</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 2): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá copiar os arquivos para os diretórios apropriados.</p>
                        <a href="?etapa=2&confirmar=sim" class="btn btn-primary">Copiar Arquivos</a>
                    <?php else: ?>
                        <h3>Copiando arquivos...</h3>
                        <?php
                        $resultados = copiarArquivos();
                        echo '<ul>';
                        $contador = 0;
                        foreach ($resultados as $arquivo => $resultado) {
                            if ($contador >= 20) {
                                echo "<li>... e mais " . (count($resultados) - 20) . " arquivos</li>";
                                break;
                            }
                            $status = $resultado ? '<span class="success">Copiado com sucesso</span>' : '<span class="error">Erro ao copiar</span>';
                            echo "<li>" . basename($arquivo) . ": $status</li>";
                            $contador++;
                        }
                        echo '</ul>';
                        ?>
                        <a href="?etapa=3" class="btn btn-primary">Próxima Etapa</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 2): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Arquivos copiados com sucesso.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá copiar os arquivos para os diretórios apropriados.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 3: Atualizar Caminhos de Inclusão -->
        <div class="step-container <?php echo $etapa < 3 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 3: Atualizar Caminhos de Inclusão</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 3): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá atualizar os caminhos de inclusão nos arquivos PHP.</p>
                        <a href="?etapa=3&confirmar=sim" class="btn btn-primary">Atualizar Caminhos</a>
                    <?php else: ?>
                        <h3>Atualizando caminhos de inclusão...</h3>
                        <?php
                        $resultados = atualizarCaminhosInclusao();
                        echo '<ul>';
                        $contador = 0;
                        foreach ($resultados as $arquivo => $resultado) {
                            if ($contador >= 20) {
                                echo "<li>... e mais " . (count($resultados) - 20) . " arquivos</li>";
                                break;
                            }
                            echo "<li>" . basename($arquivo) . ": <span class='success'>$resultado</span></li>";
                            $contador++;
                        }
                        echo '</ul>';
                        ?>
                        <a href="?etapa=4" class="btn btn-primary">Próxima Etapa</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 3): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Caminhos de inclusão atualizados com sucesso.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá atualizar os caminhos de inclusão nos arquivos PHP.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 4: Atualizar Links para URLs Amigáveis -->
        <div class="step-container <?php echo $etapa < 4 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 4: Atualizar Links para URLs Amigáveis</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 4): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá atualizar os links nos arquivos PHP para usar as URLs amigáveis.</p>
                        <a href="?etapa=4&confirmar=sim" class="btn btn-primary">Atualizar Links</a>
                    <?php else: ?>
                        <h3>Atualizando links para URLs amigáveis...</h3>
                        <?php
                        $resultados = atualizarLinksUrlsAmigaveis();
                        echo '<ul>';
                        $contador = 0;
                        foreach ($resultados as $arquivo => $resultado) {
                            if ($contador >= 20) {
                                echo "<li>... e mais " . (count($resultados) - 20) . " arquivos</li>";
                                break;
                            }
                            echo "<li>" . basename($arquivo) . ": <span class='success'>$resultado</span></li>";
                            $contador++;
                        }
                        echo '</ul>';
                        ?>
                        <a href="?etapa=5" class="btn btn-primary">Próxima Etapa</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 4): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Links atualizados com sucesso.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá atualizar os links nos arquivos PHP para usar as URLs amigáveis.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 5: Remover Arquivos Duplicados -->
        <div class="step-container <?php echo $etapa < 5 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 5: Remover Arquivos Duplicados</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 5): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá remover os arquivos duplicados do sistema.</p>
                        <div class="alert alert-danger">
                            <strong>Atenção!</strong> Esta etapa é irreversível. Certifique-se de que o sistema está funcionando corretamente com a nova estrutura antes de continuar.
                        </div>
                        <a href="?etapa=5&confirmar=sim" class="btn btn-danger">Remover Arquivos Duplicados</a>
                    <?php else: ?>
                        <h3>Removendo arquivos duplicados...</h3>
                        <?php
                        $resultados = removerArquivosDuplicados();
                        echo '<ul>';
                        $contador = 0;
                        foreach ($resultados as $arquivo => $resultado) {
                            if ($contador >= 20) {
                                echo "<li>... e mais " . (count($resultados) - 20) . " arquivos</li>";
                                break;
                            }
                            echo "<li>" . basename($arquivo) . ": <span class='warning'>$resultado</span></li>";
                            $contador++;
                        }
                        echo '</ul>';
                        ?>
                        <div class="alert alert-warning">
                            <strong>Atenção!</strong> Por segurança, os arquivos foram apenas marcados para remoção. Para realmente remover os arquivos, edite o script e descomente a linha 'unlink($arquivo);' na função removerArquivosDuplicados().
                        </div>
                        <a href="?etapa=6" class="btn btn-primary">Concluir Migração</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 5): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Arquivos duplicados marcados para remoção.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá remover os arquivos duplicados do sistema.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 6: Concluir Migração -->
        <div class="step-container <?php echo $etapa < 6 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 6: Concluir Migração</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 6): ?>
                    <div class="alert alert-success">
                        <h3><i class="fas fa-check-circle"></i> Migração Concluída!</h3>
                        <p>O sistema foi migrado com sucesso para a nova estrutura.</p>
                    </div>
                    <p>Agora você pode:</p>
                    <ul>
                        <li>Verificar se o sistema está funcionando corretamente com a nova estrutura</li>
                        <li>Testar as URLs amigáveis</li>
                        <li>Remover os arquivos de migração (este script, verificar_reorganizacao.php, etc.)</li>
                    </ul>
                    <a href="verificar_compatibilidade_servidor.php" class="btn btn-primary">Verificar Compatibilidade do Servidor</a>
                    <a href="testar_urls_amigaveis.php" class="btn btn-primary">Testar URLs Amigáveis</a>
                    <a href="dashboard" class="btn btn-success">Ir para o Dashboard</a>
                <?php else: ?>
                    <p>Esta etapa irá concluir a migração do sistema.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html><?php
/**
 * Script para facilitar a migração completa do sistema para a nova estrutura
 * Este script executa todas as etapas necessárias para migrar o sistema
 */

// Definir diretório raiz
$root_dir = __DIR__;

// Verificar se o parâmetro de etapa foi fornecido
$etapa = isset($_GET['etapa']) ? intval($_GET['etapa']) : 0;

// Verificar se o parâmetro de confirmação foi fornecido
$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === 'sim';

// Função para criar diretórios
function criarDiretorios() {
    global $root_dir;
    
    $diretorios = [
        $root_dir . '/modules/agendamentos',
        $root_dir . '/modules/caixa',
        $root_dir . '/modules/clientes',
        $root_dir . '/modules/colaboradores',
        $root_dir . '/modules/estoque',
        $root_dir . '/modules/financeiro',
        $root_dir . '/modules/orcamentos',
        $root_dir . '/modules/produtos',
        $root_dir . '/modules/relatorios',
        $root_dir . '/modules/sistema',
        $root_dir . '/modules/usuarios',
        $root_dir . '/modules/vendas',
        $root_dir . '/database/backups',
        $root_dir . '/database/scripts',
        $root_dir . '/utils',
        $root_dir . '/notificacoes'
    ];
    
    $resultados = [];
    
    foreach ($diretorios as $diretorio) {
        if (!is_dir($diretorio)) {
            $resultado = mkdir($diretorio, 0755, true);
            $resultados[$diretorio] = $resultado;
        } else {
            $resultados[$diretorio] = true;
        }
    }
    
    return $resultados;
}

// Função para copiar arquivos
function copiarArquivos() {
    global $root_dir;
    
    $arquivos = [
        // Agendamentos
        $root_dir . '/agendamento.php' => $root_dir . '/modules/agendamentos/agendamento.php',
        $root_dir . '/agendar_servico.php' => $root_dir . '/modules/agendamentos/agendar_servico.php',
        $root_dir . '/atualizar_agendamento_instalacao.php' => $root_dir . '/modules/agendamentos/atualizar_agendamento_instalacao.php',
        $root_dir . '/atualizar_consulta_agendamentos.php' => $root_dir . '/modules/agendamentos/atualizar_consulta_agendamentos.php',
        $root_dir . '/atualizar_status_agendamentos.php' => $root_dir . '/modules/agendamentos/atualizar_status_agendamentos.php',
        $root_dir . '/colaborador_agenda.php' => $root_dir . '/modules/agendamentos/colaborador_agenda.php',
        $root_dir . '/imprimir_agenda_colaborador.php' => $root_dir . '/modules/agendamentos/imprimir_agenda_colaborador.php',
        $root_dir . '/lista_materiais_instalacoes.php' => $root_dir . '/modules/agendamentos/lista_materiais_instalacoes.php',
        
        // Caixa
        $root_dir . '/caixa.php' => $root_dir . '/modules/caixa/caixa.php',
        $root_dir . '/caixa_abrir.php' => $root_dir . '/modules/caixa/caixa_abrir.php',
        $root_dir . '/caixa_fechar.php' => $root_dir . '/modules/caixa/caixa_fechar.php',
        $root_dir . '/caixa_form.php' => $root_dir . '/modules/caixa/caixa_form.php',
        
        // Clientes
        $root_dir . '/cliente_form.php' => $root_dir . '/modules/clientes/cliente_form.php',
        $root_dir . '/clientes.php' => $root_dir . '/modules/clientes/clientes.php',
        
        // Colaboradores
        $root_dir . '/colaborador_equipe.php' => $root_dir . '/modules/colaboradores/colaborador_equipe.php',
        $root_dir . '/colaborador_form.php' => $root_dir . '/modules/colaboradores/colaborador_form.php',
        $root_dir . '/colaboradores.php' => $root_dir . '/modules/colaboradores/colaboradores.php',
        $root_dir . '/relatorio_colaboradores.php' => $root_dir . '/modules/colaboradores/relatorio_colaboradores.php',
        
        // Estoque
        $root_dir . '/estoque.php' => $root_dir . '/modules/estoque/estoque.php',
        $root_dir . '/estoque_entrada.php' => $root_dir . '/modules/estoque/estoque_entrada.php',
        $root_dir . '/estoque_movimentacoes.php' => $root_dir . '/modules/estoque/estoque_movimentacoes.php',
        $root_dir . '/estoque_saida.php' => $root_dir . '/modules/estoque/estoque_saida.php',
        $root_dir . '/produto_form.php' => $root_dir . '/modules/estoque/produto_form.php',
        $root_dir . '/produtos.php' => $root_dir . '/modules/estoque/produtos.php',
        $root_dir . '/relatorios_estoque_baixo.php' => $root_dir . '/modules/estoque/relatorios_estoque_baixo.php',
        
        // Financeiro
        $root_dir . '/conta_pagar_form.php' => $root_dir . '/modules/financeiro/conta_pagar_form.php',
        $root_dir . '/contas_pagar.php' => $root_dir . '/modules/financeiro/contas_pagar.php',
        $root_dir . '/estornar_pagamento.php' => $root_dir . '/modules/financeiro/estornar_pagamento.php',
        $root_dir . '/pagar_conta.php' => $root_dir . '/modules/financeiro/pagar_conta.php',
        $root_dir . '/registrar_pagamento.php' => $root_dir . '/modules/financeiro/registrar_pagamento.php',
        $root_dir . '/relatorio_financeiro.php' => $root_dir . '/modules/financeiro/relatorio_financeiro.php',
        $root_dir . '/relatorios_contas.php' => $root_dir . '/modules/financeiro/relatorios_contas.php',
        
        // Orçamentos
        $root_dir . '/orcamento_form.php' => $root_dir . '/modules/orcamentos/orcamento_form.php',
        $root_dir . '/orcamento_visualizar.php' => $root_dir . '/modules/orcamentos/orcamento_visualizar.php',
        $root_dir . '/orcamentos.php' => $root_dir . '/modules/orcamentos/orcamentos.php',
        $root_dir . '/relatorios_orcamentos.php' => $root_dir . '/modules/orcamentos/relatorios_orcamentos.php',
        
        // Vendas
        $root_dir . '/venda_form.php' => $root_dir . '/modules/vendas/venda_form.php',
        $root_dir . '/venda_visualizar.php' => $root_dir . '/modules/vendas/venda_visualizar.php',
        $root_dir . '/vendas.php' => $root_dir . '/modules/vendas/vendas.php',
        $root_dir . '/relatorios_produtos_vendidos.php' => $root_dir . '/modules/vendas/relatorios_produtos_vendidos.php',
        $root_dir . '/relatorios_vendas.php' => $root_dir . '/modules/vendas/relatorios_vendas.php',
        
        // Usuários
        $root_dir . '/login.php' => $root_dir . '/modules/usuarios/login.php',
        $root_dir . '/logout.php' => $root_dir . '/modules/usuarios/logout.php',
        $root_dir . '/perfil.php' => $root_dir . '/modules/usuarios/perfil.php',
        $root_dir . '/usuario_permissoes.php' => $root_dir . '/modules/usuarios/usuario_permissoes.php',
        $root_dir . '/usuarios.php' => $root_dir . '/modules/usuarios/usuarios.php',
        $root_dir . '/verificar_permissao.php' => $root_dir . '/modules/usuarios/verificar_permissao.php',
        
        // Sistema
        $root_dir . '/configuracoes.php' => $root_dir . '/modules/sistema/configuracoes.php',
        $root_dir . '/dashboard.php' => $root_dir . '/modules/sistema/dashboard.php',
        $root_dir . '/configurar_atualizacao_automatica.php' => $root_dir . '/modules/sistema/configurar_atualizacao_automatica.php',
        $root_dir . '/exportar_sistema.php' => $root_dir . '/modules/sistema/exportar_sistema.php',
        
        // Notificações
        $root_dir . '/notificacao_agendamento.php' => $root_dir . '/notificacoes/notificacao_agendamento.php',
        $root_dir . '/notificacao_orcamento.php' => $root_dir . '/notificacoes/notificacao_orcamento.php',
        $root_dir . '/notificacao_pagamento.php' => $root_dir . '/notificacoes/notificacao_pagamento.php',
        $root_dir . '/notificacao_solicitacao.php' => $root_dir . '/notificacoes/notificacao_solicitacao.php',
        $root_dir . '/includes/notificacoes.php' => $root_dir . '/notificacoes/notificacoes.php',
        $root_dir . '/js/notificacoes.js' => $root_dir . '/notificacoes/notificacoes.js',
        $root_dir . '/ajax/obter_notificacoes.php' => $root_dir . '/notificacoes/obter_notificacoes.php',
        
        // Banco de dados
        $root_dir . '/instalar_banco.php' => $root_dir . '/database/instalar_banco.php',
        $root_dir . '/instalar_banco_completo.php' => $root_dir . '/database/instalar_banco_completo.php',
        $root_dir . '/instalar_banco_dados.php' => $root_dir . '/database/instalar_banco_dados.php',
        $root_dir . '/instalar_banco_hestia.php' => $root_dir . '/database/instalar_banco_hestia.php',
        $root_dir . '/instalar_banco_simples.php' => $root_dir . '/database/instalar_banco_simples.php',
        $root_dir . '/exportar_banco.php' => $root_dir . '/database/exportar_banco.php',
        $root_dir . '/exportar_banco_completo.php' => $root_dir . '/database/exportar_banco_completo.php',
        $root_dir . '/exportar_banco_completo_atualizado.php' => $root_dir . '/database/exportar_banco_completo_atualizado.php',
        $root_dir . '/restaurar_backup.php' => $root_dir . '/database/restaurar_backup.php',
        $root_dir . '/backup_sql_simples.php' => $root_dir . '/database/backup_sql_simples.php',
        
        // Utilitários
        $root_dir . '/teste_conexao.php' => $root_dir . '/utils/teste_conexao.php',
        $root_dir . '/gerenciar_transicao_status.php' => $root_dir . '/utils/gerenciar_transicao_status.php'
    ];
    
    // Copiar arquivos SQL para a pasta de scripts
    $arquivos_sql = glob($root_dir . '/*.sql');
    foreach ($arquivos_sql as $arquivo_sql) {
        $arquivos[$arquivo_sql] = $root_dir . '/database/scripts/' . basename($arquivo_sql);
    }
    
    // Copiar arquivos de backup para a pasta de backups
    $arquivos_backup = glob($root_dir . '/db_backup_*.sql');
    foreach ($arquivos_backup as $arquivo_backup) {
        $arquivos[$arquivo_backup] = $root_dir . '/database/backups/' . basename($arquivo_backup);
    }
    
    $resultados = [];
    
    foreach ($arquivos as $origem => $destino) {
        if (file_exists($origem)) {
            $resultado = copy($origem, $destino);
            $resultados[$origem] = $resultado;
        } else {
            $resultados[$origem] = false;
        }
    }
    
    return $resultados;
}

// Função para atualizar caminhos de inclusão
function atualizarCaminhosInclusao() {
    global $root_dir;
    
    $modulos = glob($root_dir . '/modules/*', GLOB_ONLYDIR);
    $resultados = [];
    
    foreach ($modulos as $modulo) {
        $arquivos = glob($modulo . '/*.php');
        
        foreach ($arquivos as $arquivo) {
            // Obter o conteúdo do arquivo
            $conteudo = file_get_contents($arquivo);
            
            // Verificar se o arquivo já foi processado
            if (strpos($conteudo, '__DIR__ . \'/../../includes') !== false) {
                $resultados[$arquivo] = 'já processado';
                continue;
            }
            
            // Calcular o caminho relativo do arquivo para a raiz
            $caminho_relativo = str_replace($root_dir, '', $arquivo);
            $nivel_diretorio = substr_count($caminho_relativo, '/') - 1;
            
            // Construir o caminho para o diretório includes
            $caminho_includes = '';
            for ($i = 0; $i < $nivel_diretorio; $i++) {
                $caminho_includes .= '../';
            }
            
            // Substituir os caminhos de inclusão
            $conteudo_modificado = $conteudo;
            
            // Padrões de inclusão a serem substituídos
            $padroes = [
                '/require_once\s*\(\s*[\'"]includes\/([^\'"]+)[\'"]\s*\)/' => 'require_once(__DIR__ . \'/' . $caminho_includes . 'includes/$1\')',
                '/require\s*\(\s*[\'"]includes\/([^\'"]+)[\'"]\s*\)/' => 'require(__DIR__ . \'/' . $caminho_includes . 'includes/$1\')',
                '/include_once\s*\(\s*[\'"]includes\/([^\'"]+)[\'"]\s*\)/' => 'include_once(__DIR__ . \'/' . $caminho_includes . 'includes/$1\')',
                '/include\s*\(\s*[\'"]includes\/([^\'"]+)[\'"]\s*\)/' => 'include(__DIR__ . \'/' . $caminho_includes . 'includes/$1\')',
            ];
            
            foreach ($padroes as $padrao => $substituicao) {
                $conteudo_modificado = preg_replace($padrao, $substituicao, $conteudo_modificado);
            }
            
            // Verificar se houve alterações
            if ($conteudo !== $conteudo_modificado) {
                // Salvar o arquivo modificado
                file_put_contents($arquivo, $conteudo_modificado);
                $resultados[$arquivo] = 'atualizado';
            } else {
                $resultados[$arquivo] = 'sem alterações';
            }
        }
    }
    
    return $resultados;
}

// Função para atualizar links para URLs amigáveis
function atualizarLinksUrlsAmigaveis() {
    global $root_dir;
    
    $modulos = glob($root_dir . '/modules/*', GLOB_ONLYDIR);
    $resultados = [];
    
    foreach ($modulos as $modulo) {
        $arquivos = glob($modulo . '/*.php');
        
        foreach ($arquivos as $arquivo) {
            // Obter o conteúdo do arquivo
            $conteudo = file_get_contents($arquivo);
            
            // Verificar se o arquivo já foi processado
            if (strpos($conteudo, 'CLIENTES_URL') !== false || 
                strpos($conteudo, 'ORCAMENTOS_URL') !== false || 
                strpos($conteudo, 'VENDAS_URL') !== false) {
                $resultados[$arquivo] = 'já processado';
                continue;
            }
            
            // Substituir os links
            $conteudo_modificado = $conteudo;
            
            // Padrões de links a serem substituídos
            $substituicoes = [
                // Links para páginas de clientes
                '/href\s*=\s*[\'"]clientes\.php([\'"]|[\?])/' => 'href="<?php echo CLIENTES_URL; ?>$1',
                '/href\s*=\s*[\'"]cliente_form\.php([\'"]|[\?])/' => 'href="<?php echo CLIENTES_URL; ?>/novo$1',
                
                // Links para páginas de orçamentos
                '/href\s*=\s*[\'"]orcamentos\.php([\'"]|[\?])/' => 'href="<?php echo ORCAMENTOS_URL; ?>$1',
                '/href\s*=\s*[\'"]orcamento_form\.php([\'"]|[\?])/' => 'href="<?php echo ORCAMENTOS_URL; ?>/novo$1',
                '/href\s*=\s*[\'"]orcamento_visualizar\.php\?id=([0-9]+)([\'"]|[\&])/' => 'href="<?php echo ORCAMENTOS_URL; ?>/visualizar/$1$2',
                
                // Links para páginas de vendas
                '/href\s*=\s*[\'"]vendas\.php([\'"]|[\?])/' => 'href="<?php echo VENDAS_URL; ?>$1',
                '/href\s*=\s*[\'"]venda_form\.php([\'"]|[\?])/' => 'href="<?php echo VENDAS_URL; ?>/nova$1',
                '/href\s*=\s*[\'"]venda_visualizar\.php\?id=([0-9]+)([\'"]|[\&])/' => 'href="<?php echo VENDAS_URL; ?>/visualizar/$1$2',
                
                // Links para páginas de produtos e estoque
                '/href\s*=\s*[\'"]produtos\.php([\'"]|[\?])/' => 'href="<?php echo PRODUTOS_URL; ?>$1',
                '/href\s*=\s*[\'"]produto_form\.php([\'"]|[\?])/' => 'href="<?php echo PRODUTOS_URL; ?>/novo$1',
                '/href\s*=\s*[\'"]estoque\.php([\'"]|[\?])/' => 'href="<?php echo ESTOQUE_URL; ?>$1',
                '/href\s*=\s*[\'"]estoque_entrada\.php([\'"]|[\?])/' => 'href="<?php echo ESTOQUE_URL; ?>/entrada$1',
                '/href\s*=\s*[\'"]estoque_saida\.php([\'"]|[\?])/' => 'href="<?php echo ESTOQUE_URL; ?>/saida$1',
                
                // Links para páginas de agendamentos
                '/href\s*=\s*[\'"]agendamento\.php([\'"]|[\?])/' => 'href="<?php echo AGENDAMENTOS_URL; ?>$1',
                '/href\s*=\s*[\'"]agendar_servico\.php([\'"]|[\?])/' => 'href="<?php echo AGENDAMENTOS_URL; ?>/agendar$1',
                
                // Links para páginas de colaboradores
                '/href\s*=\s*[\'"]colaboradores\.php([\'"]|[\?])/' => 'href="<?php echo COLABORADORES_URL; ?>$1',
                '/href\s*=\s*[\'"]colaborador_form\.php([\'"]|[\?])/' => 'href="<?php echo COLABORADORES_URL; ?>/novo$1',
                
                // Links para páginas de caixa e financeiro
                '/href\s*=\s*[\'"]caixa\.php([\'"]|[\?])/' => 'href="<?php echo CAIXA_URL; ?>$1',
                '/href\s*=\s*[\'"]contas_pagar\.php([\'"]|[\?])/' => 'href="<?php echo CONTAS_URL; ?>$1',
                
                // Links para páginas de relatórios
                '/href\s*=\s*[\'"]relatorios_vendas\.php([\'"]|[\?])/' => 'href="<?php echo RELATORIOS_URL; ?>/vendas$1',
                '/href\s*=\s*[\'"]relatorios_orcamentos\.php([\'"]|[\?])/' => 'href="<?php echo RELATORIOS_URL; ?>/orcamentos$1',
                '/href\s*=\s*[\'"]relatorio_financeiro\.php([\'"]|[\?])/' => 'href="<?php echo RELATORIOS_URL; ?>/financeiro$1',
                
                // Links para páginas de usuários
                '/href\s*=\s*[\'"]usuarios\.php([\'"]|[\?])/' => 'href="<?php echo USUARIOS_URL; ?>$1',
                '/href\s*=\s*[\'"]login\.php([\'"]|[\?])/' => 'href="<?php echo BASE_URL; ?>login$1',
                '/href\s*=\s*[\'"]logout\.php([\'"]|[\?])/' => 'href="<?php echo BASE_URL; ?>logout$1',
                
                // Links para páginas do sistema
                '/href\s*=\s*[\'"]dashboard\.php([\'"]|[\?])/' => 'href="<?php echo DASHBOARD_URL; ?>$1',
                '/href\s*=\s*[\'"]configuracoes\.php([\'"]|[\?])/' => 'href="<?php echo CONFIGURACOES_URL; ?>$1',
            ];
            
            foreach ($substituicoes as $padrao => $substituicao) {
                $conteudo_modificado = preg_replace($padrao, $substituicao, $conteudo_modificado);
            }
            
            // Verificar se houve alterações
            if ($conteudo !== $conteudo_modificado) {
                // Salvar o arquivo modificado
                file_put_contents($arquivo, $conteudo_modificado);
                $resultados[$arquivo] = 'atualizado';
            } else {
                $resultados[$arquivo] = 'sem alterações';
            }
        }
    }
    
    return $resultados;
}

// Função para remover arquivos duplicados
function removerArquivosDuplicados() {
    global $root_dir;
    
    $arquivos_para_remover = [];

    // Arquivos de teste
    $arquivos_teste = glob($root_dir . '/*teste*.php');
    foreach ($arquivos_teste as $arquivo) {
        $arquivos_para_remover[] = $arquivo;
    }

    // Arquivos de correção
    $arquivos_correcao = glob($root_dir . '/*corrigir*.php');
    foreach ($arquivos_correcao as $arquivo) {
        $arquivos_para_remover[] = $arquivo;
    }

    // Arquivos SQL na raiz (exceto o principal de instalação)
    $arquivos_sql = glob($root_dir . '/*.sql');
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
        if (file_exists($root_dir . '/' . $arquivo)) {
            $arquivos_para_remover[] = $root_dir . '/' . $arquivo;
        }
    }
    
    $resultados = [];
    
    foreach ($arquivos_para_remover as $arquivo) {
        if (file_exists($arquivo)) {
            // Apenas simular a remoção para segurança
            // unlink($arquivo);
            $resultados[$arquivo] = 'marcado para remoção';
        } else {
            $resultados[$arquivo] = 'não encontrado';
        }
    }
    
    return $resultados;
}

// Exibir cabeçalho
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrar Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .step-container { margin-bottom: 30px; }
        .step-header { 
            padding: 10px; 
            background-color: #f8f9fa; 
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .step-content { padding: 0 15px; }
        .step-inactive { opacity: 0.5; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Migrar Sistema para Nova Estrutura</h1>
        <p>Este script executa todas as etapas necessárias para migrar o sistema para a nova estrutura.</p>
        
        <div class="alert alert-warning">
            <strong>Atenção!</strong> Certifique-se de fazer um backup completo do sistema antes de iniciar a migração.
        </div>
        
        <div class="progress mb-4">
            <div class="progress-bar" role="progressbar" style="width: <?php echo min(100, $etapa * 20); ?>%" aria-valuenow="<?php echo min(100, $etapa * 20); ?>" aria-valuemin="0" aria-valuemax="100"><?php echo min(100, $etapa * 20); ?>%</div>
        </div>
        
        <!-- Etapa 1: Criar Diretórios -->
        <div class="step-container <?php echo $etapa < 1 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 1: Criar Diretórios</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 1): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá criar os diretórios necessários para a nova estrutura do sistema.</p>
                        <a href="?etapa=1&confirmar=sim" class="btn btn-primary">Criar Diretórios</a>
                    <?php else: ?>
                        <h3>Criando diretórios...</h3>
                        <?php
                        $resultados = criarDiretorios();
                        echo '<ul>';
                        foreach ($resultados as $diretorio => $resultado) {
                            $status = $resultado ? '<span class="success">Criado com sucesso</span>' : '<span class="error">Erro ao criar</span>';
                            echo "<li>$diretorio: $status</li>";
                        }
                        echo '</ul>';
                        ?>
                        <a href="?etapa=2" class="btn btn-primary">Próxima Etapa</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 1): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Diretórios criados com sucesso.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá criar os diretórios necessários para a nova estrutura do sistema.</p>
                    <a href="?etapa=1" class="btn btn-outline-primary">Iniciar</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 2: Copiar Arquivos -->
        <div class="step-container <?php echo $etapa < 2 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 2: Copiar Arquivos</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 2): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá copiar os arquivos para os diretórios apropriados.</p>
                        <a href="?etapa=2&confirmar=sim" class="btn btn-primary">Copiar Arquivos</a>
                    <?php else: ?>
                        <h3>Copiando arquivos...</h3>
                        <?php
                        $resultados = copiarArquivos();
                        echo '<ul>';
                        $contador = 0;
                        foreach ($resultados as $arquivo => $resultado) {
                            if ($contador >= 20) {
                                echo "<li>... e mais " . (count($resultados) - 20) . " arquivos</li>";
                                break;
                            }
                            $status = $resultado ? '<span class="success">Copiado com sucesso</span>' : '<span class="error">Erro ao copiar</span>';
                            echo "<li>" . basename($arquivo) . ": $status</li>";
                            $contador++;
                        }
                        echo '</ul>';
                        ?>
                        <a href="?etapa=3" class="btn btn-primary">Próxima Etapa</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 2): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Arquivos copiados com sucesso.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá copiar os arquivos para os diretórios apropriados.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 3: Atualizar Caminhos de Inclusão -->
        <div class="step-container <?php echo $etapa < 3 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 3: Atualizar Caminhos de Inclusão</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 3): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá atualizar os caminhos de inclusão nos arquivos PHP.</p>
                        <a href="?etapa=3&confirmar=sim" class="btn btn-primary">Atualizar Caminhos</a>
                    <?php else: ?>
                        <h3>Atualizando caminhos de inclusão...</h3>
                        <?php
                        $resultados = atualizarCaminhosInclusao();
                        echo '<ul>';
                        $contador = 0;
                        foreach ($resultados as $arquivo => $resultado) {
                            if ($contador >= 20) {
                                echo "<li>... e mais " . (count($resultados) - 20) . " arquivos</li>";
                                break;
                            }
                            echo "<li>" . basename($arquivo) . ": <span class='success'>$resultado</span></li>";
                            $contador++;
                        }
                        echo '</ul>';
                        ?>
                        <a href="?etapa=4" class="btn btn-primary">Próxima Etapa</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 3): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Caminhos de inclusão atualizados com sucesso.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá atualizar os caminhos de inclusão nos arquivos PHP.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 4: Atualizar Links para URLs Amigáveis -->
        <div class="step-container <?php echo $etapa < 4 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 4: Atualizar Links para URLs Amigáveis</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 4): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá atualizar os links nos arquivos PHP para usar as URLs amigáveis.</p>
                        <a href="?etapa=4&confirmar=sim" class="btn btn-primary">Atualizar Links</a>
                    <?php else: ?>
                        <h3>Atualizando links para URLs amigáveis...</h3>
                        <?php
                        $resultados = atualizarLinksUrlsAmigaveis();
                        echo '<ul>';
                        $contador = 0;
                        foreach ($resultados as $arquivo => $resultado) {
                            if ($contador >= 20) {
                                echo "<li>... e mais " . (count($resultados) - 20) . " arquivos</li>";
                                break;
                            }
                            echo "<li>" . basename($arquivo) . ": <span class='success'>$resultado</span></li>";
                            $contador++;
                        }
                        echo '</ul>';
                        ?>
                        <a href="?etapa=5" class="btn btn-primary">Próxima Etapa</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 4): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Links atualizados com sucesso.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá atualizar os links nos arquivos PHP para usar as URLs amigáveis.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 5: Remover Arquivos Duplicados -->
        <div class="step-container <?php echo $etapa < 5 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 5: Remover Arquivos Duplicados</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 5): ?>
                    <?php if (!$confirmar): ?>
                        <p>Esta etapa irá remover os arquivos duplicados do sistema.</p>
                        <div class="alert alert-danger">
                            <strong>Atenção!</strong> Esta etapa é irreversível. Certifique-se de que o sistema está funcionando corretamente com a nova estrutura antes de continuar.
                        </div>
                        <a href="?etapa=5&confirmar=sim" class="btn btn-danger">Remover Arquivos Duplicados</a>
                    <?php else: ?>
                        <h3>Removendo arquivos duplicados...</h3>
                        <?php
                        $resultados = removerArquivosDuplicados();
                        echo '<ul>';
                        $contador = 0;
                        foreach ($resultados as $arquivo => $resultado) {
                            if ($contador >= 20) {
                                echo "<li>... e mais " . (count($resultados) - 20) . " arquivos</li>";
                                break;
                            }
                            echo "<li>" . basename($arquivo) . ": <span class='warning'>$resultado</span></li>";
                            $contador++;
                        }
                        echo '</ul>';
                        ?>
                        <div class="alert alert-warning">
                            <strong>Atenção!</strong> Por segurança, os arquivos foram apenas marcados para remoção. Para realmente remover os arquivos, edite o script e descomente a linha 'unlink($arquivo);' na função removerArquivosDuplicados().
                        </div>
                        <a href="?etapa=6" class="btn btn-primary">Concluir Migração</a>
                    <?php endif; ?>
                <?php elseif ($etapa > 5): ?>
                    <p><span class="success"><i class="fas fa-check"></i> Arquivos duplicados marcados para remoção.</span></p>
                <?php else: ?>
                    <p>Esta etapa irá remover os arquivos duplicados do sistema.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Etapa 6: Concluir Migração -->
        <div class="step-container <?php echo $etapa < 6 ? 'step-inactive' : ''; ?>">
            <div class="step-header">
                <h2>Etapa 6: Concluir Migração</h2>
            </div>
            <div class="step-content">
                <?php if ($etapa == 6): ?>
                    <div class="alert alert-success">
                        <h3><i class="fas fa-check-circle"></i> Migração Concluída!</h3>
                        <p>O sistema foi migrado com sucesso para a nova estrutura.</p>
                    </div>
                    <p>Agora você pode:</p>
                    <ul>
                        <li>Verificar se o sistema está funcionando corretamente com a nova estrutura</li>
                        <li>Testar as URLs amigáveis</li>
                        <li>Remover os arquivos de migração (este script, verificar_reorganizacao.php, etc.)</li>
                    </ul>
                    <a href="verificar_compatibilidade_servidor.php" class="btn btn-primary">Verificar Compatibilidade do Servidor</a>
                    <a href="testar_urls_amigaveis.php" class="btn btn-primary">Testar URLs Amigáveis</a>
                    <a href="dashboard" class="btn btn-success">Ir para o Dashboard</a>
                <?php else: ?>
                    <p>Esta etapa irá concluir a migração do sistema.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>