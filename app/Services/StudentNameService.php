<?php

namespace App\Services;

use App\Models\AttendanceSession;
use Illuminate\Support\Str;

class StudentNameService
{
    /**
     * Map of unaccented lowercase Portuguese tokens to their proper accented forms.
     *
     * @var array<string, string>
     */
    protected array $accentMap = [
        // First names (A - Z)
        'agatha' => 'Ágatha',
        'alvaro' => 'Álvaro',
        'amabile' => 'Amábile',
        'amalia' => 'Amália',
        'andre' => 'André',
        'andrea' => 'Andréa',
        'angela' => 'Ângela',
        'angelica' => 'Angélica',
        'anibal' => 'Aníbal',
        'anisio' => 'Anísio',
        'antonio' => 'Antônio',
        'aurea' => 'Áurea',
        'aurelio' => 'Aurélio',
        'barbara' => 'Bárbara',
        'candido' => 'Cândido',
        'cassio' => 'Cássio',
        'caua' => 'Cauã',
        'cauan' => 'Cauã',
        'cesar' => 'César',
        'cezar' => 'Cézar',
        'cicero' => 'Cícero',
        'claudia' => 'Cláudia',
        'claudio' => 'Cláudio',
        'cleber' => 'Cléber',
        'cleo' => 'Cléo',
        'cristovao' => 'Cristóvão',
        'dario' => 'Dário',
        'debora' => 'Débora',
        'deise' => 'Deyse',
        'denis' => 'Dênis',
        'eder' => 'Éder',
        'edson' => 'Édson',
        'elcio' => 'Élcio',
        'eloisa' => 'Eloísa',
        'elza' => 'Élza',
        'enio' => 'Ênio',
        'erica' => 'Érica',
        'erick' => 'Érick',
        'erico' => 'Érico',
        'estevao' => 'Estêvão',
        'eugenio' => 'Eugênio',
        'fabio' => 'Fábio',
        'fabricio' => 'Fabrício',
        'fatima' => 'Fátima',
        'flavia' => 'Flávia',
        'flavio' => 'Flávio',
        'gloria' => 'Glória',
        'graca' => 'Graça',
        'helen' => 'Hélen',
        'helio' => 'Hélio',
        'heloisa' => 'Heloísa',
        'icaro' => 'Ícaro',
        'inacio' => 'Inácio',
        'ines' => 'Inês',
        'isis' => 'Ísis',
        'italo' => 'Ítalo',
        'jessica' => 'Jéssica',
        'joao' => 'João',
        'jonatas' => 'Jônatas',
        'jose' => 'José',
        'julia' => 'Júlia',
        'julio' => 'Júlio',
        'lais' => 'Laís',
        'lazaro' => 'Lázaro',
        'leo' => 'Léo',
        'leticia' => 'Letícia',
        'licia' => 'Lícia',
        'ligia' => 'Lígia',
        'livia' => 'Lívia',
        'lucia' => 'Lúcia',
        'lucio' => 'Lúcio',
        'luis' => 'Luís',
        'luisa' => 'Luísa',
        'maira' => 'Maíra',
        'marcia' => 'Márcia',
        'marcio' => 'Márcio',
        'mario' => 'Mário',
        'mauricio' => 'Maurício',
        'monica' => 'Mônica',
        'nadia' => 'Nádia',
        'nelson' => 'Nélson',
        'noemi' => 'Noemi',
        'olivia' => 'Olívia',
        'otavio' => 'Otávio',
        'patricia' => 'Patrícia',
        'rogerio' => 'Rogério',
        'romulo' => 'Rômulo',
        'sergio' => 'Sérgio',
        'silvia' => 'Sílvia',
        'silvio' => 'Sílvio',
        'sonia' => 'Sônia',
        'tania' => 'Tânia',
        'tarcisio' => 'Tarcísio',
        'thais' => 'Thaís',
        'valeria' => 'Valéria',
        'veronica' => 'Verônica',
        'vinicius' => 'Vinícius',
        'vitor' => 'Vítor',
        'vitoria' => 'Vitória',

        // Surnames
        'abrahao' => 'Abrahão',
        'alcantara' => 'Alcântara',
        'araujo' => 'Araújo',
        'assuncao' => 'Assunção',
        'avila' => 'Ávila',
        'belem' => 'Belém',
        'brandao' => 'Brandão',
        'camara' => 'Câmara',
        'cancado' => 'Cançado',
        'conceicao' => 'Conceição',
        'damiao' => 'Damião',
        'falcao' => 'Falcão',
        'franca' => 'França',
        'galao' => 'Galão',
        'goncalves' => 'Gonçalves',
        'leao' => 'Leão',
        'magalhaes' => 'Magalhães',
        'maranhao' => 'Maranhão',
        'nobrega' => 'Nóbrega',
        'paixao' => 'Paixão',
        'reboucas' => 'Rebouças',
        'romao' => 'Romão',
        'santarem' => 'Santarém',
        'serrao' => 'Serrão',
        'simao' => 'Simão',
        'simoes' => 'Simões',
    ];

