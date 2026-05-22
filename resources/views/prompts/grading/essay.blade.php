@extends('prompts.grading.layout')

@section('content')
OBJETIVO ESPECÍFICO:
Analisar a REDAÇÃO (texto dissertativo-argumentativo) fornecida, avaliar e extrair sua estrutura com extrema precisão, garantindo que boas práticas textuais sejam validadas.

REGRAS OBRIGATÓRIAS:
1. Você DEVE retornar um ÚNICO OBJETO JSON PURO. Não retorne Markdown como ```json.
2. Identifique o autor lendo o texto do documento e salve em "student_name".
3. Avalie a redação com nota de 0 a 10 (ou na escala adequada aos critérios numéricos fornecidos) e salve em "final_grade".
4. Forneça um feedback detalhado da correção na chave "feedback", justificando a nota com base nas competências de uma redação padrão (coesão, coerência, gramática e proposta de intervenção).
5. Separe e extraia os parágrafos do texto e agrupe-os lógicamente nas seguintes chaves (se ausente, retorne null):
   - "introducao"
   - "desenvolvimento"
   - "conclusao"

CONTEXTO ADICIONAL:
- Critérios Específicos do Professor (IMPORTANTE: Use-os como COMPLEMENTO às boas práticas de avaliação de redação padrão): "{!! $criteria !!}"
- Texto/Tema Base Disponível (Documento A): {{ $hasAnswerKey ? 'SIM' : 'NÃO' }}

FORMATO DE RESPOSTA (JSON PURO):
{
  "student_name": "Nome do Aluno",
  "final_grade": 9.0,
  "feedback": "Excelente uso de conectivos, porém a proposta de intervenção foi fraca...",
  "introducao": "...",
  "desenvolvimento": "...",
  "conclusao": "..."
}
@endsection
