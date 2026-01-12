# 🧠 Smart Todo — Sistema Pessoal de Execução Inteligente

> "Menos fricção, mais execução."

O **Smart Todo** é uma plataforma de gestão de tarefas de alta performance, desenvolvida para resolver a paralisia por escolha e a sobrecarga cognitiva. Diferente de listas tradicionais, este sistema utiliza conceitos de **energia biológica** e **isolamento de contexto** para sugerir a tarefa certa no momento certo.

## 🚀 Diferenciais de Engenharia

### 1. Execução Baseada em Energia (Energy-Based Tasks)

As tarefas não são apenas "coisas a fazer", mas exigências de carga mental. O sistema permite classificar tarefas como:

- 🌱 **Baixa Energia:** Tarefas mecânicas ou rápidas.
- ⚡ **Média Energia:** Requerem atenção moderada.
- 🧠 **Alta Concentração:** Trabalho profundo (Deep Work).

### 2. Algoritmo de Modo Foco (Smart Suggestion)

Através de um motor de decisão em SQL/PHP, o sistema analisa:

- **Urgência:** Tarefas com prazos vencidos ou próximos.
- **Importância:** Nível de prioridade definido.
- **Contexto:** Somente o que pode ser feito no ambiente atual.
  O resultado é o botão **"O que fazer agora?"**, que entrega uma única ação, eliminando distrações.

### 3. Smart Insights (Analytics)

Um módulo de análise que identifica os padrões de produtividade do usuário, sugerindo os melhores horários para tarefas complexas com base no histórico real de conclusões.

### 4. Arquitetura Multi-tenant & Mobile-First

- **Isolamento Total:** Banco de dados preparado para múltiplos usuários independentes.
- **Responsividade Radical:** Interface fluida para desktop, tablets e smartphones com menu lateral colapsável.
- **UX Refinada:** Descrições de tarefas expansíveis e feedback visual de prazos.

## 🛠️ Tech Stack

- **Backend:** PHP 8.x (Vanilla) com PDO para segurança contra SQL Injection.
- **Banco de Dados:** MySQL (Relacional com integridade referencial).
- **Frontend:** Tailwind CSS para uma interface limpa e moderna.
- **Metodologia:** Arquitetura orientada a modelos (Models) e fuso horário configurado para America/Recife.

## 📦 Instalação e Configuração

1. **Clonagem do Repositório:**
   '''bash
   git clone https://github.com/SamuelASantos/smart-todo.git
   '''
2. **Configuração do Banco de Dados:**
   Importe o arquivo /database/Schema.sql no seu servidor MySQL.
   O esquema utiliza o prefixo todo\_ para permitir coexistência em bancos compartilhados.
3. **Configuração do PHP:**
   Renomeie o arquivo config/database.example.php para config/database.php.
   Insira as credenciais do seu host local ou de produção.
4. **Configuração de Horário:**
   O projeto está pré-configurado para o fuso horário de Recife/Brasil (America/Recife). Ajuste em config/database.php se necessário.

## 🔐 Segurança

O projeto implementa:
Hash de senhas via password_hash.
Validação de sessão em todas as rotas protegidas.
Escapamento de dados (XSS Protection) e Soft Deletes.

## 📄 Licença

Este projeto está sob a licença MIT. Sinta-se à vontade para usar, modificar e distribuir.

## Desenvolvido com 🧠 por SamSantos
