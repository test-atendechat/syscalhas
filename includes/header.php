<?php
// Verificar autenticação
require_once('auth.php');
verificarAutenticacao();

// Verificar se a sessão expirou
if (verificarSessaoExpirada()) {
    // Fazer logout
    fazerLogout();

    // Redirecionar para a página de login com mensagem
    header('Location: login.php?sessao=expirada');
    exit;
}

// Identificar página atual
$pagina_atual = basename($_SERVER['PHP_SELF']);
?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo (isset($titulo) && !empty($titulo)) ? $titulo . ' | ' . APP_NAME : APP_NAME; ?></title>

            <!-- Bootstrap CSS -->
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <!-- Font Awesome -->
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
            <!-- IMask para máscaras de input -->
            <script src="https://unpkg.com/imask@7.1.3/dist/imask.js"></script>
            <!-- Estilo personalizado -->
            <link href="css/styles.css" rel="stylesheet">
            
            <?php
            // Carregar cores personalizadas do banco de dados
            $cor_principal = '#0d6efd'; // Cor padrão (Bootstrap primary)
            $cor_secundaria = '#6c757d'; // Cor padrão (Bootstrap secondary)
            $cor_aprovado = '#198754'; // Cor padrão (Bootstrap success)
            $cor_pendente = '#ffc107'; // Cor padrão (Bootstrap warning)
            $cor_rejeitado = '#dc3545'; // Cor padrão (Bootstrap danger)
            
            try {
                require_once('db.php'); // Garantir que a conexão com o banco está disponível
                
                // Buscar configurações de cores
                $stmt = $db->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('cor_principal', 'cor_secundaria', 'cor_aprovado', 'cor_pendente', 'cor_rejeitado')");
                $stmt->execute();
                
                $cores = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                // Aplicar as cores personalizadas se existirem
                if (isset($cores['cor_principal'])) $cor_principal = $cores['cor_principal'];
                if (isset($cores['cor_secundaria'])) $cor_secundaria = $cores['cor_secundaria'];
                if (isset($cores['cor_aprovado'])) $cor_aprovado = $cores['cor_aprovado'];
                if (isset($cores['cor_pendente'])) $cor_pendente = $cores['cor_pendente'];
                if (isset($cores['cor_rejeitado'])) $cor_rejeitado = $cores['cor_rejeitado'];
            } catch (Exception $e) {
                // Silenciar erro e usar cores padrão
            }
            ?>
            
            <!-- Estilos personalizados dinâmicos baseados na configuração -->
            <style>
                :root {
                    --cor-principal: <?php echo $cor_principal; ?>;
                    --cor-principal-hover: <?php echo adjustBrightness($cor_principal, -15); ?>;
                    --cor-principal-active: <?php echo adjustBrightness($cor_principal, -20); ?>;
                }
                
                .bg-primary, .btn-primary, .page-item.active .page-link {
                    background-color: var(--cor-principal) !important;
                    border-color: var(--cor-principal) !important;
                }
                
                .btn-primary:hover, .btn-primary:focus {
                    background-color: var(--cor-principal-hover) !important;
                    border-color: var(--cor-principal-hover) !important;
                }
                
                .btn-primary:active {
                    background-color: var(--cor-principal-active) !important;
                    border-color: var(--cor-principal-active) !important;
                }
                
                .btn-outline-primary {
                    color: var(--cor-principal) !important;
                    border-color: var(--cor-principal) !important;
                }
                
                .btn-outline-primary:hover, .btn-outline-primary:focus {
                    background-color: var(--cor-principal) !important;
                    color: white !important;
                }
                
                .text-primary, .dashboard-icon {
                    color: var(--cor-principal) !important;
                }
                
                .table-primary {
                    background-color: rgba(var(--cor-principal-rgb), 0.1) !important;
                }
                
                .form-check-input:checked {
                    background-color: var(--cor-principal) !important;
                    border-color: var(--cor-principal) !important;
                }
                
                .form-control:focus, .form-select:focus {
                    border-color: var(--cor-principal) !important;
                    box-shadow: 0 0 0 0.25rem rgba(var(--cor-principal-rgb), 0.25) !important;
                }
                
                .nav-pills .nav-link.active {
                    background-color: var(--cor-principal) !important;
                }
                
                /* Variáveis para cor dos status */
                :root {
                    --cor-aprovado: <?php echo $cor_aprovado; ?>;
                    --cor-aprovado-texto: <?php echo adjustBrightness($cor_aprovado, 50); ?>;
                    --cor-pendente: <?php echo $cor_pendente; ?>;
                    --cor-pendente-texto: <?php echo adjustBrightness($cor_pendente, -50); ?>;
                    --cor-rejeitado: <?php echo $cor_rejeitado; ?>;
                    --cor-rejeitado-texto: <?php echo adjustBrightness($cor_rejeitado, -50); ?>;
                }
                
                /* Cores dos status */
                .status-aprovado {
                    background-color: var(--cor-aprovado) !important;
                    color: var(--cor-aprovado-texto) !important;
                }
                
                .status-pendente {
                    background-color: var(--cor-pendente) !important;
                    color: var(--cor-pendente-texto) !important;
                }
                
                .status-rejeitado {
                    background-color: var(--cor-rejeitado) !important;
                    color: var(--cor-rejeitado-texto) !important;
                }
                
                .status-pago, .status-finalizado, .status-ativo {
                    background-color: var(--cor-aprovado) !important;
                    color: var(--cor-aprovado-texto) !important;
                }
                
                .status-pago-parcial {
                    background-color: #6c757d !important;
                    color: #ffffff !important;
                }
            </style>
        </head>
        <body>
            <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
                <div class="container-fluid">
                    <a class="navbar-brand" href="dashboard.php">
                        <i class="fas fa-water me-2"></i><?php echo APP_NAME; ?>
                    </a>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="navbarMain">
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($pagina_atual == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($pagina_atual, 'orcamento') !== false) ? 'active' : ''; ?>" href="orcamentos.php">
                                    <i class="fas fa-file-invoice-dollar me-1"></i>Orçamentos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (strpos($pagina_atual, 'cliente') !== false) ? 'active' : ''; ?>" href="clientes.php">
                                    <i class="fas fa-users me-1"></i>Clientes
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo (strpos($pagina_atual, 'caixa') !== false || strpos($pagina_atual, 'vendas') !== false || strpos($pagina_atual, 'conta') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-cash-register me-1"></i>Finanças
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="caixa.php"><i class="fas fa-money-bill-wave me-1"></i>Caixa</a></li>
                                    <li><a class="dropdown-item" href="vendas.php"><i class="fas fa-shopping-cart me-1"></i>Histórico de Vendas</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="contas_pagar.php"><i class="fas fa-file-invoice me-1"></i>Contas a Pagar</a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo (strpos($pagina_atual, 'produto') !== false || strpos($pagina_atual, 'estoque') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-boxes me-1"></i>Produtos e Estoque
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="produtos.php"><i class="fas fa-box me-1"></i>Cadastro de Produtos</a></li>
                                    <li><a class="dropdown-item" href="estoque.php"><i class="fas fa-warehouse me-1"></i>Controle de Estoque</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="estoque_entrada.php"><i class="fas fa-arrow-alt-circle-down me-1"></i>Entrada de Estoque</a></li>
                                    <li><a class="dropdown-item" href="estoque_saida.php"><i class="fas fa-arrow-alt-circle-up me-1"></i>Saída de Estoque</a></li>
                                    <li><a class="dropdown-item" href="estoque_movimentacoes.php"><i class="fas fa-exchange-alt me-1"></i>Movimentações</a></li>
                                </ul>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo (strpos($pagina_atual, 'relatorio') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-chart-bar me-1"></i>Relatórios
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="relatorios_orcamentos.php"><i class="fas fa-file-invoice me-1"></i>Orçamentos</a></li>
                                    <li><a class="dropdown-item" href="relatorios_vendas.php"><i class="fas fa-chart-line me-1"></i>Vendas</a></li>
                                    <li><a class="dropdown-item" href="relatorios_contas.php"><i class="fas fa-money-check-alt me-1"></i>Contas a Pagar</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="relatorios_estoque_baixo.php"><i class="fas fa-exclamation-triangle me-1"></i>Estoque Baixo</a></li>
                                    <li><a class="dropdown-item" href="relatorios_produtos_vendidos.php"><i class="fas fa-trophy me-1"></i>Produtos mais Vendidos</a></li>
                                </ul>
                            </li>
                        </ul>

                        <div class="d-flex">
                            <div class="dropdown">
                                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['usuario_nome']; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-id-card me-1"></i>Meu Perfil</a></li>
                                    <li><a class="dropdown-item" href="configuracoes.php"><i class="fas fa-cog me-1"></i>Configurações</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Sair</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container mt-4 mb-5">
                <!-- Conteúdo da página será incluído aqui -->