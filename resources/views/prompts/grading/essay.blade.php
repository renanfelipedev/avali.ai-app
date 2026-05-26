@extends('prompts.grading.layout')

@section('content')
OBJETIVO ESPECÍFICO:
Analisar a REDAÇÃO (texto dissertativo-argumentativo) fornecida, avaliar e extrair sua estrutura com extrema precisão, garantindo que boas práticas textuais sejam validadas.

REGRAS OBRIGATÓRIAS:
1. Você DEVE retornar um ÚNICO OBJETO JSON PURO. Não retorne Markdown como ```json.
2. Extraia o(s) autor(es) lendo o conteúdo do documento (normalmente abaixo do título). Se houver múltiplos, concatene-os com vírgula. Salve na chave "student_name".
3. Avalie a redação com nota de 0 a 10 e salve na chave "final_grade".
4. Forneça um feedback geral detalhado na chave "feedback". IGNORE COMPLETAMENTE a formatação ou listagem dos nomes dos autores na sua avaliação. O feedback deve focar estritamente no Título e no Conteúdo da redação, justificando a nota com base nas competências de uma redação padrão (coesão, coerência, gramática e proposta de intervenção).
5. Para cada seção estrutural da redação (Título, Introdução, Desenvolvimento, Conclusão), forneça um objeto separado na chave "sections_feedback".
6. Dentro de cada objeto de seção, inclua EXCLUSIVAMENTE: "section_name", "student_text" (o texto extraído) e "feedback" (sua análise sobre aquela seção específica). NÃO inclua notas ou dados numéricos nestes objetos de seção.
7. NÃO invente nem deduza seções que não estejam explícitas no documento. Se uma seção não estiver presente, omita-a do array.

CONTEXTO ADICIONAL:
- Critérios Específicos do Professor (USE-OS PARA A NOTA E FEEDBACK): "{!! $criteria !!}"
- Texto/Tema Base Disponível (Documento A): {{ $hasAnswerKey ? 'SIM' : 'NÃO' }}

FORMATO DE RESPOSTA (JSON PURO):
{
"student_name": "Nome do Aluno",
"final_grade": 9.0,
"feedback": "Excelente uso de conectivos, porém a proposta de intervenção foi fraca...",
"sections_feedback": [
{
"section_name": "Introdução",
"student_text": "Trecho exato da introdução da redação...",
"feedback": "A introdução apresenta o tema de forma clara..."
},
{
"section_name": "Desenvolvimento",
"student_text": "Trecho exato do desenvolvimento...",
"feedback": "Os argumentos foram bem construídos..."
}
]
}
@endsection