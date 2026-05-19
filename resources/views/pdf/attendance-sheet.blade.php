<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lista de Presença - {{ $session->class_name }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1f2937;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 25px;
            border-bottom: 2px solid #6366f1;
            padding-bottom: 15px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #4f46e5;
            margin: 0;
        }
        .subtitle {
            font-size: 14px;
            color: #4b5563;
            margin: 5px 0 0 0;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .info-grid td {
            padding: 4px 0;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            color: #374151;
            width: 120px;
        }
        .info-value {
            color: #4b5563;
        }
        .table-title {
            font-size: 14px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .attendance-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #d1d5db;
            font-size: 11px;
            text-transform: uppercase;
        }
        .attendance-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
        }
        .attendance-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="logo">Avali.AI</h1>
        <p class="subtitle">Lista Oficial de Presença - Chamada Online</p>
    </div>

    <table class="info-grid">
        <tr>
            <td class="info-label">Professor(a):</td>
            <td class="info-value">{{ $session->user->name }}</td>
            <td class="info-label text-right">Data da Chamada:</td>
            <td class="info-value text-right">{{ $session->created_at->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Turma / Aula:</td>
            <td class="info-value">{{ $session->class_name }}</td>
            <td class="info-label text-right">Horário de Início:</td>
            <td class="info-value text-right">{{ $session->created_at->format('H:i:s') }}</td>
        </tr>
        <tr>
            <td class="info-label">Status:</td>
            <td class="info-value">{{ $session->is_active ? 'Aberta (Em Andamento)' : 'Finalizada' }}</td>
            <td class="info-label text-right">Total Presentes:</td>
            <td class="info-value text-right"><strong>{{ $session->records->count() }}</strong></td>
        </tr>
    </table>

    <div class="table-title">Estudantes Presentes</div>

    <table class="attendance-table">
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th style="width: 45%">Nome do Estudante</th>
                <th style="width: 20%">Horário de Entrada</th>
                <th style="width: 30%">Informações Adicionais (IP/Disp.)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($session->records->sortBy('student_name') as $index => $record)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="font-weight: bold; color: #1f2937;">{{ $record->student_name }}</td>
                    <td>{{ $record->created_at->format('H:i:s') }}</td>
                    <td style="font-size: 10px; color: #6b7280;">
                        IP: {{ $record->ip_address ?? 'N/D' }}<br>
                        {{ Str::limit($record->user_agent, 45) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: #9ca3af; font-style: italic;">
                        Nenhum registro de presença foi efetuado.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Gerado automaticamente por Avali.AI em {{ now()->setTimezone('America/Bahia')->format('d/m/Y \à\s H:i:s') }} - Documento Oficial de Presença
    </div>

</body>
</html>
