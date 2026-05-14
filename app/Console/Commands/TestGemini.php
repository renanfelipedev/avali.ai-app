<?php

namespace App\Console\Commands;

use Gemini\Laravel\Facades\Gemini;
use Illuminate\Console\Command;
use Throwable;

class TestGemini extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ia:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa a conexão com o Gemini gerando duas questões em formato JSON';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando teste de comunicação com o Gemini...');

        $modelName = config('gemini.default_model');
        $this->line("Modelo configurado: <comment>{$modelName}</comment>");

        $prompt = <<<PROMPT
Gere duas questões simples de conhecimentos gerais.
A resposta deve ser ESTRITAMENTE um JSON válido no seguinte formato:
[
  {
    "pergunta": "Qual a capital do Brasil?",
    "opcoes": ["A) Rio", "B) SP", "C) Brasília", "D) Salvador"],
    "resposta_correta": "C) Brasília"
  }
]
Não inclua marcadores de markdown (como ```json) na resposta, apenas o array JSON puro.
PROMPT;

        $this->line("Enviando prompt...\n");

        try {
            $response = Gemini::generativeModel($modelName)->generateContent($prompt);

            $text = trim($response->text());

            // Clean markdown blocks if AI still outputted them
            $text = str_replace(['```json', '```'], '', $text);
            $text = trim($text);

            $this->info("Resposta recebida com sucesso!\n");
            $this->line($text);

            // Validar o JSON
            $json = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->info("\n✅ JSON válido!");
                $this->table(['Pergunta', 'Resposta Correta'], collect($json)->map(function ($item) {
                    return [$item['pergunta'] ?? 'N/A', $item['resposta_correta'] ?? 'N/A'];
                })->toArray());
            } else {
                $this->error("\n❌ Erro: O retorno não é um JSON válido. (" . json_last_error_msg() . ")");
            }
        } catch (Throwable $e) {
            $this->error("\n❌ Falha ao comunicar com a IA:");
            $this->error($e->getMessage());
            $this->error("Arquivo: " . $e->getFile() . " (Linha " . $e->getLine() . ")");
        }
    }
}
