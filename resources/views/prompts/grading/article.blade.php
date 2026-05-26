@extends('prompts.grading.layout')

@section('content')
OBJETIVO ESPECÍFICO:
Analisar o ARTIGO CIENTÍFICO fornecido, extrair suas seções principais, avaliá-lo com base no rigor acadêmico e atribuir uma nota e feedback estruturado.

REGRAS OBRIGATÓRIAS:
1. Você DEVE retornar um ÚNICO OBJETO JSON PURO. Não retorne Markdown como ```json.
2. Extraia o(s) autor(es) lendo o conteúdo do documento (normalmente abaixo do título). Se houver múltiplos, concatene-os com vírgula (ex: "Renan, João"). Salve na chave "student_name".
3. Avalie o artigo com nota de 0 a 10 e salve na chave "final_grade".
4. Forneça um feedback geral detalhado na chave "feedback". IGNORE COMPLETAMENTE a formatação ou listagem dos nomes dos autores na sua avaliação. O feedback deve focar estritamente no Título e no Conteúdo acadêmico do artigo.
5. Para cada seção estrutural do artigo (Título, Resumo, Introdução, Metodologia, Resultados, Conclusão, Referências, etc.), forneça um objeto separado na chave "sections_feedback".
6. Dentro de cada objeto de seção, inclua EXCLUSIVAMENTE: "section_name", "student_text" (o texto extraído) e "feedback" (sua análise sobre aquela seção específica). NÃO inclua notas ou dados numéricos nestes objetos de seção.
7. NÃO invente nem deduza seções que não estejam explícitas no documento. Se uma seção não estiver presente, omita-a do array.

CONTEXTO ADICIONAL:
- Critérios Específicos do Professor (USE-OS PARA A NOTA E FEEDBACK): "{!! $criteria !!}"
- Referência/Modelo Disponível (Documento A): {{ $hasAnswerKey ? 'SIM' : 'NÃO' }}

FORMATO DE RESPOSTA (JSON PURO):
{
"student_name": "Autores Encontrados",
"final_grade": 8.5,
"feedback": "Parecer geral conclusivo e detalhado sobre o artigo como um todo.",
"sections_feedback": [
{
"section_name": "Título",
"student_text": "Trecho exato do título do artigo...",
"feedback": "O título está adequado e reflete o tema..."
},
{
"section_name": "Resumo",
"student_text": "Trecho exato do resumo...",
"feedback": "O resumo sintetiza bem o trabalho..."
}
]
}
@endsection