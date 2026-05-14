<?php

namespace App\Console\Commands;

use Gemini\Laravel\Facades\Gemini;
use Illuminate\Console\Command;
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
            $prompt = <<<PROMPT
Você é um professor especialista. Sua tarefa é corrigir a prova de um aluno com base nas questões fornecidas.

QUESTÕES DA PROVA:
{$prova}

RESPOSTAS DO ALUNO:
{$resposta}

Por favor, forneça:
1. Uma avaliação detalhada de cada resposta (se está correta, parcialmente correta ou incorreta).
2. A justificativa para a correção.
3. Uma nota sugerida de 0 a 10.
4. Feedback construtivo para o aluno.

Responda em formato Markdown estruturado.
PROMPT;

            $response = Gemini::generativeModel('gemini-flash-latest')
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
            $this->line('Motivo: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function getContent(string $input): string
    {
        if (file_exists($input)) {
            if (str_ends_with(strtolower($input), '.pdf')) {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($input);
                    return $pdf->getText();
                } catch (Throwable $e) {
                    $this->warn("Falha ao extrair texto do PDF {$input}: " . $e->getMessage());
                    return file_get_contents($input);
                }
            }
            return file_get_contents($input);
        }

        return $input;
    }
}
