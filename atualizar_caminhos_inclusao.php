<?php
/**
 * Script para atualizar automaticamente os caminhos de inclusão em todos os arquivos PHP nos módulos
 * Este script substitui os caminhos relativos por caminhos absolutos usando __DIR__
 */

// Definir diretório raiz
$root_dir = __DIR__;

// Função para processar um arquivo PHP
function processarArquivo($arquivo) {
    global $root_dir;
    
    // Obter o conteúdo do arquivo
    $conteudo = file_get_contents($arquivo);
    
    // Verificar se o arquivo já foi processado
    if (strpos($conteudo, '__DIR__ . \'/../../includes') !== false) {
        echo "Arquivo já processado: $arquivo<br>";
        return;
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
    echo "<h1>Atualizar Caminhos de Inclusão</h1>";
    echo "<p>Este script irá atualizar automaticamente os caminhos de inclusão em todos os arquivos PHP nos módulos.</p>";
    echo "<p>Os caminhos relativos serão substituídos por caminhos absolutos usando __DIR__.</p>";
    echo "<p>Por exemplo:</p>";
    echo "<pre>require_once('includes/config.php');</pre>";
    echo "<p>Será substituído por:</p>";
    echo "<pre>require_once(__DIR__ . '/../../includes/config.php');</pre>";
    echo "<p>Para continuar, clique no botão abaixo:</p>";
    echo "<a href='?confirmar=sim' style='display:inline-block; background-color:#0d6efd; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Confirmar Atualização</a>";
    echo "<p><a href='dashboard.php' style='color:#0d6efd;'>Cancelar e voltar para o Dashboard</a></p>";
    exit;
}

// Processar os diretórios de módulos
echo "<h1>Atualizando Caminhos de Inclusão</h1>";

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
 * Script para atualizar automaticamente os caminhos de inclusão em todos os arquivos PHP nos módulos
 * Este script substitui os caminhos relativos por caminhos absolutos usando __DIR__
 */

// Definir diretório raiz
$root_dir = __DIR__;

// Função para processar um arquivo PHP
function processarArquivo($arquivo) {
    global $root_dir;
    
    // Obter o conteúdo do arquivo
    $conteudo = file_get_contents($arquivo);
    
    // Verificar se o arquivo já foi processado
    if (strpos($conteudo, '__DIR__ . \'/../../includes') !== false) {
        echo "Arquivo já processado: $arquivo<br>";
        return;
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
    echo "<h1>Atualizar Caminhos de Inclusão</h1>";
    echo "<p>Este script irá atualizar automaticamente os caminhos de inclusão em todos os arquivos PHP nos módulos.</p>";
    echo "<p>Os caminhos relativos serão substituídos por caminhos absolutos usando __DIR__.</p>";
    echo "<p>Por exemplo:</p>";
    echo "<pre>require_once('includes/config.php');</pre>";
    echo "<p>Será substituído por:</p>";
    echo "<pre>require_once(__DIR__ . '/../../includes/config.php');</pre>";
    echo "<p>Para continuar, clique no botão abaixo:</p>";
    echo "<a href='?confirmar=sim' style='display:inline-block; background-color:#0d6efd; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Confirmar Atualização</a>";
    echo "<p><a href='dashboard.php' style='color:#0d6efd;'>Cancelar e voltar para o Dashboard</a></p>";
    exit;
}

// Processar os diretórios de módulos
echo "<h1>Atualizando Caminhos de Inclusão</h1>";

$modulos = glob($root_dir . '/modules/*', GLOB_ONLYDIR);
foreach ($modulos as $modulo) {
    echo "<h2>Processando módulo: " . basename($modulo) . "</h2>";
    processarDiretorio($modulo);
}

echo "<h2>Processamento concluído!</h2>";
echo "<p>Todos os arquivos PHP nos módulos foram processados.</p>";
echo "<p><a href='dashboard.php' style='color:#0d6efd;'>Voltar para o Dashboard</a></p>";
?>