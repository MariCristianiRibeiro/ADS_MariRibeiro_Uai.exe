# Requisitos base do projeto UAI.exe

## 1. Visão geral
Este projeto já possui a base do Laravel com autenticação e banco PostgreSQL funcionando. A partir dessa base, a próxima etapa é desenvolver a parte funcional do sistema que não depende da automação do N8N.

O escopo inicial inclui:
- autenticação de usuários
- dashboard principal
- perfil do usuário
- organização de dados do sistema
- preparação para integração futura com N8N

## 2. Escopo fora do N8N
A integração com N8N será tratada como etapa futura. Nesta fase, o sistema deve funcionar de forma independente e com estrutura pronta para receber automações externas.

## 3. Requisitos funcionais

### 3.1 Autenticação
RF-01: O sistema deve permitir o cadastro de usuários com nome, e-mail e senha.
RF-02: O sistema deve permitir login com e-mail e senha.
RF-03: O sistema deve permitir logout seguro.
RF-04: O sistema deve impedir acesso a páginas restritas sem autenticação.
RF-05: O sistema deve permitir recuperação de senha.
RF-06: O sistema deve validar e-mail e senha antes de autenticar.

### 3.2 Dashboard
RF-07: Após autenticar, o usuário deve acessar um dashboard inicial.
RF-08: O dashboard deve conter informações gerais do sistema e navegação central.
RF-09: O dashboard deve mostrar acesso a funcionalidades principais do sistema.

### 3.3 Perfil do usuário
RF-10: O usuário deve poder visualizar suas informações.
RF-11: O usuário deve poder atualizar seus dados cadastrais.
RF-12: O sistema deve permitir alteração de senha.
RF-13: O sistema deve validar dados de entrada antes de salvar.

### 3.4 Gestão de registros do sistema
RF-14: O sistema deve permitir cadastro e consulta de registros principais do negócio.
RF-15: O sistema deve permitir edição de registros já criados.
RF-16: O sistema deve permitir exclusão lógica ou física, conforme regra definida.
RF-17: O sistema deve exibir mensagens de sucesso e erro adequadas.

### 3.5 Preparação para automação
RF-18: O sistema deve expor endpoints ou estrutura de dados que possam ser consumidos por automações externas.
RF-19: O sistema deve armazenar eventos e ações realizadas pelo usuário para integração futura.
RF-20: O sistema deve registrar logs simples das operações do sistema.

## 4. Requisitos não funcionais

RNF-01: O sistema deve utilizar Laravel como base de desenvolvimento.
RNF-02: O banco de dados principal deve ser PostgreSQL.
RNF-03: A autenticação deve seguir padrões seguros do Laravel.
RNF-04: A aplicação deve funcionar em ambiente local e em ambiente de testes.
RNF-05: A interface deve ser responsiva.
RNF-06: Os dados devem ser validados no backend.
RNF-07: O sistema deve conter mensagens amigáveis para erros e validações.
RNF-08: O código deve seguir organização por módulos e controllers.

## 5. Histórias de usuário

### História 1 - Cadastro e login
Como usuário do sistema, quero me cadastrar e fazer login para acessar a área protegida.

### História 2 - Dashboard
Como usuário autenticado, quero ver o painel principal para acessar rapidamente as áreas importantes do sistema.

### História 3 - Perfil
Como usuário, quero atualizar meu perfil para manter meus dados corretos.

### História 4 - Gestão de dados
Como usuário, quero registrar e consultar os dados principais do sistema para acompanhar operações.

### História 5 - Integração futura
Como sistema, quero ficar preparado para receber automações externas para ampliar os processos sem alterar a base principal.

## 6. Backlog sugerido

### Sprint 1
- autenticação completa
- dashboard inicial
- perfil do usuário
- ajustes de layout
- testes básicos de login e registro

### Sprint 2
- cadastro de módulos do negócio
- listagem, edição e exclusão
- relatórios simples
- filtros e pesquisa

### Sprint 3
- logs de ações
- API para integração externa
- estrutura para comunicação com N8N

### Sprint 4
- automações do N8N integradas
- ajustes finais
- deploy e testes de homologação

## 7. Critérios de aceite iniciais
- usuário consegue registrar-se
- usuário consegue logar e sair
- usuário autenticado consegue acessar dashboard
- usuário consegue atualizar perfil
- sistema usa PostgreSQL corretamente
- as páginas privadas exigem autenticação

## 8. Observação
Em razão do documento Word não estar sendo lido de forma confiável no ambiente atual, esta versão foi estruturada com base no projeto Laravel já existente e no escopo funcional que não depende do N8N. Assim que o documento for disponibilizado em texto ou em formato legível, este documento pode ser refinado para refletir exatamente os requisitos finais.
