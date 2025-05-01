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

// Carregar configurações do banco de dados
// Valores padrão
$cor_principal = '#0d6efd'; // Cor padrão (Bootstrap primary)
$cor_secundaria = '#6c757d'; // Cor padrão (Bootstrap secondary)
$cor_aprovado = '#198754'; // Cor padrão (Bootstrap success)
$cor_pendente = '#ffc107'; // Cor padrão (Bootstrap warning)
$cor_rejeitado = '#dc3545'; // Cor padrão (Bootstrap danger)
$tema_sistema = 'light'; // Tema padrão do sistema

try {
    require_once('db.php'); // Garantir que a conexão com o banco está disponível
    
    // Buscar configurações
    $stmt = $db->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN (
        'cor_principal', 'cor_secundaria', 'cor_aprovado', 'cor_pendente', 'cor_rejeitado', 'tema_sistema'
    )");
    $stmt->execute();
    
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Aplicar as configurações personalizadas se existirem
    if (isset($configs['cor_principal'])) $cor_principal = $configs['cor_principal'];
    if (isset($configs['cor_secundaria'])) $cor_secundaria = $configs['cor_secundaria'];
    if (isset($configs['cor_aprovado'])) $cor_aprovado = $configs['cor_aprovado'];
    if (isset($configs['cor_pendente'])) $cor_pendente = $configs['cor_pendente'];
    if (isset($configs['cor_rejeitado'])) $cor_rejeitado = $configs['cor_rejeitado'];
    if (isset($configs['tema_sistema'])) $tema_sistema = $configs['tema_sistema'];
    
    // Se tema foi alterado via cookie, usar esta configuração
    if (isset($_COOKIE['tema_sistema'])) {
        $tema_sistema = $_COOKIE['tema_sistema'];
    }
} catch (Exception $e) {
    // Silenciar erro e usar configurações padrão
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="<?php echo $tema_sistema; ?>">
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
    <!-- Estilos personalizados -->
    <link href="css/styles.css" rel="stylesheet">
    <?php if ($pagina_atual == 'dashboard.php'): ?>
    <link href="css/dashboard.css" rel="stylesheet">
    <?php endif; ?>
    
    <!-- Estilos personalizados dinâmicos baseados na configuração -->
    <style>
        :root {
            --cor-principal: <?php echo $cor_principal; ?>;
            --cor-principal-hover: <?php echo adjustBrightness($cor_principal, -15); ?>;
            --cor-principal-active: <?php echo adjustBrightness($cor_principal, -20); ?>;
            
            /* Tema claro (padrão) */
            --bg-color: #f8f9fa;
            --text-color: #212529;
            --card-bg: #ffffff;
            --card-border: #dee2e6;
            --input-bg: #ffffff;
            --input-border: #ced4da;
            --table-stripe: rgba(0, 0, 0, 0.05);
            --hover-bg: rgba(0, 0, 0, 0.075);
            --border-color: #dee2e6;
            --shadow-color: rgba(0, 0, 0, 0.15);
        }
        
        /* Aplicar tema escuro se selecionado */
        <?php if ($tema_sistema == 'dark'): ?>
        :root {
            --bg-color: #212529;
            --text-color: #f8f9fa;
            --card-bg: #343a40;
            --card-border: #495057;
            --input-bg: #2b3035;
            --input-border: #495057;
            --table-stripe: rgba(255, 255, 255, 0.05);
            --hover-bg: rgba(255, 255, 255, 0.075);
            --border-color: #495057;
            --shadow-color: rgba(0, 0, 0, 0.5);
        }
        <?php endif; ?>
        
        /* Estilo global baseado no tema */
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        
        .card {
            background-color: var(--card-bg);
            border-color: var(--card-border);
        }
        
        .form-control, .form-select {
            background-color: var(--input-bg);
            border-color: var(--input-border);
            color: var(--text-color);
        }
        
        .table {
            color: var(--text-color);
        }
        
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: var(--table-stripe);
        }
        
        .dropdown-menu {
            background-color: var(--card-bg);
            border-color: var(--card-border);
        }
        
        .dropdown-item {
            color: var(--text-color);
        }
        
        .dropdown-item:hover {
            background-color: var(--hover-bg);
        }
        
        .modal-content {
            background-color: var(--card-bg);
            color: var(--text-color);
            border-color: var(--card-border);
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
        
        /* Menu centralizado com bordas (padrão) */
        .navbar.navbar-dark.bg-primary {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .navbar-nav {
            margin: 0 auto;
            display: flex;
            justify-content: center;
        }
        
        .navbar .nav-link {
            padding: 0.7rem 1.2rem;
            margin: 0 0.3rem;
            border-radius: 5px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .navbar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .navbar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .navbar .dropdown-menu {
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
        }
        
        /* Botão alternar tema */
        .toggle-theme-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 10px;
        }
        
        .toggle-theme-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
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
    <!-- Menu centralizado com bordas -->
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
                    
                    <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('gerenciar_orcamentos')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($pagina_atual, 'orcamento') !== false) ? 'active' : ''; ?>" href="orcamentos.php">
                            <i class="fas fa-file-invoice-dollar me-1"></i>Orçamentos
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('gerenciar_clientes')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($pagina_atual, 'cliente') !== false) ? 'active' : ''; ?>" href="clientes.php">
                            <i class="fas fa-users me-1"></i>Clientes
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    // Verifica se o usuário tem acesso a pelo menos uma seção financeira
                    $mostra_financas = ($_SESSION['usuario']['nivel'] === 'admin' || 
                                       verificarPermissao('gerenciar_caixa') || 
                                       verificarPermissao('gerenciar_vendas') || 
                                       verificarPermissao('gerenciar_contas'));
                    
                    if ($mostra_financas): 
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos($pagina_atual, 'caixa') !== false || strpos($pagina_atual, 'vendas') !== false || strpos($pagina_atual, 'conta') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cash-register me-1"></i>Finanças
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('gerenciar_caixa')): ?>
                            <li><a class="dropdown-item" href="caixa.php"><i class="fas fa-money-bill-wave me-1"></i>Caixa</a></li>
                            <?php endif; ?>
                            
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('gerenciar_vendas')): ?>
                            <li><a class="dropdown-item" href="vendas.php"><i class="fas fa-shopping-cart me-1"></i>Histórico de Vendas</a></li>
                            <?php endif; ?>
                            
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('gerenciar_contas')): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="contas_pagar.php"><i class="fas fa-file-invoice me-1"></i>Contas a Pagar</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    // Verifica se o usuário tem acesso a agendamentos
                    $mostra_agendamentos = ($_SESSION['usuario']['nivel'] === 'admin' || 
                                      verificarPermissao('gerenciar_agendamentos'));
                    
                    if ($mostra_agendamentos): 
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos($pagina_atual, 'agendamento') !== false || strpos($pagina_atual, 'instalador') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-calendar-alt me-1"></i>Agendamentos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="agendamentos.php"><i class="fas fa-calendar-check me-1"></i>Lista de Agendamentos</a></li>
                            <li><a class="dropdown-item" href="agendamento_form.php"><i class="fas fa-calendar-plus me-1"></i>Novo Agendamento</a></li>
                            <li><a class="dropdown-item" href="calendario_agendamento.php"><i class="fas fa-calendar-week me-1"></i>Calendário</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="instaladores.php"><i class="fas fa-hard-hat me-1"></i>Instaladores</a></li>
                            <li><a class="dropdown-item" href="indisponibilidades.php"><i class="fas fa-calendar-times me-1"></i>Indisponibilidades</a></li>
                            <li><a class="dropdown-item" href="horarios_disponiveis.php"><i class="fas fa-clock me-1"></i>Horários</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    // Verifica se o usuário tem acesso a pelo menos uma seção de produtos/estoque
                    $mostra_produtos = ($_SESSION['usuario']['nivel'] === 'admin' || 
                                      verificarPermissao('gerenciar_produtos') || 
                                      verificarPermissao('gerenciar_estoque'));
                    
                    if ($mostra_produtos): 
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos($pagina_atual, 'produto') !== false || strpos($pagina_atual, 'estoque') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-boxes me-1"></i>Produtos e Estoque
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('gerenciar_produtos')): ?>
                            <li><a class="dropdown-item" href="produtos.php"><i class="fas fa-box me-1"></i>Cadastro de Produtos</a></li>
                            <?php endif; ?>
                            
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('gerenciar_estoque')): ?>
                            <li><a class="dropdown-item" href="estoque.php"><i class="fas fa-warehouse me-1"></i>Controle de Estoque</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="estoque_entrada.php"><i class="fas fa-arrow-alt-circle-down me-1"></i>Entrada de Estoque</a></li>
                            <li><a class="dropdown-item" href="estoque_saida.php"><i class="fas fa-arrow-alt-circle-up me-1"></i>Saída de Estoque</a></li>
                            <li><a class="dropdown-item" href="estoque_movimentacoes.php"><i class="fas fa-exchange-alt me-1"></i>Movimentações</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php 
                    // Verifica se o usuário tem acesso a pelo menos uma seção de relatórios
                    $mostra_relatorios = ($_SESSION['usuario']['nivel'] === 'admin' || 
                                        verificarPermissao('visualizar_relatorios_gerais') || 
                                        verificarPermissao('visualizar_relatorios_financeiros'));
                    
                    if ($mostra_relatorios): 
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (strpos($pagina_atual, 'relatorio') !== false) ? 'active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-bar me-1"></i>Relatórios
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('visualizar_relatorios_gerais')): ?>
                            <li><a class="dropdown-item" href="relatorios_orcamentos.php"><i class="fas fa-file-invoice me-1"></i>Orçamentos</a></li>
                            <li><a class="dropdown-item" href="relatorios_vendas.php"><i class="fas fa-chart-line me-1"></i>Vendas</a></li>
                            <li><a class="dropdown-item" href="relatorios_produtos_vendidos.php"><i class="fas fa-trophy me-1"></i>Produtos mais Vendidos</a></li>
                            <li><a class="dropdown-item" href="relatorios_estoque_baixo.php"><i class="fas fa-exclamation-triangle me-1"></i>Estoque Baixo</a></li>
                            <?php endif; ?>
                            
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('gerenciar_agendamentos')): ?>
                            <li><a class="dropdown-item" href="relatorio_instaladores.php"><i class="fas fa-hard-hat me-1"></i>Desempenho de Instaladores</a></li>
                            <?php endif; ?>
                            
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('visualizar_relatorios_financeiros')): ?>
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin' || verificarPermissao('gerenciar_contas')): ?>
                            <li><a class="dropdown-item" href="relatorios_contas.php"><i class="fas fa-money-check-alt me-1"></i>Contas a Pagar</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="relatorio_financeiro.php"><i class="fas fa-chart-pie me-1"></i>Relatório Financeiro</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['usuario']['nivel'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (strpos($pagina_atual, 'usuario') !== false) ? 'active' : ''; ?>" href="usuarios.php">
                            <i class="fas fa-user-cog me-1"></i>Usuários
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center">
                    <!-- Botão de alternar tema -->
                    <button id="toggle-theme-btn" class="toggle-theme-btn me-2" title="<?php echo ($tema_sistema == 'dark') ? 'Mudar para tema claro' : 'Mudar para tema escuro'; ?>">
                        <?php if ($tema_sistema == 'dark'): ?>
                        <i class="fas fa-sun"></i>
                        <?php else: ?>
                        <i class="fas fa-moon"></i>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Usuário -->
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['usuario']['nome']; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-id-card me-1"></i>Meu Perfil</a></li>
                            <?php if ($_SESSION['usuario']['nivel'] === 'admin'): ?>
                            <li><a class="dropdown-item" href="configuracoes.php"><i class="fas fa-cog me-1"></i>Configurações</a></li>
                            <?php endif; ?>
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

    <!-- Script para alternar tema -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleThemeBtn = document.getElementById('toggle-theme-btn');
        if (toggleThemeBtn) {
            toggleThemeBtn.addEventListener('click', function() {
                // Alterna o tema sem recarregar a página
                const currentTheme = document.documentElement.getAttribute('data-bs-theme') || '<?php echo $tema_sistema; ?>';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                // Atualiza o atributo no HTML
                document.documentElement.setAttribute('data-bs-theme', newTheme);
                
                // Aplicar diretamente as variáveis CSS para modo escuro/claro
                if (newTheme === 'dark') {
                    // Aplicar tema escuro diretamente via CSS
                    document.documentElement.style.setProperty('--bg-color', '#212529');
                    document.documentElement.style.setProperty('--text-color', '#f8f9fa');
                    document.documentElement.style.setProperty('--card-bg', '#343a40');
                    document.documentElement.style.setProperty('--card-border', '#495057');
                    document.documentElement.style.setProperty('--input-bg', '#2b3035');
                    document.documentElement.style.setProperty('--input-border', '#495057');
                    document.documentElement.style.setProperty('--table-stripe', 'rgba(255, 255, 255, 0.05)');
                    document.documentElement.style.setProperty('--hover-bg', 'rgba(255, 255, 255, 0.075)');
                    document.documentElement.style.setProperty('--border-color', '#495057');
                    document.documentElement.style.setProperty('--shadow-color', 'rgba(0, 0, 0, 0.5)');
                    
                    // Atualiza o ícone para sol (mudar para claro)
                    this.innerHTML = '<i class="fas fa-sun"></i>';
                    this.title = 'Mudar para tema claro';
                } else {
                    // Aplicar tema claro diretamente via CSS
                    document.documentElement.style.setProperty('--bg-color', '#f8f9fa');
                    document.documentElement.style.setProperty('--text-color', '#212529');
                    document.documentElement.style.setProperty('--card-bg', '#ffffff');
                    document.documentElement.style.setProperty('--card-border', '#dee2e6');
                    document.documentElement.style.setProperty('--input-bg', '#ffffff');
                    document.documentElement.style.setProperty('--input-border', '#ced4da');
                    document.documentElement.style.setProperty('--table-stripe', 'rgba(0, 0, 0, 0.05)');
                    document.documentElement.style.setProperty('--hover-bg', 'rgba(0, 0, 0, 0.075)');
                    document.documentElement.style.setProperty('--border-color', '#dee2e6');
                    document.documentElement.style.setProperty('--shadow-color', 'rgba(0, 0, 0, 0.15)');
                    
                    // Atualiza o ícone para lua (mudar para escuro)
                    this.innerHTML = '<i class="fas fa-moon"></i>';
                    this.title = 'Mudar para tema escuro';
                }
                
                // Salva a preferência em cookie
                document.cookie = 'tema_sistema=' + newTheme + '; path=/; max-age=31536000'; // 1 ano
            });
        }
    });
    </script>