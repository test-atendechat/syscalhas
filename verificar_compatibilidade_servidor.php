<?php
/**
 * Script para verificar a compatibilidade do servidor com as URLs amigáveis
 * Este script verifica se o mod_rewrite está habilitado e se o .htaccess está funcionando
 */

// Função para verificar se o mod_rewrite está habilitado
function verificarModRewrite() {
    if (function_exists('apache_get_modules')) {
        return in_array('mod_rewrite', apache_get_modules());
    } else {
        // Se não for possível verificar diretamente, tentar criar um arquivo de teste
        $arquivo_teste = __DIR__ . '/.htaccess_teste';
        $conteudo_teste = "RewriteEngine On\nRewriteRule ^teste$ teste.php [L]";
        file_put_contents($arquivo_teste, $conteudo_teste);
        
        $arquivo_php_teste = __DIR__ . '/teste.php';
        $conteudo_php_teste = "<?php echo 'OK'; ?>";
        file_put_contents($arquivo_php_teste, $conteudo_php_teste);
        
        // Tentar acessar o arquivo de teste
        $url_teste = 'http://' . $_SERVER['HTTP_HOST'] . '/teste';
        $ch = curl_init($url_teste);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resultado = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Remover os arquivos de teste
        unlink($arquivo_teste);
        unlink($arquivo_php_teste);
        
        return ($http_code == 200 && $resultado == 'OK');
    }
}

// Função para verificar se o .htaccess está funcionando
function verificarHtaccess() {
    // Verificar se o arquivo .htaccess existe
    if (!file_exists(__DIR__ . '/.htaccess')) {
        return false;
    }
    
    // Tentar acessar uma URL amigável
    $url_teste = 'http://' . $_SERVER['HTTP_HOST'] . '/dashboard';
    $ch = curl_init($url_teste);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code == 200);
}

// Função para verificar as permissões de diretórios
function verificarPermissoes() {
    $diretorios = [
        __DIR__,
        __DIR__ . '/modules',
        __DIR__ . '/includes',
        __DIR__ . '/css',
        __DIR__ . '/js',
        __DIR__ . '/img',
        __DIR__ . '/database',
        __DIR__ . '/utils',
        __DIR__ . '/notificacoes'
    ];
    
    $resultados = [];
    
    foreach ($diretorios as $diretorio) {
        if (is_dir($diretorio)) {
            $resultados[$diretorio] = [
                'existe' => true,
                'leitura' => is_readable($diretorio),
                'escrita' => is_writable($diretorio)
            ];
        } else {
            $resultados[$diretorio] = [
                'existe' => false,
                'leitura' => false,
                'escrita' => false
            ];
        }
    }
    
    return $resultados;
}

// Função para verificar a versão do PHP
function verificarVersaoPHP() {
    return [
        'versao' => PHP_VERSION,
        'compativel' => version_compare(PHP_VERSION, '7.4.0', '>=')
    ];
}

// Função para verificar a conexão com o banco de dados
function verificarBancoDados() {
    try {
        require_once 'includes/db.php';
        global $pdo;
        
        $stmt = $pdo->query("SELECT 1");
        return [
            'conectado' => true,
            'tipo' => DB_TYPE,
            'host' => DB_HOST,
            'nome' => DB_NAME,
            'usuario' => DB_USER,
            'porta' => DB_PORT
        ];
    } catch (Exception $e) {
        return [
            'conectado' => false,
            'erro' => $e->getMessage()
        ];
    }
}

// Realizar as verificações
$mod_rewrite = verificarModRewrite();
$htaccess = verificarHtaccess();
$permissoes = verificarPermissoes();
$versao_php = verificarVersaoPHP();
$banco_dados = verificarBancoDados();

