# Instruções para Instalação e Correção do Banco de Dados

Este diretório contém arquivos para a instalação e correção do banco de dados PostgreSQL do Sistema de Orçamentos para Calhas.

## Arquivos Disponíveis

1. **instalar_postgresql_corrigido.sql** - Script completo para instalação do banco de dados com correções para os problemas de valores booleanos e referências circulares.

2. **correcoes_instalacao_banco.sql** - Script com correções para aplicar em um banco existente que esteja apresentando problemas com valores booleanos nulos nas permissões.

3. **db_backup_completo_com_correcoes.sql** - Backup completo do banco de dados atual, incluindo estrutura e dados.

## Opções de Instalação

### Opção 1: Instalação Limpa (Recomendado para novos sistemas)

1. Execute o script completo corrigido:
   ```
   psql -U seu_usuario -d seu_banco -f instalar_postgresql_corrigido.sql
   ```

2. Este script criará todas as tabelas e inserirá dados iniciais como usuários e configurações.

### Opção 2: Correção de um Banco de Dados Existente

Se você já tem um banco de dados instalado mas está tendo problemas com valores booleanos nulos ou outros erros:

1. Execute o script de correções:
   ```
   psql -U seu_usuario -d seu_banco -f correcoes_instalacao_banco.sql
   ```

2. Este script corrigirá apenas os problemas específicos sem afetar seus dados existentes.

### Opção 3: Restauração Completa do Backup

Para restaurar o banco de dados completo a partir do backup:

1. Execute o comando:
   ```
   psql -U seu_usuario -d seu_banco -f db_backup_completo_com_correcoes.sql
   ```

2. Este comando substituirá completamente o banco de dados atual pelo conteúdo do backup.

## Usuários Padrão

Após a instalação, os seguintes usuários estarão disponíveis:

1. **Administrador**
   - Email: admin@admin.com
   - Senha: admin123
   - Acesso total a todas as funcionalidades

2. **Atendente**
   - Email: atendente@exemplo.com
   - Senha: caixa123
   - Acesso a orçamentos, vendas, clientes e caixa

3. **Financeiro**
   - Email: financeiro@exemplo.com
   - Senha: financeiro123
   - Acesso a relatórios financeiros, contas a pagar e caixa

## Verificação da Instalação

Para verificar se a instalação foi bem-sucedida:

1. Tente fazer login com as credenciais de administrador.
2. Verifique se pode acessar todas as funcionalidades sem erros.
3. Teste a criação de um orçamento e a adição de produtos.

## Solução de Problemas Comuns

1. **Erro de conexão com o banco de dados:**
   - Verifique se as credenciais em includes/db.php estão corretas.
   - Certifique-se de que o servidor PostgreSQL está rodando.

2. **Erro de permissão negada:**
   - Verifique se o usuário do PostgreSQL tem permissões para criar tabelas.

3. **Valores NULL em campos booleanos:**
   - Execute o script de correções para atualizar todos os valores nulos para FALSE.

4. **Problemas com o controle de caixa:**
   - Verifique se a tabela caixa_controle foi criada corretamente.
   - Tente criar um novo registro de abertura de caixa manualmente.

## Informações Adicionais

- A senha do administrador está codificada com bcrypt.
- O sistema usa PDO para conexão com o banco de dados.
- Todas as tabelas estão configuradas com UTF-8 para suportar caracteres especiais.

Para qualquer assistência adicional, consulte a documentação completa do sistema ou entre em contato com o suporte técnico.