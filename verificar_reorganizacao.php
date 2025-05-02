<?php
// Script para verificar se os arquivos foram copiados corretamente para as novas pastas

// Definir diretórios a verificar
$diretorios = [
    'modules/agendamentos',
    'modules/caixa',
    'modules/clientes',
    'modules/colaboradores',
    'modules/estoque',
    'modules/financeiro',
    'modules/orcamentos',
    'modules/produtos',
    'modules/relatorios',
    'modules/sistema',
    'modules/usuarios',
    'modules/vendas',
    'database/backups',
    'database/scripts',
    'utils',
    'notificacoes'
];

// Verificar se os diretórios existem
echo "<h1>Verificação da Reorganização de Arquivos</h1>";
echo "<h2>Verificando diretórios...</h2>";
echo "<ul>";
foreach ($diretorios as $diretorio) {
    $caminho_completo = __DIR__ . '/' . $diretorio;
    if (is_dir($caminho_completo)) {
        echo "<li style='color:green'>✓ Diretório <strong>{$diretorio}</strong> existe.</li>";
        
        // Contar arquivos no diretório
        $arquivos = glob($caminho_completo . '/*');
        $num_arquivos = count($arquivos);
        echo "<ul><li>Contém {$num_arquivos} arquivo(s).</li>";
        
        // Listar os primeiros 5 arquivos
        if ($num_arquivos > 0) {
            echo "<li>Exemplos: ";
            $contador = 0;
            foreach ($arquivos as $arquivo) {
                if ($contador >= 5) break;
                echo basename($arquivo) . ", ";
                $contador++;
            }
            echo "</li>";
        }
        echo "</ul>";
    } else {
        echo "<li style='color:red'>✗ Diretório <strong>{$diretorio}</strong> não existe!</li>";
    }
}
echo "</ul>";

// Verificar se o arquivo .htaccess existe
echo "<h2>Verificando arquivos de configuração...</h2>";
echo "<ul>";
if (file_exists(__DIR__ . '/.htaccess')) {
    echo "<li style='color:green'>✓ Arquivo <strong>.htaccess</strong> existe.</li>";
} else {
    echo "<li style='color:red'>✗ Arquivo <strong>.htaccess</strong> não existe!</li>";
}

// Verificar se o arquivo paths.php existe
if (file_exists(__DIR__ . '/includes/paths.php')) {
    echo "<li style='color:green'>✓ Arquivo <strong>includes/paths.php</strong> existe.</li>";
} else {
    echo "<li style='color:red'>✗ Arquivo <strong>includes/paths.php</strong> não existe!</li>";
}

// Verificar se o README.md existe
if (file_exists(__DIR__ . '/README.md')) {
    echo "<li style='color:green'>✓ Arquivo <strong>README.md</strong> existe.</li>";
} else {
    echo "<li style='color:red'>✗ Arquivo <strong>README.md</strong> não existe!</li>";
}
echo "</ul>";

// Verificar arquivos que podem ser removidos
echo "<h2>Arquivos que podem ser removidos:</h2>";
echo "<ul>";

// Arquivos de teste
$arquivos_teste = glob(__DIR__ . '/*teste*.php');
foreach ($arquivos_teste as $arquivo) {
    echo "<li>" . basename($arquivo) . "</li>";
}

// Arquivos de correção
$arquivos_correcao = glob(__DIR__ . '/*corrigir*.php');
foreach ($arquivos_correcao as $arquivo) {
    echo "<li>" . basename($arquivo) . "</li>";
}

// Arquivos de backup SQL na raiz
$arquivos_sql = glob(__DIR__ . '/*.sql');
foreach ($arquivos_sql as $arquivo) {
    echo "<li>" . basename($arquivo) . "</li>";
}

// Arquivos duplicados
$arquivos_duplicados = [
    'caixa_form_modified.php',
    'caixa_form_notificacoes_patch.php',
    'limpar_dados_teste.php',
    'limpar_dados_teste_colaboradores.php',
    'limpar_dados_teste_novo.php'
];

foreach ($arquivos_duplicados as $arquivo) {
    if (file_exists(__DIR__ . '/' . $arquivo)) {
        echo "<li>" . $arquivo . "</li>";
    }
}

echo "</ul>";

echo "<h2>Próximos passos:</h2>";
echo "<ol>";
echo "<li>Verifique se todos os diretórios foram criados corretamente.</li>";
echo "<li>Verifique se os arquivos foram copiados para os diretórios corretos.</li>";
echo "<li>Teste o funcionamento do sistema com as URLs amigáveis.</li>";
echo "<li>Após confirmar que tudo está funcionando, remova os arquivos duplicados da raiz.</li>";
echo "<li>Atualize os links internos nos arquivos PHP para usar as novas URLs amigáveis.</li>";
echo "</ol>";
?>