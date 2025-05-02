# Sistema de Gestão - Sandro Calhas LTDA

Este é o sistema de gestão para a empresa Sandro Calhas LTDA, que inclui funcionalidades para gerenciamento de orçamentos, vendas, clientes, estoque, agendamentos e muito mais.

## Estrutura do Projeto

O projeto foi reorganizado para seguir uma estrutura mais modular e organizada:

```
/
├── modules/                  # Módulos do sistema
│   ├── agendamentos/         # Gerenciamento de agendamentos
│   ├── caixa/                # Controle de caixa
│   ├── clientes/             # Cadastro e gestão de clientes
│   ├── colaboradores/        # Gestão de colaboradores
│   ├── estoque/              # Controle de estoque e produtos
│   ├── financeiro/           # Gestão financeira
│   ├── orcamentos/           # Orçamentos
│   ├── relatorios/           # Relatórios diversos
│   ├── sistema/              # Configurações do sistema
│   ├── usuarios/             # Gestão de usuários
│   └── vendas/               # Vendas e faturamento
├── includes/                 # Arquivos de inclusão
│   ├── auth.php              # Autenticação
│   ├── config.php            # Configurações
│   ├── db.php                # Conexão com banco de dados
│   ├── functions.php         # Funções utilitárias
│   ├── header.php            # Cabeçalho das páginas
│   ├── footer.php            # Rodapé das páginas
│   ├── paths.php             # Definição de caminhos
│   └── notificacoes.php      # Sistema de notificações
├── css/                      # Arquivos CSS
├── js/                       # Arquivos JavaScript
├── img/                      # Imagens
├── ajax/                     # Endpoints AJAX
├── database/                 # Scripts e backups de banco de dados
│   ├── backups/              # Backups do banco
│   └── scripts/              # Scripts SQL
├── notificacoes/             # Sistema de notificações
├── utils/                    # Utilitários diversos
├── site/                     # Site público
├── .htaccess                 # Configuração de URLs amigáveis
└── index.php                 # Arquivo principal
```

## URLs Amigáveis

O sistema agora utiliza URLs amigáveis para facilitar a navegação:

- `/dashboard` - Painel principal
- `/clientes` - Lista de clientes
- `/cliente/novo` - Cadastrar novo cliente
- `/cliente/editar/123` - Editar cliente com ID 123
- `/orcamentos` - Lista de orçamentos
- `/orcamento/novo` - Novo orçamento
- `/orcamento/visualizar/123` - Visualizar orçamento com ID 123
- `/vendas` - Lista de vendas
- `/produtos` - Lista de produtos
- `/estoque` - Controle de estoque
- `/agendamentos` - Agenda de serviços
- `/colaboradores` - Lista de colaboradores
- `/caixa` - Controle de caixa
- `/relatorios/vendas` - Relatório de vendas
- `/configuracoes` - Configurações do sistema

## Requisitos do Sistema

- PHP 7.4 ou superior
- PostgreSQL 12 ou superior
- Apache com mod_rewrite habilitado

## Instalação

1. Clone o repositório
2. Configure o banco de dados em `includes/config.local.php`
3. Importe o banco de dados usando os scripts em `database/scripts/`
4. Configure o servidor web para apontar para o diretório raiz do projeto
5. Certifique-se que o mod_rewrite está habilitado no Apache

## Acesso ao Sistema

- URL: https://seu-dominio.com/
- Usuário padrão: admin
- Senha padrão: admin123

*Lembre-se de alterar a senha padrão após o primeiro acesso.*