    /**
     * Lowercase prepositions in Portuguese names.
     *
     * @var array<string>
     */
    protected array $prepositions = ['de', 'da', 'do', 'das', 'dos', 'e'];

    /**
     * Normalize a name for accent-insensitive and case-insensitive comparison.
     */
    public function normalize(string $name): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($name)) ?? '';

        return Str::ascii(mb_strtolower($clean, 'UTF-8'));
    }

    /**
     * Check if two names match, ignoring accents and case.
     */
    public function namesMatch(string $name1, string $name2, bool $allowPartial = false): bool
    {
        $norm1 = $this->normalize($name1);
        $norm2 = $this->normalize($name2);

        if ($norm1 === '' || $norm2 === '') {
            return false;
        }

        if ($norm1 === $norm2) {
            return true;
        }

        if ($allowPartial) {
            return str_contains($norm1, $norm2) || str_contains($norm2, $norm1);
        }

        return false;
    }

    /**
     * Resolve the official or proper accented student name based on session context and dictionary.
     */
    public function resolveStudentName(string $inputName, ?AttendanceSession $session = null): string
    {
        $inputName = trim($inputName);
        if ($inputName === '') {
            return '';
        }

        $normalizedInput = $this->normalize($inputName);

        // 1. Check enrolled students in the session's classroom
        if ($session?->classroom_id && $session->classroom) {
            $classroomStudents = $session->classroom->students;

            // 1a. Exact normalized match
            $matched = $classroomStudents->first(function ($student) use ($normalizedInput) {
                return $this->normalize($student->name) === $normalizedInput;
            });

            if ($matched) {
                return $matched->name;
            }

            // 1b. Partial match if unambiguous (exactly 1 match)
            $partialMatches = $classroomStudents->filter(function ($student) use ($normalizedInput) {
                $studentNorm = $this->normalize($student->name);

                return str_contains($studentNorm, $normalizedInput) || str_contains($normalizedInput, $studentNorm);
            });

            if ($partialMatches->count() === 1) {
                return $partialMatches->first()->name;
            }
        }

        // 2. Check any registered student of the teacher
        if ($session?->user) {
            $matched = $session->user->students->first(function ($student) use ($normalizedInput) {
                return $this->normalize($student->name) === $normalizedInput;
            });

            if ($matched) {
                return $matched->name;
            }
        }

        // 3. Fallback: Restore common Portuguese accents and format casing
        return $this->fixAccentsAndCasing($inputName);
    }

    /**
     * Restore common Portuguese accents and apply proper Title Case, preserving prepositions.
     */
    public function fixAccentsAndCasing(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        if (empty($words)) {
            return '';
        }

        $formattedWords = [];

        foreach ($words as $index => $word) {
            if ($word === '') {
                continue;
            }

            $asciiLower = Str::ascii(mb_strtolower($word, 'UTF-8'));
            $lower = mb_strtolower($word, 'UTF-8');

            // Handle prepositions (e.g. "da", "de", "do", "das", "dos", "e")
            // Keep lowercase unless it's the very first word
            if ($index > 0 && in_array($lower, $this->prepositions, true)) {
                $formattedWords[] = $lower;

                continue;
            }

            // If mapped in our dictionary, use the accented version
            if (isset($this->accentMap[$asciiLower])) {
                $formattedWords[] = $this->accentMap[$asciiLower];

                continue;
            }

            // Otherwise, capitalize first letter while respecting any existing accents
            $formattedWords[] = mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $formattedWords);
    }
}