// Exibir os resultados
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Compatibilidade do Servidor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Verificar Compatibilidade do Servidor</h1>
        <p>Este script verifica se o servidor é compatível com as URLs amigáveis e a nova estrutura de diretórios.</p>
        
        <div class="card mb-4">
            <div class="card-header">
                <h2>Requisitos para URLs Amigáveis</h2>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>mod_rewrite habilitado</td>
                        <td>
                            <?php if ($mod_rewrite): ?>
                                <span class="success"><i class="fas fa-check"></i> Sim</span>
                            <?php else: ?>
                                <span class="error"><i class="fas fa-times"></i> Não</span>
                                <p class="text-danger">O módulo mod_rewrite não está habilitado. As URLs amigáveis não funcionarão corretamente.</p>
                                <p>Para habilitar o mod_rewrite, execute os seguintes comandos:</p>
                                <pre>sudo a2enmod rewrite
sudo systemctl restart apache2</pre>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>.htaccess funcionando</td>
                        <td>
                            <?php if ($htaccess): ?>
                                <span class="success"><i class="fas fa-check"></i> Sim</span>
                            <?php else: ?>
                                <span class="error"><i class="fas fa-times"></i> Não</span>
                                <p class="text-danger">O arquivo .htaccess não está funcionando corretamente. As URLs amigáveis não funcionarão.</p>
                                <p>Verifique se o arquivo .htaccess existe na raiz do projeto e se o Apache está configurado para permitir o uso de .htaccess:</p>
                                <pre>&lt;Directory /var/www/html&gt;
    AllowOverride All
&lt;/Directory&gt;</pre>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h2>Versão do PHP</h2>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>Versão do PHP</td>
                        <td>
                            <?php echo $versao_php['versao']; ?>
                            <?php if ($versao_php['compativel']): ?>
                                <span class="success"><i class="fas fa-check"></i> Compatível</span>
                            <?php else: ?>
                                <span class="error"><i class="fas fa-times"></i> Incompatível</span>
                                <p class="text-danger">O sistema requer PHP 7.4 ou superior.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h2>Conexão com o Banco de Dados</h2>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>Conexão</td>
                        <td>
                            <?php if ($banco_dados['conectado']): ?>
                                <span class="success"><i class="fas fa-check"></i> Conectado</span>
                            <?php else: ?>
                                <span class="error"><i class="fas fa-times"></i> Erro</span>
                                <p class="text-danger">Erro: <?php echo $banco_dados['erro']; ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($banco_dados['conectado']): ?>
                    <tr>
                        <td>Tipo</td>
                        <td><?php echo $banco_dados['tipo']; ?></td>
                    </tr>
                    <tr>
                        <td>Host</td>
                        <td><?php echo $banco_dados['host']; ?></td>
                    </tr>
                    <tr>
                        <td>Nome do Banco</td>
                        <td><?php echo $banco_dados['nome']; ?></td>
                    </tr>
                    <tr>
                        <td>Usuário</td>
                        <td><?php echo $banco_dados['usuario']; ?></td>
                    </tr>
                    <tr>
                        <td>Porta</td>
                        <td><?php echo $banco_dados['porta']; ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h2>Permissões de Diretórios</h2>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Diretório</th>
                            <th>Existe</th>
                            <th>Leitura</th>
                            <th>Escrita</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissoes as $diretorio => $info): ?>
                        <tr>
                            <td><?php echo $diretorio; ?></td>
                            <td>
                                <?php if ($info['existe']): ?>
                                    <span class="success"><i class="fas fa-check"></i> Sim</span>
                                <?php else: ?>
                                    <span class="error"><i class="fas fa-times"></i> Não</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($info['leitura']): ?>
                                    <span class="success"><i class="fas fa-check"></i> Sim</span>
                                <?php else: ?>
                                    <span class="error"><i class="fas fa-times"></i> Não</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($info['escrita']): ?>
                                    <span class="success"><i class="fas fa-check"></i> Sim</span>
                                <?php else: ?>
                                    <span class="error"><i class="fas fa-times"></i> Não</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-secondary">Voltar para o Dashboard</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html><?php
