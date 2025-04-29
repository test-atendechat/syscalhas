<?php
require_once('includes/config.php');
require_once('includes/db.php');
require_once('includes/functions.php');
require_once('includes/auth.php');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Inicializar variáveis
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$produto = [
    'id' => 0,
    'codigo' => '',
    'descricao' => '',
    'categoria_id' => '',
    'unidade' => 'unidade',
    'valor_unitario' => '',
    'custo_unitario' => '',
    'observacoes' => ''
];
$erro = '';
$sucesso = '';
$titulo = 'Cadastrar Novo Produto';
$modo = 'cadastrar';

// Se for edição, buscar dados do produto
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM produtos WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 1) {
        $produto = $stmt->fetch(PDO::FETCH_ASSOC);
        $titulo = 'Editar Produto';
        $modo = 'editar';
    } else {
        $erro = 'Produto não encontrado.';
    }
}

// Obter categorias para sugestão
$categorias = $db->query("SELECT id, nome FROM categorias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capturar dados do formulário
    $produto = [
        'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
        'codigo' => limpaString($_POST['codigo'] ?? ''),
        'descricao' => limpaString($_POST['descricao'] ?? ''),
        'categoria_id' => intval($_POST['categoria_id'] ?? 0),
        'unidade' => limpaString($_POST['unidade'] ?? 'unidade'),
        'valor_unitario' => str_replace(',', '.', str_replace('.', '', $_POST['valor_unitario'] ?? '0')),
        'custo_unitario' => str_replace(',', '.', str_replace('.', '', $_POST['custo_unitario'] ?? '0')),
        'estoque_minimo' => str_replace(',', '.', str_replace('.', '', $_POST['estoque_minimo'] ?? '0')),
        'observacoes' => limpaString($_POST['observacoes'] ?? '')
    ];
    
    // Validações
    if (empty($produto['descricao'])) {
        $erro = 'A descrição do produto é obrigatória.';
    } elseif (empty($produto['categoria_id'])) {
        $erro = 'A categoria é obrigatória.';
    } elseif (empty($produto['valor_unitario']) || !is_numeric($produto['valor_unitario'])) {
        $erro = 'O valor unitário é obrigatório e deve ser um número válido.';
    } else {
        // Verificar se código já está cadastrado para outro produto (se informado)
        if (!empty($produto['codigo'])) {
            $stmt = $db->prepare("SELECT id FROM produtos WHERE codigo = :codigo AND id != :id");
            $stmt->bindParam(':codigo', $produto['codigo']);
            $stmt->bindParam(':id', $produto['id'], PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $erro = 'Este código já está cadastrado para outro produto.';
            }
        }
        
        if (empty($erro)) {
            try {
                // Inserir ou atualizar produto
                if ($produto['id'] > 0) {
                    // Atualizar
                    $stmt = $db->prepare("UPDATE produtos SET 
                        codigo = :codigo,
                        descricao = :descricao,
                        categoria_id = :categoria_id,
                        unidade = :unidade,
                        valor_unitario = :valor_unitario,
                        custo_unitario = :custo_unitario,
                        estoque_minimo = :estoque_minimo,
                        observacoes = :observacoes
                        WHERE id = :id");
                    $stmt->bindParam(':id', $produto['id'], PDO::PARAM_INT);
                    $mensagem = 'atualizado';
                } else {
                    // Inserir
                    $stmt = $db->prepare("INSERT INTO produtos (
                        codigo, descricao, categoria_id, unidade, valor_unitario, custo_unitario, estoque_minimo, observacoes
                    ) VALUES (
                        :codigo, :descricao, :categoria_id, :unidade, :valor_unitario, :custo_unitario, :estoque_minimo, :observacoes
                    )");
                    $mensagem = 'cadastrado';
                }
                
                // Bind de parâmetros
                $stmt->bindParam(':codigo', $produto['codigo']);
                $stmt->bindParam(':descricao', $produto['descricao']);
                $stmt->bindParam(':categoria_id', $produto['categoria_id']);
                $stmt->bindParam(':unidade', $produto['unidade']);
                $stmt->bindParam(':valor_unitario', $produto['valor_unitario']);
                $stmt->bindParam(':custo_unitario', $produto['custo_unitario']);
                $stmt->bindParam(':estoque_minimo', $produto['estoque_minimo']);
                $stmt->bindParam(':observacoes', $produto['observacoes']);
                
                $stmt->execute();
                
                if ($produto['id'] == 0) {
                    // Se for um novo produto, pegar o ID gerado
                    $produto_id = $db->lastInsertId();
                    
                    // Redirecionar para a página de entrada de estoque com o produto já selecionado
                    header("Location: estoque_entrada.php?produto_id={$produto_id}&novo=1");
                } else {
                    // Se for edição, redirecionar para a listagem de produtos
                    header("Location: produtos.php?mensagem={$mensagem}");
                }
                exit;
                
            } catch (Exception $e) {
                $erro = 'Erro ao salvar produto: ' . $e->getMessage();
            }
        }
    }
}

// Agora podemos incluir o header, depois de qualquer possível redirecionamento
require_once('includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-box me-2"></i><?php echo $titulo; ?></h1>
    <a href="produtos.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Voltar
    </a>
</div>

<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo $erro; ?></div>
<?php endif; ?>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?php echo $sucesso; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <i class="fas fa-box-open me-2"></i>Formulário de Produto
    </div>
    <div class="card-body">
        <form method="post" action="produto_form.php" id="formProduto">
            <input type="hidden" name="id" value="<?php echo $produto['id']; ?>">
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="codigo" class="form-label">Código</label>
                    <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $produto['codigo']; ?>">
                </div>
                <div class="col-md-9">
                    <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="descricao" name="descricao" value="<?php echo $produto['descricao']; ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="categoria_id" class="form-label">Categoria <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select class="form-select" id="categoria_id" name="categoria_id" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($produto['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="unidade" class="form-label">Unidade <span class="text-danger">*</span></label>
                    <select class="form-select" id="unidade" name="unidade" required>
                        <option value="unidade" <?php echo $produto['unidade'] == 'unidade' || $produto['unidade'] == 'UN' ? 'selected' : ''; ?>>Unidade</option>
                        <option value="metro" <?php echo $produto['unidade'] == 'metro' || $produto['unidade'] == 'MT' ? 'selected' : ''; ?>>Metro</option>
                        <option value="metro quadrado" <?php echo $produto['unidade'] == 'metro quadrado' || $produto['unidade'] == 'M2' ? 'selected' : ''; ?>>Metro Quadrado</option>
                        <option value="kilograma" <?php echo $produto['unidade'] == 'kilograma' || $produto['unidade'] == 'KG' ? 'selected' : ''; ?>>Kilograma</option>
                        <option value="peça" <?php echo $produto['unidade'] == 'peça' || $produto['unidade'] == 'PC' ? 'selected' : ''; ?>>Peça</option>
                        <option value="caixa" <?php echo $produto['unidade'] == 'caixa' || $produto['unidade'] == 'CX' ? 'selected' : ''; ?>>Caixa</option>
                        <option value="par" <?php echo $produto['unidade'] == 'par' || $produto['unidade'] == 'PR' ? 'selected' : ''; ?>>Par</option>
                        <option value="hora" <?php echo $produto['unidade'] == 'hora' || $produto['unidade'] == 'HR' ? 'selected' : ''; ?>>Hora</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="valor_unitario" class="form-label">Valor Unitário <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control" id="valor_unitario" name="valor_unitario" value="<?php echo !empty($produto['valor_unitario']) ? number_format((float)$produto['valor_unitario'], 2, ',', '.') : '0,00'; ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="custo_unitario" class="form-label">Custo Unitário</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control money-mask" id="custo_unitario" name="custo_unitario" value="<?php echo !empty($produto['custo_unitario']) ? number_format((float)$produto['custo_unitario'], 2, ',', '.') : '0,00'; ?>">
                    </div>
                    <small class="text-muted">Valor de custo para cálculo de lucro</small>
                </div>
                <div class="col-md-4">
                    <label for="estoque_minimo" class="form-label">Estoque Mínimo</label>
                    <div class="input-group">
                        <input type="text" class="form-control number-only" id="estoque_minimo" name="estoque_minimo" value="<?php echo !empty($produto['estoque_minimo']) ? $produto['estoque_minimo'] : '0'; ?>">
                        <span class="input-group-text"><?php echo $produto['unidade']; ?></span>
                    </div>
                    <small class="text-muted">Quantidade para alertas de estoque baixo</small>
                </div>
                <div class="col-md-4">
                    <label for="margem_lucro" class="form-label">Margem de Lucro</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="margem_lucro" disabled>
                        <span class="input-group-text">%</span>
                    </div>
                    <small id="lucro_valor" class="text-success">Lucro estimado: R$ 0,00</small>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo $produto['observacoes']; ?></textarea>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?php echo $modo == 'cadastrar' ? 'Cadastrar' : 'Atualizar'; ?> Produto
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Formatação de campos de moeda
    const valorUnitario = document.getElementById('valor_unitario');
    const custoUnitario = document.getElementById('custo_unitario');
    const margemLucro = document.getElementById('margem_lucro');
    const lucroValor = document.getElementById('lucro_valor');
    const estoqueMinimo = document.getElementById('estoque_minimo');
    const form = document.getElementById('formProduto');
    
    // Função para formatar campos de moeda
    function formatarMoeda(input) {
        let valor = input.value.replace(/\D/g, '');
        
        if (valor.length === 0) {
            input.value = '';
            return 0;
        }
        
        // Converter para formato de moeda
        const valorNumerico = parseInt(valor) / 100;
        input.value = valorNumerico.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return valorNumerico;
    }
    
    // Função para calcular margem de lucro
    function calcularMargemLucro() {
        const valorVenda = parseFloat(valorUnitario.value.replace('.', '').replace(',', '.')) || 0;
        const valorCusto = parseFloat(custoUnitario.value.replace('.', '').replace(',', '.')) || 0;
        
        if (valorCusto > 0 && valorVenda > 0) {
            const lucro = valorVenda - valorCusto;
            const percentual = (lucro / valorCusto) * 100;
            
            margemLucro.value = percentual.toFixed(2).replace('.', ',');
            lucroValor.textContent = 'Lucro estimado: R$ ' + lucro.toFixed(2).replace('.', ',');
            
            // Mudar cor conforme margem de lucro
            if (percentual < 20) {
                lucroValor.className = 'text-danger';
            } else if (percentual < 40) {
                lucroValor.className = 'text-warning';
            } else {
                lucroValor.className = 'text-success';
            }
        } else {
            margemLucro.value = '0,00';
            lucroValor.textContent = 'Lucro estimado: R$ 0,00';
            lucroValor.className = 'text-muted';
        }
    }
    
    // Aplicar formatação aos campos de moeda
    valorUnitario.addEventListener('input', function(e) {
        formatarMoeda(this);
        calcularMargemLucro();
    });
    
    custoUnitario.addEventListener('input', function(e) {
        formatarMoeda(this);
        calcularMargemLucro();
    });
    
    // Formatar campo de estoque mínimo (apenas números)
    estoqueMinimo.addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '');
    });
    
    // Calcular margem de lucro inicial se os valores já estiverem preenchidos
    calcularMargemLucro();
    
    // Atualizar texto da unidade de medida quando mudar a unidade
    const unidadeSelect = document.getElementById('unidade');
    const unidadeTexto = document.querySelector('#estoque_minimo + .input-group-text');
    
    unidadeSelect.addEventListener('change', function() {
        unidadeTexto.textContent = this.value;
    });

    // Validação do formulário
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
</script>

