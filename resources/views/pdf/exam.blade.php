<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Prova - {{ $exam->title }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1f2937;
            font-size: 11.5px;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #6366f1;
            padding-bottom: 12px;
        }
        .school-header {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 25px;
        }
        .school-header td {
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            vertical-align: middle;
        }
        .school-header .label {
            font-weight: bold;
            color: #374151;
            background-color: #f9fafb;
            width: 15%;
            font-size: 10px;
        }
        .school-header .content-empty {
            width: 35%;
        }
        .exam-title {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
            text-align: center;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #4f46e5;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-top: 25px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .question {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .question-header {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 6px;
            color: #1f2937;
        }
        .question-text {
            margin-bottom: 10px;
            color: #374151;
            text-align: justify;
        }
        .options-list {
            margin-left: 10px;
        }
        .option-item {
            margin-bottom: 8px;
            display: table;
            width: 100%;
        }
        .option-marker {
            display: table-cell;
            width: 45px;
            font-weight: bold;
            color: #4b5563;
        }
        .option-text {
            display: table-cell;
            color: #4b5563;
            vertical-align: top;
        }
        .discursive-answer-lines {
            margin-top: 12px;
            margin-left: 5px;
        }
        .discursive-line {
            border-bottom: 1px dotted #9ca3af;
            height: 24px;
            margin-bottom: 6px;
            width: 98%;
        }
        .page-break {
            page-break-before: always;
        }
        .answer-key-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .answer-key-table th, .answer-key-table td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            text-align: left;
        }
        .answer-key-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        .footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 5px;
        }
        /* Prose style for legacy markdown content */
        .prose h3 {
            font-size: 13px;
            font-weight: bold;
            margin-top: 20px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            color: #4f46e5;
        }
        .prose p {
            margin-bottom: 10px;
            text-align: justify;
        }
        .prose ul {
            list-style-type: none;
            padding-left: 10px;
        }
        .prose li {
            margin-bottom: 6px;
        }
    </style>
</head>
<body>

    <!-- Header com a logo transparente integrada -->
    <div class="header">
        <table style="width: 100%; border: none; border-collapse: collapse;">
            <tr style="background: none; border: none;">
                <td style="border: none; padding: 0; vertical-align: middle;">
                    @if(file_exists(public_path('images/logo.png')))
                        <img src="{{ public_path('images/logo.png') }}" style="height: 30px; width: auto;" alt="Logo">
                    @else
                        <span style="font-size: 20px; font-weight: bold; color: #4f46e5;">Avali.AI</span>
                    @endif
                </td>
                <td style="border: none; padding: 0; text-align: right; vertical-align: middle;">
                    <p style="font-size: 12px; font-weight: bold; color: #4b5563; margin: 0;">Avali.AI</p>
                    <p style="font-size: 10px; color: #9ca3af; margin: 2px 0 0 0;">Plataforma de Avaliação Inteligente</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Cabeçalho de Identificação Escolar -->
    <table class="school-header">
        <tr>
            <td class="label">ESTUDANTE:</td>
            <td colspan="3" style="width: 85%;"></td>
        </tr>
        <tr>
            <td class="label">DATA:</td>
            <td class="content-empty">____/____/________</td>
            <td class="label">TURMA:</td>
            <td class="content-empty">____________________</td>
        </tr>
        <tr>
            <td class="label">PROFESSOR:</td>
            <td class="content-empty">{{ $exam->user->name }}</td>
            <td class="label">NOTA:</td>
            <td class="content-empty">____________________</td>
        </tr>
    </table>

    <div class="exam-title">{{ $exam->title }}</div>

    @if($isJson && $jsonData)
        <!-- Questões Objetivas -->
        @if(isset($jsonData['objective_questions']) && count($jsonData['objective_questions']) > 0)
            <div class="section-title">Questões Objetivas</div>
            @foreach($jsonData['objective_questions'] as $index => $q)
                <div class="question">
                    <div class="question-header">Questão {{ $q['number'] ?? ($index + 1) }}</div>
                    <div class="question-text">{{ $q['text'] }}</div>
                    <div class="options-list">
                        @foreach($q['options'] ?? [] as $key => $option)
                            <div class="option-item">
                                <div class="option-marker">( &nbsp; ) {{ strtoupper($key) }})</div>
                                <div class="option-text">{{ $option }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        <!-- Questões Discursivas -->
        @if(isset($jsonData['discursive_questions']) && count($jsonData['discursive_questions']) > 0)
            <div class="section-title">Questões Discursivas</div>
            @foreach($jsonData['discursive_questions'] as $index => $q)
                <div class="question">
                    <div class="question-header">Questão {{ $q['number'] ?? ($index + 1) }}</div>
                    <div class="question-text">{{ $q['text'] }}</div>
                    <div class="discursive-answer-lines">
                        <div class="discursive-line"></div>
                        <div class="discursive-line"></div>
                        <div class="discursive-line"></div>
                        <div class="discursive-line"></div>
                        <div class="discursive-line"></div>
                    </div>
                </div>
            @endforeach
        @endif

        <!-- Gabarito Sugerido (Página Separada) -->
        <div class="page-break"></div>
        
        <div class="section-title" style="color: #10b981; border-bottom: 2px solid #10b981;">Gabarito Sugerido (Uso Exclusivo do Professor)</div>
        
        @if(isset($jsonData['objective_questions']) && count($jsonData['objective_questions']) > 0)
            <h3 style="margin-top: 20px; font-size: 12px; color: #374151;">Respostas - Questões Objetivas</h3>
            <table class="answer-key-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Questão</th>
                        <th style="width: 70%;">Alternativa Correta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($jsonData['objective_questions'] as $index => $q)
                        <tr>
                            <td><strong>Questão {{ $q['number'] ?? ($index + 1) }}</strong></td>
                            <td><span style="font-weight: bold; color: #047857; text-transform: uppercase;">Alternativa {{ $q['answer'] ?? 'N/A' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(isset($jsonData['discursive_questions']) && count($jsonData['discursive_questions']) > 0)
            <h3 style="margin-top: 30px; font-size: 12px; color: #374151;">Critérios de Correção - Questões Discursivas</h3>
            @foreach($jsonData['discursive_questions'] as $index => $q)
                <div style="margin-bottom: 15px; border-bottom: 1px solid #f3f4f6; padding-bottom: 10px; page-break-inside: avoid;">
                    <strong>Questão {{ $q['number'] ?? ($index + 1) }}:</strong>
                    <p style="margin: 5px 0 0 10px; font-style: italic; color: #4b5563; text-align: justify;">{{ $q['answer_key'] ?? 'N/A' }}</p>
                </div>
            @endforeach
        @endif

    @else
        <!-- Conteúdo Legado Markdown -->
        <div class="prose">
            {!! $htmlContent !!}
        </div>
    @endif

    <div class="footer">
        Gerado automaticamente pela Plataforma Avali.AI - Documento de Avaliação Acadêmica
    </div>

</body>
</html>
