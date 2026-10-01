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
    protected $signature = 'ia:test-models';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa a conexão com o Gemini verificando qual dos fallback_models retorna a resposta';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Buscando todos os modelos disponíveis na API do Gemini...');

        try {
            $response = Gemini::models()->list();
            $availableModels = $response->models;
        } catch (Throwable $e) {
            $this->error('Falha ao buscar a lista de modelos: '.$e->getMessage());

            return;
        }

        $models = [];
        foreach ($availableModels as $model) {
            // Filtra apenas modelos que suportam geração de texto
            if (in_array('generateContent', $model->supportedGenerationMethods)) {
                $models[] = str_replace('models/', '', $model->name);
            }
        }

        if (empty($models)) {
            $this->error('Nenhum modelo que suporta geração de conteúdo foi encontrado na API.');

            return;
        }

        $this->info(count($models).' modelos encontrados que suportam generateContent.');

        $prompt = 'Responda apenas com a palavra "Sucesso" para confirmar o funcionamento.';
        $this->line("\nEnviando questionamento simples: \"{$prompt}\"\n");

        $sucesso = false;

        foreach ($models as $modelName) {
            $this->line("Testando modelo: <comment>{$modelName}</comment>...");

            try {
                $response = Gemini::generativeModel($modelName)->generateContent($prompt);
                $text = trim($response->text());

                $this->info("✅ Resposta recebida do modelo {$modelName} com sucesso!");
                $this->line("Resposta: {$text}\n");
                $sucesso = true;
                sleep(1);
            } catch (Throwable $e) {
                $this->error("❌ Falha no modelo {$modelName}: ".$e->getMessage());
            }
        }

        if (! $sucesso) {
            $this->error('Todos os modelos testados falharam ao tentar retornar uma resposta.');
        }
    }
}
