<x-mail::message>
# Recuperação de Senha

Olá, **{{ $userName }}**!

Recebemos uma solicitação para redefinir a senha da sua conta no **avali.ai**.

Para prosseguir e escolher uma nova senha, clique no botão abaixo:

<x-mail::button :url="$resetUrl">
Redefinir Minha Senha
</x-mail::button>

*Este link é válido por 60 minutos. Se você não solicitou a redefinição de senha, nenhuma ação adicional é necessária e sua senha atual continuará segura.*

Atenciosamente,<br>
Equipe **{{ config('app.name') }}**
</x-mail::message>
