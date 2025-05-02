<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar autenticação
verificarAutenticacao();

// Verificar se é administrador
if (!isset($_SESSION['usuario']['nivel']) || $_SESSION['usuario']['nivel'] !== 'admin') {
    // Redirecionar para a página inicial com mensagem de erro
    header('Location: index.php?erro=permissao');
    exit;
}

// Inicializar variáveis
$mensagem = '';
$arquivoSQL = '';

// Se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exportar'])) {
    try {
        // Gerar nome do arquivo baseado na data e hora atual
        $dataHora = date('Y-m-d_H-i-s');
        $nomeArquivo = "db_backup_completo_$dataHora.sql";
        $caminhoArquivo = __DIR__ . '/' . $nomeArquivo;
        
        // Obter variáveis de ambiente do PostgreSQL
        $dbHost = DB_HOST;
        $dbName = DB_NAME;
        $dbUser = DB_USER;
        $dbPass = DB_PASS;
        $dbPort = DB_PORT;
        
        // Criar comando para exportar o banco de dados
        $comando = "PGPASSWORD=\"$dbPass\" pg_dump -h $dbHost -p $dbPort -U $dbUser -d $dbName --clean --if-exists --no-owner --no-privileges > $caminhoArquivo";
        
        // Executar o comando
        exec($comando, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Erro ao exportar o banco de dados. Código de retorno: $returnCode");
        }
        
        // Verificar se o arquivo foi criado
        if (!file_exists($caminhoArquivo)) {
            throw new Exception("Arquivo de backup não foi criado");
        }
        
        // Adicionar comandos para corrigir problemas conhecidos
        $conteudoAdicional = "
-- Correções adicionais para garantir compatibilidade
ALTER TABLE permissoes
  ALTER COLUMN gerenciar_usuarios SET DEFAULT false,
  ALTER COLUMN visualizar_relatorios_financeiros SET DEFAULT false,
  ALTER COLUMN gerenciar_estoque SET DEFAULT false,
  ALTER COLUMN gerenciar_produtos SET DEFAULT false,
  ALTER COLUMN gerenciar_orcamentos SET DEFAULT false,
  ALTER COLUMN gerenciar_vendas SET DEFAULT false,
  ALTER COLUMN gerenciar_clientes SET DEFAULT false,
  ALTER COLUMN gerenciar_caixa SET DEFAULT false,
  ALTER COLUMN gerenciar_contas SET DEFAULT false,
  ALTER COLUMN visualizar_relatorios_gerais SET DEFAULT false;

-- Corrigir dados do usuário admin
UPDATE usuarios SET senha = '" . password_hash('admin123', PASSWORD_DEFAULT) . "' WHERE email = 'admin@admin.com';
";
        
        // Adicionar o conteúdo ao final do arquivo
        file_put_contents($caminhoArquivo, $conteudoAdicional, FILE_APPEND);
        
        // Ler o conteúdo do arquivo para exibir
        $conteudoSQL = file_get_contents($caminhoArquivo);
        
        // Salvar cópia permanente com nome padronizado
        $copiaPermanente = __DIR__ . '/db_backup_completo_com_correcoes.sql';
        file_put_contents($copiaPermanente, $conteudoSQL);
        
        $mensagem = "Backup do banco de dados realizado com sucesso! O arquivo <strong>$nomeArquivo</strong> foi criado.";
        $arquivoSQL = $nomeArquivo;
        
    } catch (Exception $e) {
        $mensagem = "Erro ao exportar o banco de dados: " . $e->getMessage();
    }
}

// Cabeçalho
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-database me-2"></i>Exportar Banco de Dados Completo</h1>
    <a href="dashboard.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php if (!empty($mensagem)): ?>
    <div class="alert alert-<?php echo strpos($mensagem, 'Erro') !== false ? 'danger' : 'success'; ?> mb-4">
        <?php echo $mensagem; ?>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-cogs me-2"></i>Exportação Completa do Banco de Dados
    </div>
    <div class="card-body">
        <p>Este utilitário irá exportar todo o banco de dados, incluindo estrutura e dados, para um arquivo SQL.</p>
        <p>O arquivo gerado pode ser importado em outro ambiente PostgreSQL para migrar o sistema.</p>
        
        <form method="post" class="mt-4">
            <div class="d-grid">
                <button type="submit" name="exportar" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-download me-2"></i>Exportar Banco de Dados
                </button>
            </div>
        </form>
        
        <?php if (!empty($arquivoSQL)): ?>
            <div class="mt-4">
                <h5>Download do Arquivo SQL:</h5>
                <div class="d-grid gap-2">
                    <a href="<?php echo $arquivoSQL; ?>" class="btn btn-success" download>
                        <i class="fas fa-file-download me-2"></i>Baixar <?php echo $arquivoSQL; ?>
                    </a>
                    <a href="db_backup_completo_com_correcoes.sql" class="btn btn-info" download>
                        <i class="fas fa-file-download me-2"></i>Baixar Cópia Permanente (db_backup_completo_com_correcoes.sql)
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header bg-info text-white">
        <i class="fas fa-info-circle me-2"></i>Instruções para Importar
    </div>
    <div class="card-body">
        <h5>Como importar o arquivo em outro servidor:</h5>
        
        <ol class="mb-4">
            <li>Copie o arquivo SQL para o servidor de destino</li>
            <li>Crie um banco de dados vazio no PostgreSQL</li>
            <li>Execute o comando abaixo para importar o backup:</li>
        </ol>
        
        <div class="bg-dark text-light p-3 rounded">
            <code>psql -h [servidor] -p [porta] -U [usuario] -d [nome_do_banco] -f [arquivo.sql]</code>
        </div>
        
        <p class="mt-3">Exemplo:</p>
        <div class="bg-dark text-light p-3 rounded">
            <code>psql -h localhost -p 5432 -U postgres -d sistema_calhas -f db_backup_completo_com_correcoes.sql</code>
        </div>
        
        <div class="alert alert-warning mt-4">
            <strong>Importante:</strong> O processo de importação substituirá todos os dados existentes no banco de dados de destino.
        </div>
    </div>
</div>

<?php
// Rodapé
require_once('includes/footer.php');
?>