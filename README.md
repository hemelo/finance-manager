<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Finance Manager

Este é um aplicativo de gerenciamento financeiro pessoal construído com o framework Laravel. Ele permite que os usuários gerenciem suas contas bancárias, cartões de crédito/débito, transações, faturas e assinaturas, com recursos para cálculo de cashback e notificações.

## Funcionalidades Principais ✨

* **Autenticação de Usuário**: Sistema de registro e login seguro.
* **Gerenciamento de Contas Bancárias**:
    * Adicionar, visualizar e gerenciar contas bancárias.
    * Rastrear saldos de contas.
* **Gerenciamento de Cartões**:
    * Adicionar e gerenciar cartões de crédito/débito vinculados a contas bancárias.
    * Definir limites, datas de vencimento e taxas de cashback.
    * Visualizar saldo real disponível no cartão.
* **Gerenciamento de Transações**:
    * Registrar transações de diferentes tipos:
        * Compras com cartão.
        * Depósitos bancários.
        * Saques bancários.
    * Vincular transações a cartões ou contas bancárias.
    * Suporte a transações parceladas.
* **Gerenciamento de Faturas**:
    * Geração automática de faturas mensais para cartões.
    * Cálculo de cashback por fatura ou por transação, dependendo da configuração do cartão.
    * Acompanhamento do status das faturas (aberta, paga).
* **Gerenciamento de Assinaturas**:
    * Adicionar, visualizar, editar e cancelar serviços de assinatura recorrentes (ex: Netflix, academia).
    * Vincular assinaturas a cartões específicos.
    * Definir frequência (mensal, anual), valor e data de início.
    * Geração automática de transações para assinaturas ativas na data de cobrança.
    * Pausar e retomar assinaturas.
* **Cálculo de Cashback**:
    * Sistema flexível para calcular cashback com base na taxa do cartão, seja por transação individual ou sobre o valor total da fatura.
* **Notificações**:
    * Notificações por e-mail e no sistema sobre:
        * Faturas próximas do vencimento (3 dias de antecedência).
        * Cobranças de assinaturas futuras (3 dias de antecedência).
* **Tarefas Agendadas**:
    * Geração diária de faturas.
    * Geração diária de transações de assinaturas.
    * Envio diário de notificações de faturas e assinaturas.

---

## Tecnologias Utilizadas 🛠️

* **Framework**: Laravel v12.0
* **Linguagem**: PHP v8.2
* **Frontend**:
    * Vite
    * Tailwind CSS v4.0
    * Blade Templates
    * Axios (para requisições JavaScript)
* **Banco de Dados**: SQLite (padrão), MySQL, PostgreSQL, SQL Server (configurável)
* **Filas**: Suporte para processamento em background (configurável, padrão: database)
* **Agendamento de Tarefas**: Laravel Scheduler

---

## Instalação e Configuração 🚀

1.  **Clone o repositório:**
    ```bash
    git clone https://SEU_REPOSITORIO_AQUI.git
    cd finance-manager
    ```

2.  **Instale as dependências do PHP:**
    ```bash
    composer install
    ```

3.  **Instale as dependências do JavaScript:**
    ```bash
    npm install
    ```

4.  **Configure o ambiente:**
    * Copie o arquivo de exemplo `.env.example` para `.env`:
        ```bash
        cp .env.example .env
        ```
    * Gere a chave da aplicação:
        ```bash
        php artisan key:generate
        ```
    * Configure as credenciais do banco de dados e outras configurações de ambiente no arquivo `.env`.
        * Por padrão, o projeto está configurado para usar SQLite e criará o arquivo `database/database.sqlite`.

5.  **Execute as Migrations do Banco de Dados:**
    ```bash
    php artisan migrate
    ```

6.  **(Opcional) Popule o banco de dados com dados de teste:**
    * O seeder padrão cria um usuário de teste.
    ```bash
    php artisan db:seed
    ```

7.  **Compile os assets do frontend:**
    * Para desenvolvimento (com hot-reloading):
        ```bash
        npm run dev
        ```
    * Para produção:
        ```bash
        npm run build
        ```

