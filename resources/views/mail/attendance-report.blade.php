<x-mail::message>
# Chamada Finalizada!

Olá, **{{ $session->user->name }}**!

A chamada online para a turma **{{ $session->class_name }}** foi encerrada com sucesso.

**Resumo da Chamada:**
* **Turma:** {{ $session->class_name }}
* **Data/Hora:** {{ $session->created_at->format('d/m/Y H:i') }}
* **Total de Estudantes Presentes:** {{ $session->records->count() }}

A lista completa de presença em formato PDF foi gerada e anexada a este e-mail.

Você também pode acessar e visualizar o registro completo diretamente na plataforma.

<x-mail::button :url="route('attendance.show', $session->uuid)">
Ver Chamada no Painel
</x-mail::button>

Atenciosamente,<br>
Equipe **{{ config('app.name') }}**
</x-mail::message>
