<?php
/**
 * Script para atualizar automaticamente os links nos arquivos PHP para usar as URLs amigáveis
 * Este script substitui os links diretos por constantes de URL definidas em paths.php
 */

// Definir diretório raiz
$root_dir = __DIR__;

// Incluir configurações
require_once 'includes/config.php';

// Função para processar um arquivo PHP
function processarArquivo($arquivo) {
    // Obter o conteúdo do arquivo
    $conteudo = file_get_contents($arquivo);
    
    // Verificar se o arquivo já foi processado
    if (strpos($conteudo, 'CLIENTES_URL') !== false || 
        strpos($conteudo, 'ORCAMENTOS_URL') !== false || 
        strpos($conteudo, 'VENDAS_URL') !== false) {
        echo "Arquivo já processado: $arquivo<br>";
        return;
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
        echo "Arquivo atualizado: $arquivo<br>";
    } else {
        echo "Nenhuma alteração necessária: $arquivo<br>";
    }
}

// Função para processar um diretório recursivamente
function processarDiretorio($diretorio) {
    $arquivos = glob($diretorio . '/*.php');
    
    foreach ($arquivos as $arquivo) {
        processarArquivo($arquivo);
    }
    
    // Processar subdiretórios
    $subdiretorios = glob($diretorio . '/*', GLOB_ONLYDIR);
    foreach ($subdiretorios as $subdiretorio) {
        processarDiretorio($subdiretorio);
    }
}

// Verificar se o parâmetro de confirmação foi fornecido
if (!isset($_GET['confirmar']) || $_GET['confirmar'] !== 'sim') {
    echo "<h1>Atualizar Links para URLs Amigáveis</h1>";
    echo "<p>Este script irá atualizar automaticamente os links nos arquivos PHP para usar as URLs amigáveis.</p>";
    echo "<p>Os links diretos serão substituídos por constantes de URL definidas em paths.php.</p>";
    echo "<p>Por exemplo:</p>";
    echo "<pre>href=\"clientes.php\"</pre>";
    echo "<p>Será substituído por:</p>";
    echo "<pre>href=\"<?php echo CLIENTES_URL; ?>\"</pre>";
    echo "<p>Para continuar, clique no botão abaixo:</p>";
    echo "<a href='?confirmar=sim' style='display:inline-block; background-color:#0d6efd; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Confirmar Atualização</a>";
    echo "<p><a href='dashboard.php' style='color:#0d6efd;'>Cancelar e voltar para o Dashboard</a></p>";
    exit;
}

// Processar os diretórios de módulos
echo "<h1>Atualizando Links para URLs Amigáveis</h1>";

$modulos = glob($root_dir . '/modules/*', GLOB_ONLYDIR);
foreach ($modulos as $modulo) {
    echo "<h2>Processando módulo: " . basename($modulo) . "</h2>";
    processarDiretorio($modulo);
}

echo "<h2>Processamento concluído!</h2>";
echo "<p>Todos os arquivos PHP nos módulos foram processados.</p>";
echo "<p><a href='dashboard.php' style='color:#0d6efd;'>Voltar para o Dashboard</a></p>";
?><?php
/**
 * Script para atualizar automaticamente os links nos arquivos PHP para usar as URLs amigáveis
 * Este script substitui os links diretos por constantes de URL definidas em paths.php
 */

// Definir diretório raiz
$root_dir = __DIR__;

// Incluir configurações
require_once 'includes/config.php';

// Função para processar um arquivo PHP
function processarArquivo($arquivo) {
    // Obter o conteúdo do arquivo
    $conteudo = file_get_contents($arquivo);
    
    // Verificar se o arquivo já foi processado
    if (strpos($conteudo, 'CLIENTES_URL') !== false || 
        strpos($conteudo, 'ORCAMENTOS_URL') !== false || 
        strpos($conteudo, 'VENDAS_URL') !== false) {
        echo "Arquivo já processado: $arquivo<br>";
        return;
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
        echo "Arquivo atualizado: $arquivo<br>";
    } else {
        echo "Nenhuma alteração necessária: $arquivo<br>";
    }
}

// Função para processar um diretório recursivamente
function processarDiretorio($diretorio) {
    $arquivos = glob($diretorio . '/*.php');
    
    foreach ($arquivos as $arquivo) {
        processarArquivo($arquivo);
    }
    
    // Processar subdiretórios
    $subdiretorios = glob($diretorio . '/*', GLOB_ONLYDIR);
    foreach ($subdiretorios as $subdiretorio) {
        processarDiretorio($subdiretorio);
    }
}

// Verificar se o parâmetro de confirmação foi fornecido
if (!isset($_GET['confirmar']) || $_GET['confirmar'] !== 'sim') {
    echo "<h1>Atualizar Links para URLs Amigáveis</h1>";
    echo "<p>Este script irá atualizar automaticamente os links nos arquivos PHP para usar as URLs amigáveis.</p>";
    echo "<p>Os links diretos serão substituídos por constantes de URL definidas em paths.php.</p>";
    echo "<p>Por exemplo:</p>";
    echo "<pre>href=\"clientes.php\"</pre>";
    echo "<p>Será substituído por:</p>";
    echo "<pre>href=\"<?php echo CLIENTES_URL; ?>\"</pre>";
    echo "<p>Para continuar, clique no botão abaixo:</p>";
    echo "<a href='?confirmar=sim' style='display:inline-block; background-color:#0d6efd; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Confirmar Atualização</a>";
    echo "<p><a href='dashboard.php' style='color:#0d6efd;'>Cancelar e voltar para o Dashboard</a></p>";
    exit;
}

// Processar os diretórios de módulos
echo "<h1>Atualizando Links para URLs Amigáveis</h1>";

$modulos = glob($root_dir . '/modules/*', GLOB_ONLYDIR);
foreach ($modulos as $modulo) {
    echo "<h2>Processando módulo: " . basename($modulo) . "</h2>";
    processarDiretorio($modulo);
}

echo "<h2>Processamento concluído!</h2>";
echo "<p>Todos os arquivos PHP nos módulos foram processados.</p>";
echo "<p><a href='dashboard.php' style='color:#0d6efd;'>Voltar para o Dashboard</a></p>";
?>