/**
 * Script para verificar a compatibilidade do servidor com as URLs amigáveis
 * Este script verifica se o mod_rewrite está habilitado e se o .htaccess está funcionando
 */

// Função para verificar se o mod_rewrite está habilitado
function verificarModRewrite() {
    if (function_exists('apache_get_modules')) {
        return in_array('mod_rewrite', apache_get_modules());
    } else {
        // Se não for possível verificar diretamente, tentar criar um arquivo de teste
        $arquivo_teste = __DIR__ . '/.htaccess_teste';
        $conteudo_teste = "RewriteEngine On\nRewriteRule ^teste$ teste.php [L]";
        file_put_contents($arquivo_teste, $conteudo_teste);
        
        $arquivo_php_teste = __DIR__ . '/teste.php';
        $conteudo_php_teste = "<?php echo 'OK'; ?>";
        file_put_contents($arquivo_php_teste, $conteudo_php_teste);
        
        // Tentar acessar o arquivo de teste
        $url_teste = 'http://' . $_SERVER['HTTP_HOST'] . '/teste';
        $ch = curl_init($url_teste);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resultado = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Remover os arquivos de teste
        unlink($arquivo_teste);
        unlink($arquivo_php_teste);
        
        return ($http_code == 200 && $resultado == 'OK');
    }
}

// Função para verificar se o .htaccess está funcionando
function verificarHtaccess() {
    // Verificar se o arquivo .htaccess existe
    if (!file_exists(__DIR__ . '/.htaccess')) {
        return false;
    }
    
    // Tentar acessar uma URL amigável
    $url_teste = 'http://' . $_SERVER['HTTP_HOST'] . '/dashboard';
    $ch = curl_init($url_teste);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code == 200);
}

// Função para verificar as permissões de diretórios
function verificarPermissoes() {
    $diretorios = [
        __DIR__,
        __DIR__ . '/modules',
        __DIR__ . '/includes',
        __DIR__ . '/css',
        __DIR__ . '/js',
        __DIR__ . '/img',
        __DIR__ . '/database',
        __DIR__ . '/utils',
        __DIR__ . '/notificacoes'
    ];
    
    $resultados = [];
    
    foreach ($diretorios as $diretorio) {
        if (is_dir($diretorio)) {
            $resultados[$diretorio] = [
                'existe' => true,
                'leitura' => is_readable($diretorio),
                'escrita' => is_writable($diretorio)
            ];
        } else {
            $resultados[$diretorio] = [
                'existe' => false,
                'leitura' => false,
                'escrita' => false
            ];
        }
    }
    
    return $resultados;
}

// Função para verificar a versão do PHP
function verificarVersaoPHP() {
    return [
        'versao' => PHP_VERSION,
        'compativel' => version_compare(PHP_VERSION, '7.4.0', '>=')
    ];
}

// Função para verificar a conexão com o banco de dados
function verificarBancoDados() {
    try {
        require_once 'includes/db.php';
        global $pdo;
        
        $stmt = $pdo->query("SELECT 1");
        return [
            'conectado' => true,
            'tipo' => DB_TYPE,
            'host' => DB_HOST,
            'nome' => DB_NAME,
            'usuario' => DB_USER,
            'porta' => DB_PORT
        ];
    } catch (Exception $e) {
        return [
            'conectado' => false,
            'erro' => $e->getMessage()
        ];
    }
}

// Realizar as verificações
$mod_rewrite = verificarModRewrite();
$htaccess = verificarHtaccess();
$permissoes = verificarPermissoes();
$versao_php = verificarVersaoPHP();
$banco_dados = verificarBancoDados();

