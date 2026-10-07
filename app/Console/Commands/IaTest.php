<?php

namespace App\Console\Commands;

use App\Services\GeminiApiKeyService;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Console\Command;
use Smalot\PdfParser\Parser;
use Throwable;

class IaTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ia:test {prova} {resposta}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige uma prova baseada nas questões e respostas fornecidas';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $provaInput = $this->argument('prova');
        $respostaInput = $this->argument('resposta');

        $this->info('Iniciando processo de correção com Gemini...');

        $prova = $this->getContent($provaInput);
        $resposta = $this->getContent($respostaInput);

        try {
            $prompt = view('prompts.grading.test', [
                'prova' => $prova,
                'resposta' => $resposta,
            ])->render();

            $model = app(GeminiApiKeyService::class)->getDefaultModel();
            $response = Gemini::generativeModel($model)
                ->generateContent($prompt);

            $text = trim($response->text());

            if ($text === '') {
                $this->error('Falha: o Gemini respondeu sem conteudo textual.');

                return self::FAILURE;
            }

            $this->info('Correção concluída com sucesso:');
            $this->line($text);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Falha ao processar a correção com o Gemini.');
            $this->line('Motivo: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function getContent(string $input): string
    {
        if (file_exists($input)) {
            if (str_ends_with(strtolower($input), '.pdf')) {
                try {
                    $parser = new Parser;
                    $pdf = $parser->parseFile($input);

                    return $pdf->getText();
                } catch (Throwable $e) {
                    $this->warn("Falha ao extrair texto do PDF {$input}: ".$e->getMessage());

                    return file_get_contents($input);
                }
            }

            return file_get_contents($input);
        }

        return $input;
    }
}
