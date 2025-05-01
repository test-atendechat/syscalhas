<?php
require_once('../includes/config.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Calhas e Rufos de Qualidade</title>
    
    <!-- Meta tags para SEO -->
    <meta name="description" content="Serviços de qualidade em instalação e manutenção de calhas e rufos. Atendemos Descalvado, Porto Ferreira e região.">
    <meta name="keywords" content="calhas, rufos, serviços, construção, goteiras, chuva, descalvado, porto ferreira">
    
    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL; ?>site/index.php">
    <meta property="og:title" content="<?php echo APP_NAME; ?> - Calhas e Rufos de Qualidade">
    <meta property="og:description" content="Serviços de qualidade em instalação e manutenção de calhas e rufos. Atendemos Descalvado, Porto Ferreira e região.">
    <meta property="og:image" content="<?php echo BASE_URL; ?>img/social/orcamento_share.svg">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/styles.css" rel="stylesheet">
    <style>
        body {
            padding-top: 0;
            background-color: #f8f9fa;
        }
        .hero-section {
            background: linear-gradient(rgba(13, 110, 253, 0.8), rgba(13, 110, 253, 0.9)), url('../img/social/calhas-bg.svg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            margin-bottom: 40px;
        }
        .hero-section h1 {
            font-size: 2.8rem;
            font-weight: 700;
        }
        .service-card {
            transition: transform 0.3s;
            margin-bottom: 20px;
            height: 100%;
        }
        .service-card:hover {
            transform: translateY(-5px);
        }
        .service-icon {
            font-size: 2.5rem;
            color: #0d6efd;
            margin-bottom: 15px;
        }
        .whatsapp-btn {
            background-color: #25D366;
            color: white;
            border: none;
            transition: all 0.3s;
        }
        .whatsapp-btn:hover {
            background-color: #128C7E;
            color: white;
            transform: scale(1.05);
        }
        .btn-primary {
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: scale(1.05);
        }
        .footer {
            background-color: #343a40;
            color: white;
            padding: 40px 0;
            margin-top: 40px;
        }
        .footer a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        .footer a:hover {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="mb-4"><?php echo APP_NAME; ?></h1>
            <p class="lead mb-5">Soluções profissionais em calhas e rufos para sua casa ou empresa</p>
            <div class="d-flex justify-content-center flex-wrap gap-3">
                <a href="solicitar_orcamento.php" class="btn btn-light btn-lg px-4 py-3">
                    <i class="fas fa-calculator me-2"></i>Solicitar Orçamento
                </a>
                <a href="https://api.whatsapp.com/send?phone=5519992226892&text=Ol%C3%A1%2C%20gostaria%20de%20informa%C3%A7%C3%B5es%20sobre%20servi%C3%A7os%20de%20calhas%20em%20Descalvado." target="_blank" class="btn whatsapp-btn btn-lg px-4 py-3">
                    <i class="fab fa-whatsapp me-2"></i>WhatsApp Descalvado
                </a>
                <a href="https://api.whatsapp.com/send?phone=5519992620970&text=Ol%C3%A1%2C%20gostaria%20de%20informa%C3%A7%C3%B5es%20sobre%20servi%C3%A7os%20de%20calhas%20em%20Porto%20Ferreira." target="_blank" class="btn whatsapp-btn btn-lg px-4 py-3">
                    <i class="fab fa-whatsapp me-2"></i>WhatsApp Porto Ferreira
                </a>
            </div>
        </div>
    </section>

    <!-- Serviços -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Nossos Serviços</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="card service-card shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-umbrella"></i>
                            </div>
                            <h4>Instalação de Calhas</h4>
                            <p class="text-muted">Instalação de calhas novas com materiais de alta qualidade e acabamento profissional.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card service-card shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-tools"></i>
                            </div>
                            <h4>Manutenção e Reparo</h4>
                            <p class="text-muted">Serviços de manutenção e reparo em calhas e rufos existentes para prevenir problemas.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card service-card shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="service-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <h4>Rufos e Acabamentos</h4>
                            <p class="text-muted">Instalação de rufos e acabamentos para proteção e beleza da sua construção.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-primary text-white py-5 text-center">
        <div class="container">
            <h2 class="mb-4">Solicite um orçamento sem compromisso</h2>
            <p class="lead mb-4">Nossa equipe está pronta para lhe atender e oferecer as melhores soluções para suas necessidades.</p>
            <a href="solicitar_orcamento.php" class="btn btn-light btn-lg">
                <i class="fas fa-calendar-alt me-2"></i>Agendar Visita Técnica
            </a>
        </div>
    </section>

    <!-- Por que nos escolher -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Por Que Nos Escolher</h2>
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="text-center">
                        <div class="service-icon">
                            <i class="fas fa-medal"></i>
                        </div>
                        <h5>Referência há mais de 15 anos</h5>
                        <p class="text-muted">Excelência e profissionalismo reconhecidos em toda a região.</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="text-center">
                        <div class="service-icon">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <h5>Serviço bem feito</h5>
                        <p class="text-muted">Não escolhemos o serviço, escolhemos fazer bem feito do início ao fim.</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="text-center">
                        <div class="service-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h5>Garantias Personalizadas</h5>
                        <p class="text-muted">Oferecemos garantias especiais para cada serviço executado.</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="text-center">
                        <div class="service-icon">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <h5>Produtos de Qualidade</h5>
                        <p class="text-muted">Trabalhamos com as melhores chapas e materiais para calhas do mercado.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3"><?php echo APP_NAME; ?></h5>
                    <p>Soluções profissionais em calhas e rufos para sua casa ou empresa. Atendemos Descalvado, Porto Ferreira e região.</p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3">Contato</h5>
                    <p><i class="fas fa-phone me-2"></i> Descalvado: (19) 99222-6892</p>
                    <p><i class="fas fa-phone me-2"></i> Porto Ferreira: (19) 99262-0970</p>
                    <p><i class="fas fa-envelope me-2"></i> contato@calhas.com</p>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Links Rápidos</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php"><i class="fas fa-home me-2"></i>Início</a></li>
                        <li><a href="solicitar_orcamento.php"><i class="fas fa-calculator me-2"></i>Solicitar Orçamento</a></li>
                        <li><a href="../login.php"><i class="fas fa-sign-in-alt me-2"></i>Área do Cliente</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-light">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>