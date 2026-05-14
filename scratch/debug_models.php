<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Gemini\Laravel\Facades\Gemini;

echo "--- LISTANDO MODELOS DISPONÍVEIS ---\n";
try {
    $models = Gemini::models()->list();
    $availableModels = [];
    foreach ($models->models as $model) {
        // Verifica se o modelo suporta generateContent
        if (in_array('generateContent', $model->supportedGenerationMethods)) {
            $name = str_replace('models/', '', $model->name);
            $availableModels[] = $name;
            echo "[OK] {$name}\n";
        } else {
            // echo "[SKIP] {$model->name} (Não suporta geração)\n";
        }
    }

    echo "\n--- TESTANDO GERAÇÃO (PING) EM CADA MODELO ---\n";
    foreach ($availableModels as $modelName) {
        try {
            echo "Testando {$modelName}... ";
            $response = Gemini::generativeModel($modelName)->generateContent('ping');
            echo "SUCESSO! (Tokens: " . ($response->usageMetadata->totalTokenCount ?? 'N/A') . ")\n";
        } catch (\Exception $e) {
            echo "FALHA: " . $e->getMessage() . "\n";
        }
    }

} catch (\Exception $e) {
    echo "ERRO GERAL: " . $e->getMessage() . "\n";
}