<!-- Modal para adicionar nova categoria -->
<div class="modal fade" id="modalCategoria" tabindex="-1" aria-labelledby="modalCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalCategoriaLabel">Nova Categoria</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="nova_categoria" class="form-label">Nome da Categoria</label>
                    <input type="text" class="form-control" id="nova_categoria" placeholder="Digite o nome da categoria">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarCategoria">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar nova categoria
    document.getElementById('btnSalvarCategoria').addEventListener('click', function() {
        const novaCategoria = document.getElementById('nova_categoria').value.trim();
        if (novaCategoria) {
            // Enviar via AJAX para criar nova categoria
            fetch('ajax/salvar_categoria.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'nome=' + encodeURIComponent(novaCategoria)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Adicionar ao select
                    const select = document.getElementById('categoria_id');
                    const option = new Option(novaCategoria, data.id, true, true);
                    select.appendChild(option);
                    
                    // Fechar modal
                    bootstrap.Modal.getInstance(document.getElementById('modalCategoria')).hide();
                    
                    // Limpar campo
                    document.getElementById('nova_categoria').value = '';
                } else {
                    alert('Erro ao adicionar categoria: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar a requisição');
            });
        } else {
            alert('Por favor, digite o nome da categoria');
        }
    });
});
</script>

<?php
require_once('includes/footer.php');
?>
