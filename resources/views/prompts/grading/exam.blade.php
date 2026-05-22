@extends('prompts.grading.layout')

@section('content')
OBJETIVO ESPECÍFICO:
Analisar a PROVA DO ALUNO fornecida e realizar uma correção detalhada, extraindo o conteúdo questão por questão em formato JSON.

REGRAS OBRIGATÓRIAS:
1. Você DEVE retornar um ARRAY JSON contendo um objeto com as chaves: "student_name", "final_grade", "questions" (array) e "full_transcription". Não retorne Markdown como ```json.
2. No array "questions", você DEVE incluir CADA questão identificada na prova com: "question_number", "grade", "student_answer" e "feedback".
3. O "feedback" deve explicar CLARAMENTE por que o aluno recebeu aquela nota, comparando com o gabarito se disponível.
4. NUNCA retorne apenas a nota final. O detalhamento por questão é obrigatório para a transparência do sistema.

CONTEXTO ADICIONAL:
- Critérios do Professor: "{!! $criteria !!}"
- Gabarito Disponível: {{ $hasAnswerKey ? 'SIM' : 'NÃO' }}
- Prova de Referência Disponível: {{ $hasExamFile ? 'SIM' : 'NÃO' }}

FORMATO DE RESPOSTA (JSON PURO):
[
  {
    "student_name": "Nome Identificado",
    "final_grade": 8.5,
    "full_transcription": "Texto integral da prova...",
    "questions": [
      {
        "question_number": 1,
        "grade": 2.0,
        "student_answer": "Resposta do aluno extraída fielmente",
        "feedback": "Explicação pedagógica detalhada..."
      }
    ]
  }
]
@endsection
