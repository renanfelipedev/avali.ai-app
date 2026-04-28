<?php

namespace App\Console\Commands;

use Gemini\Laravel\Facades\Gemini;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('ia:test')]
#[Description('Testa a conexao com a API do Gemini')]
class IaTest extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando teste de conexao com o Gemini...');

        try {
            $prompt = 'Responda apenas com a palavra: conectado';

            $response = Gemini::generativeModel('gemini-flash-latest')
                ->generateContent($prompt);

            $text = trim($response->text());

            if ($text === '') {
                $this->error('Falha: o Gemini respondeu sem conteudo textual.');

                return self::FAILURE;
            }

            $this->info('Conexao com o Gemini validada com sucesso.');
            $this->line('Resposta: ' . $text);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Falha ao conectar com o Gemini.');
            $this->line('Motivo: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
