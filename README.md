# 🚀 avali.ai - O Futuro da Avaliação Educacional

![avali.ai banner](https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80)

O **avali.ai** é uma plataforma revolucionária que utiliza Inteligência Artificial de ponta para automatizar a correção de provas e avaliações. Projetado para professores que buscam eficiência sem abrir mão da qualidade pedagógica, o sistema transforma horas de correção manual em minutos de insights automatizados.

## ✨ Superpoderes do avali.ai

- **📦 Processamento em Lote**: Envie centenas de provas de uma vez em um arquivo `.zip`.
- **🧠 IA Especialista**: Utiliza o motor **Gemini** (Google AI) para entender contexto, fórmulas e caligrafia.
- **📝 Transcrição Inteligente**: OCR multimodal que transcreve as respostas dos alunos fielmente.
- **💡 Feedback Pedagógico**: Não apenas dá a nota, mas explica o porquê do acerto ou erro para cada questão.
- **⏳ Fila de Processamento**: Correções em segundo plano para que você nunca precise esperar o navegador carregar.
- **📊 Painel em Tempo Real**: Acompanhe o progresso das correções ao vivo através de uma interface fluida com Livewire.
- **📄 Suporte Multiformato**: PDF, DOCX, Imagens (PNG/JPG) e até arquivos de texto puro.

## 🛠️ Tecnologias de Elite

O avali.ai é construído sobre as fundações mais robustas do desenvolvimento moderno:

- **Framework**: Laravel 13
- **Frontend**: Livewire 4 + Flux UI 2.x
- **IA**: Google Gemini API
- **Fila**: Redis / Database Queues
- **Processamento de Doc**: PhpWord & OCR Multimodal

## 🚀 Como Começar?

### Pré-requisitos
- PHP 8.3+
- Composer
- Uma chave de API do [Google AI Studio](https://aistudio.google.com/app/apikey)

### Instalação Rápida

1. **Clone o repositório**
   ```bash
   git clone https://github.com/renan/avali.ai.git
   cd avali.ai/application
   ```

2. **Instale as dependências**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Configure o ambiente**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Não esqueça de adicionar sua `GEMINI_API_KEY` no `.env`!*

4. **Prepare o banco de dados**
   ```bash
   php artisan migrate
   ```

5. **Inicie os motores**
   ```bash
   php artisan serve
   # Em outro terminal, inicie a fila de processamento:
   php artisan queue:work
   ```

## 📈 Resiliência e Performance

O avali.ai foi desenhado para escalar. Ele inclui:
- **Rate Limit Protection**: Escalonamento inteligente de requisições para respeitar os limites da API Gemini.
- **Auto-Retry**: Sistema de retentativas automáticas com backoff exponencial para erros de conexão ou alta demanda.
- **Fail-Fast**: Verificação prévia de conectividade com a IA.

---

Criado com ❤️ por educadores para educadores.  
**avali.ai** - Transformando dados em aprendizado.