8.  **Inicie o servidor de desenvolvimento:**
    * O `composer.json` inclui um script `dev` conveniente que inicia o servidor, o listener da fila, o pail (logs) e o Vite:
        ```bash
        composer run dev
        ```
    * Ou, para iniciar apenas o servidor PHP:
        ```bash
        php artisan serve
        ```

9.  **Configurar o Agendador de Tarefas (Cron):**
    * Para que as tarefas agendadas (geração de faturas, transações de assinatura, notificações) funcionem automaticamente, adicione a seguinte entrada Cron ao seu servidor:
        ```cron
        * * * * * cd /caminho-para-seu-projeto && php artisan schedule:run >> /dev/null 2>&1
        ```
      (Ajuste `/caminho-para-seu-projeto` para o caminho real do seu projeto.)

---

## Rotas Principais (Web) 🕸️

Todas as rotas principais requerem autenticação.

* **Contas Bancárias**: `Route::resource('bank_accounts', BankAccountController::class);`
    * `/bank_accounts` (GET, POST)
    * `/bank_accounts/create` (GET)
    * `/bank_accounts/{bank_account}` (GET, PUT/PATCH, DELETE)
    * `/bank_accounts/{bank_account}/edit` (GET)
* **Cartões**: `Route::resource('cards', CardController::class);`
    * (As funcionalidades do controller ainda não foram implementadas)
* **Transações**: `Route::resource('transactions', TransactionController::class);`
    * `/transactions` (GET, POST)
    * `/transactions/create` (GET)
* **Faturas**: `Route::resource('invoices', InvoiceController::class);`
    * (As funcionalidades do controller ainda não foram implementadas)
* **Assinaturas**: (Rotas implícitas via controller, mas não explicitamente um `Route::resource` em `web.php`, mas o `SubscriptionController` possui os métodos de um resource)
    * `/subscriptions` (GET, POST)
    * `/subscriptions/create` (GET)
    * `/subscriptions/{subscription}` (GET, PUT/PATCH, DELETE)
    * `/subscriptions/{subscription}/edit` (GET)
    * Métodos customizados para pausar (`/subscriptions/{subscription}/pause`) e retomar (`/subscriptions/{subscription}/resume`) provavelmente seriam definidos separadamente se não usando resources.

---

## Comandos Artisan Personalizados 👨‍💻

Além dos comandos padrão do Laravel, este projeto inclui:

* `php artisan app:invoices:generate`
    * Gera faturas para cartões com base nas transações do mês.
    * Agendado para rodar diariamente.
* `php artisan app:invoices:notify-due`
    * Envia notificações para faturas que vencem em breve.
    * Agendado para rodar diariamente às 08:00.
* `php artisan app:subscriptions:generate-transactions`
    * Gera transações para assinaturas ativas.
    * Agendado para rodar diariamente.
* `php artisan app:subscriptions:send-due-notifications`
    * Envia notificações para assinaturas com cobrança próxima.
    * Agendado para rodar diariamente às 09:00.
* `php artisan inspire`
    * Exibe uma citação inspiradora.

---

## Estrutura do Banco de Dados (Principais Tabelas) 🗄️

* **`users`**: Armazena informações dos usuários.
* **`bank_accounts`**: Contas bancárias dos usuários, incluindo nome do banco, número da conta e saldo.
* **`cards`**: Cartões dos usuários, vinculados a contas bancárias, com informações como nome, bandeira, limite, data de vencimento e taxa de cashback.
* **`transactions`**: Todas as transações financeiras, podendo ser vinculadas a cartões, faturas, assinaturas e contas bancárias. Inclui valor, data, descrição, tipo (compra com cartão, depósito, saque) e parcelas.
* **`invoices`**: Faturas mensais dos cartões, com valor total, referência do mês, data de vencimento e status.
* **`subscriptions`**: Assinaturas recorrentes dos usuários, com nome, categoria, valor, frequência, datas de início e próxima cobrança, e status.
* **`cashback`**: Registros de cashback gerados, vinculados a cartões, transações (opcionalmente) e faturas.
* **`notifications`**: Tabela padrão do Laravel para armazenar notificações do sistema.

---

## Contribuições

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues ou pull requests.

## Licença

Este projeto é um software de código aberto licenciado sob a [Licença MIT](https://opensource.org/licenses/MIT).
