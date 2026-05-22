@extends('prompts.grading.layout')

@section('content')
OBJETIVO ESPECÍFICO:
Analisar o ARTIGO CIENTÍFICO fornecido, extrair suas seções principais, avaliá-lo com base no rigor acadêmico e atribuir uma nota e feedback estruturado.

REGRAS OBRIGATÓRIAS:
1. Você DEVE retornar um ÚNICO OBJETO JSON PURO. Não retorne Markdown como ```json.
2. Extraia o(s) autor(es) lendo o conteúdo do documento (normalmente abaixo do título). Se houver múltiplos, concatene-os com vírgula (ex: "Renan, João"). Salve na chave "student_name".
3. Avalie o artigo com nota de 0 a 10 e salve na chave "final_grade".
4. Forneça um feedback geral detalhado na chave "feedback".
5. Extraia o texto para as seguintes seções estruturais do artigo científico (se ausente, tente deduzir pelo fluxo do texto, caso não exista retorne null):
   - "resumo"
   - "introducao"
   - "metodologia"
   - "revisao_bibliografica"
   - "conclusao"
   - "perspectivas_futuras"
   - "referencias"

CONTEXTO ADICIONAL:
- Critérios Específicos do Professor (USE-OS PARA A NOTA E FEEDBACK): "{!! $criteria !!}"
- Referência/Modelo Disponível (Documento A): {{ $hasAnswerKey ? 'SIM' : 'NÃO' }}

FORMATO DE RESPOSTA (JSON PURO):
{
  "student_name": "Autores Encontrados",
  "final_grade": 8.5,
  "feedback": "Seu artigo tem uma boa premissa, mas falha na metodologia...",
  "resumo": "...",
  "introducao": "...",
  "metodologia": "...",
  "revisao_bibliografica": "...",
  "conclusao": "...",
  "perspectivas_futuras": "...",
  "referencias": "..."
}
@endsection
