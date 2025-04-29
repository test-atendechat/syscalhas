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
                                <a class="nav-link dropdown-toggle <?php echo (strpos($pagina_atual, 'caixa') !== false || strpos($pagina_atual, 'vendas') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-cash-register me-1"></i>Caixa e Vendas
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="caixa.php"><i class="fas fa-money-bill-wave me-1"></i>Caixa</a></li>
                                    <li><a class="dropdown-item" href="vendas.php"><i class="fas fa-shopping-cart me-1"></i>Histórico de Vendas</a></li>
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