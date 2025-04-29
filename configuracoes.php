<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');

$titulo = "Configurações";
require_once('includes/header.php');

// Verificar se o usuário é administrador
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] != 'administrador') {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>Acesso negado! Você não tem permissão para acessar esta página.
          </div>';
    require_once('includes/footer.php');
    exit;
}

// Inicializar variáveis
$mensagem = '';
$configuracoes = [
    'empresa_nome' => APP_NAME,
    'empresa_telefone' => '',
    'empresa_email' => '',
    'empresa_endereco' => '',
    'empresa_cnpj' => '',
    'taxa_padrao_mao_obra' => 15, // Valor padrão para a taxa de mão de obra (em %)
    'estoque_alerta_minimo' => 5, // Valor padrão para alerta de estoque mínimo
    'dias_validade_orcamento' => 15, // Validade padrão dos orçamentos em dias
];

// Buscar configurações atuais do banco de dados
$stmt = $db->query("SELECT chave, valor FROM configuracoes");
$config_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Mesclar configurações do banco com valores padrão
foreach ($config_db as $chave => $valor) {
    $configuracoes[$chave] = $valor;
}

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        
        // Processar cada configuração
        foreach ($configuracoes as $chave => $valor) {
            if (isset($_POST[$chave])) {
                $novo_valor = trim($_POST[$chave]);
                
                // Verificar se a configuração já existe
                $stmt = $db->prepare("SELECT COUNT(*) FROM configuracoes WHERE chave = :chave");
                $stmt->bindParam(':chave', $chave);
                $stmt->execute();
                $existe = $stmt->fetchColumn();
                
                if ($existe) {
                    // Atualizar
                    $stmt = $db->prepare("UPDATE configuracoes SET valor = :valor WHERE chave = :chave");
                } else {
                    // Inserir
                    $stmt = $db->prepare("INSERT INTO configuracoes (chave, valor) VALUES (:chave, :valor)");
                }
                
                $stmt->bindParam(':chave', $chave);
                $stmt->bindParam(':valor', $novo_valor);
                $stmt->execute();
                
                // Atualizar a variável local
                $configuracoes[$chave] = $novo_valor;
            }
        }
        
        // Confirmar transação
        $db->commit();
        $mensagem = alerta('Configurações atualizadas com sucesso!', 'success');
    } catch (Exception $e) {
        // Reverter em caso de erro
        $db->rollback();
        $mensagem = alerta('Erro ao atualizar configurações: ' . $e->getMessage(), 'danger');
    }
}

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-cog me-2"></i>Configurações do Sistema</h1>
    <a href="dashboard.php" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php echo $mensagem; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Configurações Gerais</h5>
    </div>
    <div class="card-body">
        <form method="post">
            <!-- Dados da Empresa -->
            <div class="mb-4">
                <h5><i class="fas fa-building me-2"></i>Dados da Empresa</h5>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="empresa_nome" class="form-label">Nome da Empresa</label>
                        <input type="text" class="form-control" id="empresa_nome" name="empresa_nome" value="<?php echo $configuracoes['empresa_nome']; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="empresa_cnpj" class="form-label">CNPJ</label>
                        <input type="text" class="form-control" id="empresa_cnpj" name="empresa_cnpj" value="<?php echo $configuracoes['empresa_cnpj']; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="empresa_telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="empresa_telefone" name="empresa_telefone" value="<?php echo $configuracoes['empresa_telefone']; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="empresa_email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="empresa_email" name="empresa_email" value="<?php echo $configuracoes['empresa_email']; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="empresa_endereco" class="form-label">Endereço</label>
                        <textarea class="form-control" id="empresa_endereco" name="empresa_endereco" rows="3"><?php echo $configuracoes['empresa_endereco']; ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Configurações de Orçamentos -->
            <div class="mb-4">
                <h5><i class="fas fa-file-invoice-dollar me-2"></i>Configurações de Orçamentos</h5>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="taxa_padrao_mao_obra" class="form-label">Taxa Padrão de Mão de Obra (%)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="taxa_padrao_mao_obra" name="taxa_padrao_mao_obra" step="0.01" min="0" value="<?php echo $configuracoes['taxa_padrao_mao_obra']; ?>">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">Este valor será usado como padrão ao criar novos orçamentos.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="dias_validade_orcamento" class="form-label">Validade Padrão dos Orçamentos (dias)</label>
                        <input type="number" class="form-control" id="dias_validade_orcamento" name="dias_validade_orcamento" min="1" value="<?php echo $configuracoes['dias_validade_orcamento']; ?>">
                        <div class="form-text">Número de dias em que os orçamentos permanecem válidos a partir da data de criação.</div>
                    </div>
                </div>
            </div>
            
            <!-- Configurações de Estoque -->
            <div class="mb-4">
                <h5><i class="fas fa-warehouse me-2"></i>Configurações de Estoque</h5>
                <hr>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="estoque_alerta_minimo" class="form-label">Alerta de Estoque Mínimo</label>
                        <input type="number" class="form-control" id="estoque_alerta_minimo" name="estoque_alerta_minimo" min="0" step="0.01" value="<?php echo $configuracoes['estoque_alerta_minimo']; ?>">
                        <div class="form-text">Produtos com estoque abaixo deste valor serão destacados como estoque baixo.</div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="reset" class="btn btn-outline-secondary me-2">Restaurar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Salvar Configurações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para CNPJ
    const cnpjInput = document.getElementById('empresa_cnpj');
    if (cnpjInput) {
        IMask(cnpjInput, {
            mask: '00.000.000/0000-00'
        });
    }
    
    // Máscara para telefone
    const telefoneInput = document.getElementById('empresa_telefone');
    if (telefoneInput) {
        IMask(telefoneInput, {
            mask: [
                {
                    mask: '(00) 0000-0000'
                },
                {
                    mask: '(00) 00000-0000'
                }
            ]
        });
    }
});
</script>

<?php require_once('includes/footer.php'); ?>