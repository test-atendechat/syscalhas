<?php
// Inicialização de variáveis
$titulo = "Restauração de Backup";
$mensagens = [];

// Incluir arquivos de configuração
require_once 'includes/config.php';
require_once 'includes/db.php';

// Verificar se o usuário está autenticado
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// Processar formulário
$confirmado = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar']) && $_POST['confirmar'] === 'sim') {
        $confirmado = true;
        
        // Caminho para o arquivo de backup
        $arquivo_backup = 'attached_assets/backup_2025-04-29_22-58-45.sql';
        
        if (file_exists($arquivo_backup)) {
            // Ler o conteúdo do arquivo
            $conteudo = file_get_contents($arquivo_backup);
            
            // Dividir o conteúdo em comandos SQL
            $comandos = explode("-- --------------------------------------------------------", $conteudo);
            
            try {
                // Iniciar transação
                $db->beginTransaction();
                
                $sucesso = 0;
                $falhas = 0;
                
                foreach ($comandos as $comando) {
                    // Pular comentários e linhas em branco
                    if (empty(trim($comando)) || strpos(trim($comando), '--') === 0) {
                        continue;
                    }
                    
                    // Extrair nome da tabela (para exibição)
                    preg_match('/DROP TABLE IF EXISTS ([a-zA-Z0-9_]+)/', $comando, $matches);
                    $tabela = isset($matches[1]) ? $matches[1] : 'desconhecida';
                    
                    // Dividir o comando em DROP TABLE, CREATE TABLE e INSERT
                    $partes = preg_split('/(DROP TABLE|CREATE TABLE|INSERT INTO)/', $comando, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                    
                    // Para cada parte do comando
                    for ($i = 0; $i < count($partes); $i += 2) {
                        if (isset($partes[$i]) && isset($partes[$i+1])) {
                            $tipo = trim($partes[$i]);
                            $sql = $tipo . ' ' . trim($partes[$i+1]);
                            
                            // Verificar se é um comando válido
                            if (!empty(trim($sql))) {
                                try {
                                    // Adicionar ponto e vírgula ao final se não existir
                                    if (substr(rtrim($sql), -1) !== ';') {
                                        $sql .= ';';
                                    }
                                    
                                    // Execução do comando SQL
                                    $db->exec($sql);
                                    $sucesso++;
                                } catch (PDOException $e) {
                                    // Ignorar erros específicos (tabela não existe, chave duplicada, etc)
                                    if (strpos($e->getMessage(), 'already exists') !== false || 
                                        strpos($e->getMessage(), 'does not exist') !== false) {
                                        // Apenas avisar, não é um erro crítico
                                        $mensagens[] = [
                                            'tipo' => 'info',
                                            'texto' => "ℹ️ Aviso: " . $e->getMessage()
                                        ];
                                    } else {
                                        // Registrar o erro
                                        $falhas++;
                                        $mensagens[] = [
                                            'tipo' => 'erro',
                                            'texto' => "❌ Erro em '$tabela': " . $e->getMessage()
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Confirmar transação
                $db->commit();
                
                $mensagens[] = [
                    'tipo' => 'sucesso',
                    'texto' => "✅ Restauração concluída! $sucesso comandos executados com sucesso."
                ];
                
                if ($falhas > 0) {
                    $mensagens[] = [
                        'tipo' => 'aviso',
                        'texto' => "⚠️ Ocorreram $falhas falhas durante a restauração. Veja os detalhes acima."
                    ];
                }
            } catch (PDOException $e) {
                // Reverter transação em caso de erro
                $db->rollBack();
                $mensagens[] = [
                    'tipo' => 'erro',
                    'texto' => "❌ Erro durante a restauração: " . $e->getMessage()
                ];
            }
        } else {
            $mensagens[] = [
                'tipo' => 'erro',
                'texto' => "❌ Arquivo de backup não encontrado: $arquivo_backup"
            ];
        }
    } else {
        $mensagens[] = [
            'tipo' => 'aviso',
            'texto' => "⚠️ Você precisa confirmar a restauração marcando a caixa de seleção."
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
                        <h5 class="mb-0"><i class="bi bi-database-fill-up"></i> Restauração de Backup</h5>
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
                                <p>Esta operação irá <strong>RESTAURAR O BANCO DE DADOS</strong> a partir do backup:</p>
                                <code>attached_assets/backup_2025-04-29_22-58-45.sql</code>
                                <p class="mt-2"><strong>Este processo irá sobrescrever todos os dados atuais!</strong></p>
                            </div>

                            <form method="post" class="mt-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="confirmar" value="sim" id="confirmarRestauracao">
                                    <label class="form-check-label" for="confirmarRestauracao">
                                        Eu entendo que esta ação irá sobrescrever os dados existentes e não pode ser desfeita.
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-database-fill-up"></i> Restaurar Backup
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