// Exibir os resultados
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Compatibilidade do Servidor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Verificar Compatibilidade do Servidor</h1>
        <p>Este script verifica se o servidor é compatível com as URLs amigáveis e a nova estrutura de diretórios.</p>
        
        <div class="card mb-4">
            <div class="card-header">
                <h2>Requisitos para URLs Amigáveis</h2>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>mod_rewrite habilitado</td>
                        <td>
                            <?php if ($mod_rewrite): ?>
                                <span class="success"><i class="fas fa-check"></i> Sim</span>
                            <?php else: ?>
                                <span class="error"><i class="fas fa-times"></i> Não</span>
                                <p class="text-danger">O módulo mod_rewrite não está habilitado. As URLs amigáveis não funcionarão corretamente.</p>
                                <p>Para habilitar o mod_rewrite, execute os seguintes comandos:</p>
                                <pre>sudo a2enmod rewrite
sudo systemctl restart apache2</pre>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>.htaccess funcionando</td>
                        <td>
                            <?php if ($htaccess): ?>
                                <span class="success"><i class="fas fa-check"></i> Sim</span>
                            <?php else: ?>
                                <span class="error"><i class="fas fa-times"></i> Não</span>
                                <p class="text-danger">O arquivo .htaccess não está funcionando corretamente. As URLs amigáveis não funcionarão.</p>
                                <p>Verifique se o arquivo .htaccess existe na raiz do projeto e se o Apache está configurado para permitir o uso de .htaccess:</p>
                                <pre>&lt;Directory /var/www/html&gt;
    AllowOverride All
&lt;/Directory&gt;</pre>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h2>Versão do PHP</h2>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>Versão do PHP</td>
                        <td>
                            <?php echo $versao_php['versao']; ?>
                            <?php if ($versao_php['compativel']): ?>
                                <span class="success"><i class="fas fa-check"></i> Compatível</span>
                            <?php else: ?>
                                <span class="error"><i class="fas fa-times"></i> Incompatível</span>
                                <p class="text-danger">O sistema requer PHP 7.4 ou superior.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h2>Conexão com o Banco de Dados</h2>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>Conexão</td>
                        <td>
                            <?php if ($banco_dados['conectado']): ?>
                                <span class="success"><i class="fas fa-check"></i> Conectado</span>
                            <?php else: ?>
                                <span class="error"><i class="fas fa-times"></i> Erro</span>
                                <p class="text-danger">Erro: <?php echo $banco_dados['erro']; ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($banco_dados['conectado']): ?>
                    <tr>
                        <td>Tipo</td>
                        <td><?php echo $banco_dados['tipo']; ?></td>
                    </tr>
                    <tr>
                        <td>Host</td>
                        <td><?php echo $banco_dados['host']; ?></td>
                    </tr>
                    <tr>
                        <td>Nome do Banco</td>
                        <td><?php echo $banco_dados['nome']; ?></td>
                    </tr>
                    <tr>
                        <td>Usuário</td>
                        <td><?php echo $banco_dados['usuario']; ?></td>
                    </tr>
                    <tr>
                        <td>Porta</td>
                        <td><?php echo $banco_dados['porta']; ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h2>Permissões de Diretórios</h2>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Diretório</th>
                            <th>Existe</th>
                            <th>Leitura</th>
                            <th>Escrita</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissoes as $diretorio => $info): ?>
                        <tr>
                            <td><?php echo $diretorio; ?></td>
                            <td>
                                <?php if ($info['existe']): ?>
                                    <span class="success"><i class="fas fa-check"></i> Sim</span>
                                <?php else: ?>
                                    <span class="error"><i class="fas fa-times"></i> Não</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($info['leitura']): ?>
                                    <span class="success"><i class="fas fa-check"></i> Sim</span>
                                <?php else: ?>
                                    <span class="error"><i class="fas fa-times"></i> Não</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($info['escrita']): ?>
                                    <span class="success"><i class="fas fa-check"></i> Sim</span>
                                <?php else: ?>
                                    <span class="error"><i class="fas fa-times"></i> Não</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="dashboard.php" class="btn btn-secondary">Voltar para o Dashboard</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>