<?php

use App\Models\Classroom;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.main')] class extends Component
{
    use WithFileUploads;

    public Classroom $classroom;
    public string $activeTab = 'alunos';

    #[Validate('nullable|file|mimes:txt,csv|max:5120')]
    public $studentListFile;

    // Para criar novo aluno
    #[Validate('required|string|max:255')]
    public string $newStudentName = '';
    
    #[Validate('nullable|email|max:255')]
    public string $newStudentEmail = '';

    // Para editar turma
    #[Validate('required|string|max:255')]
    public string $editName = '';

    #[Validate('nullable|string|max:255')]
    public ?string $editSubject = null;

    #[Validate('nullable|string|max:255')]
    public ?string $editInstitution = null;

    #[Validate('nullable|string|max:1000')]
    public ?string $editDescription = null;

    public function mount(Classroom $classroom)
    {
        if ($classroom->user_id !== Auth::id()) {
            abort(403);
        }
        $this->classroom = $classroom;
    }

    public function openEditModal()
    {
        $this->editName = $this->classroom->name;
        $this->editSubject = $this->classroom->subject;
        $this->editInstitution = $this->classroom->institution;
        $this->editDescription = $this->classroom->description;
        $this->modal('edit-classroom')->show();
    }

    public function updateClassroom()
    {
        $this->validateOnly('editName');
        $this->validateOnly('editSubject');
        $this->validateOnly('editInstitution');
        $this->validateOnly('editDescription');

        $this->classroom->update([
            'name' => $this->editName,
            'subject' => $this->editSubject,
            'institution' => $this->editInstitution,
            'description' => $this->editDescription,
        ]);

        $this->modal('edit-classroom')->close();
        session()->flash('status', 'Turma atualizada com sucesso!');
    }

    public function deleteClassroom()
    {
        $this->classroom->delete();
        session()->flash('status', 'Turma excluída com sucesso!');
        return $this->redirect(route('classrooms.index'), navigate: true);
    }

    public function createStudent()
    {
        $this->validateOnly('newStudentName');
        $this->validateOnly('newStudentEmail');

        // Cria o aluno vinculado ao professor
        $student = Auth::user()->students()->create([
            'name' => $this->newStudentName,
            'email' => $this->newStudentEmail,
            'institution' => $this->classroom->institution,
        ]);

        // Vincula o aluno à turma
        $this->classroom->students()->attach($student->id);

        $this->reset(['newStudentName', 'newStudentEmail']);
        $this->modal('add-student')->close();
        
        // Atualiza a propriedade
        unset($this->classroom->students);
    }

    public function removeStudent($studentId)
    {
        $this->classroom->students()->detach($studentId);
        unset($this->classroom->students);
    }

    public function importStudents()
    {
        $this->validateOnly('studentListFile');

        if (!$this->studentListFile) {
            return;
        }

        $content = file_get_contents($this->studentListFile->getRealPath());
        $lines = explode("\n", $content);
        
        $studentIds = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $name = '';
            $email = null;

            // Try to split by comma or semicolon
            if (str_contains($line, ';')) {
                $parts = explode(';', $line);
            } elseif (str_contains($line, ',')) {
                $parts = explode(',', $line);
            } else {
                $parts = [$line];
            }

            if (count($parts) >= 2) {
                $part1 = trim($parts[0]);
                $part2 = trim($parts[1]);

                // Determine which is name and which is email
                if (filter_var($part1, FILTER_VALIDATE_EMAIL)) {
                    $email = $part1;
                    $name = $part2;
                } elseif (filter_var($part2, FILTER_VALIDATE_EMAIL)) {
                    $email = $part2;
                    $name = $part1;
                } else {
                    $name = $part1;
                    $email = $part2;
                }
            } else {
                $name = trim($parts[0]);
            }

            if (empty($name)) {
                continue;
            }

            // Ensure email is valid, otherwise null
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = null;
            }

            // Find or create student for user
            if ($email) {
                $student = Auth::user()->students()->where('email', $email)->first();
            } else {
                $student = Auth::user()->students()->where('name', $name)->whereNull('email')->first();
            }

            if (!$student) {
                $student = Auth::user()->students()->create([
                    'name' => $name,
                    'email' => $email,
                    'institution' => $this->classroom->institution,
                ]);
            }

            $studentIds[] = $student->id;
        }

        // Exclude previous students, keeping only the ones from the file
        $this->classroom->students()->sync($studentIds);

        $this->reset('studentListFile');
        $this->modal('import-students')->close();

        unset($this->classroom->students);

        session()->flash('success', 'Lista de alunos importada com sucesso!');
    }
};
?>

<div>
    <div class="mb-6">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item href="{{ route('classrooms.index') }}">Turmas</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $classroom->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
    </div>

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <flux:heading size="xl">{{ $classroom->name }}</flux:heading>
            @if($classroom->subject)
                <flux:subheading>{{ $classroom->subject }} @if($classroom->institution) • {{ $classroom->institution }} @endif</flux:subheading>
            @endif
        </div>
        
        <div class="flex gap-2">
            <flux:button wire:click="openEditModal" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
            <flux:button wire:click="deleteClassroom" wire:confirm="Tem certeza que deseja excluir esta turma? Provas e chamadas também serão removidas." size="sm" variant="ghost" icon="trash" color="danger">Excluir</flux:button>
        </div>
    </div>

    <div class="flex border-b border-zinc-200 dark:border-zinc-800 mb-6 gap-6">
        <button wire:click="$set('activeTab', 'alunos')" class="pb-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'alunos' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            Alunos ({{ $classroom->students()->count() }})
        </button>
        <button wire:click="$set('activeTab', 'chamadas')" class="pb-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'chamadas' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            Chamadas ({{ $classroom->attendanceSessions()->count() }})
        </button>
        <button wire:click="$set('activeTab', 'provas')" class="pb-3 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'provas' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            Provas ({{ $classroom->exams()->count() }})
        </button>
    </div>

    @if (session()->has('success'))
        <div class="mb-4 p-4 text-sm text-green-800 bg-green-50 rounded-lg dark:bg-green-900/30 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    <div>
        @if($activeTab === 'alunos')
            <div class="flex justify-between items-center mb-4">
                <flux:heading size="lg">Lista de Alunos</flux:heading>
                <div class="flex gap-2">
                    <flux:modal.trigger name="import-students">
                        <flux:button variant="filled" size="sm" icon="arrow-up-tray">Importar Lista</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger name="add-student">
                        <flux:button variant="primary" size="sm" icon="plus">Adicionar Aluno</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <flux:card class="overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nome</flux:table.column>
                        <flux:table.column>Email</flux:table.column>
                        <flux:table.column align="right">Ações</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($classroom->students as $student)
                            <flux:table.row class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $student->name }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $student->email ?? '-' }}</flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:button wire:click="removeStudent({{ $student->id }})" wire:confirm="Tem certeza que deseja remover este aluno da turma?" variant="danger" size="sm" icon="trash">Remover</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="px-6 py-8 text-center text-zinc-500">
                                    Nenhum aluno cadastrado nesta turma.
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif

        @if($activeTab === 'chamadas')
            <div class="flex justify-between items-center mb-4">
                <flux:heading size="lg">Histórico de Chamadas</flux:heading>
                <flux:button href="{{ route('attendance.index') }}" variant="primary" size="sm" icon="plus">Nova Chamada</flux:button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($classroom->attendanceSessions()->latest()->get() as $session)
                    <flux:card>
                        <div class="flex justify-between items-start mb-2">
                            <flux:heading size="md">{{ $session->created_at->format('d/m/Y H:i') }}</flux:heading>
                            @if($session->is_active)
                                <flux:badge color="green">Aberta</flux:badge>
                            @else
                                <flux:badge color="zinc">Encerrada</flux:badge>
                            @endif
                        </div>
                        <p class="text-sm text-zinc-500 mb-4">{{ $session->records()->count() }} presentes</p>
                        <flux:button href="{{ route('attendance.show', $session->uuid) }}" variant="ghost" size="sm" class="w-full">Ver Detalhes</flux:button>
                    </flux:card>
                @empty
                    <div class="col-span-full py-8 text-center text-zinc-500 border border-dashed rounded-xl border-zinc-300 dark:border-zinc-700">
                        Nenhuma chamada realizada para esta turma.
                    </div>
                @endforelse
            </div>
        @endif

        @if($activeTab === 'provas')
            <div class="flex justify-between items-center mb-4">
                <flux:heading size="lg">Provas da Turma</flux:heading>
                <flux:button href="{{ route('exams.create') }}" variant="primary" size="sm" icon="plus">Gerar Nova Prova</flux:button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($classroom->exams()->latest()->get() as $exam)
                    <flux:card>
                        <flux:heading size="md" class="mb-1">{{ $exam->title }}</flux:heading>
                        <p class="text-sm text-zinc-500 mb-4">{{ $exam->created_at->format('d/m/Y') }}</p>
                        <flux:button href="{{ route('exams.show', $exam) }}" variant="ghost" size="sm" class="w-full">Ver Prova</flux:button>
                    </flux:card>
                @empty
                    <div class="col-span-full py-8 text-center text-zinc-500 border border-dashed rounded-xl border-zinc-300 dark:border-zinc-700">
                        Nenhuma prova gerada para esta turma.
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    <!-- Modal Adicionar Aluno -->
    <flux:modal name="add-student" class="max-w-md">
        <form wire:submit="createStudent">
            <flux:heading size="lg" class="mb-6">Adicionar Novo Aluno</flux:heading>
            
            <div class="space-y-4">
                <flux:input wire:model="newStudentName" label="Nome Completo" placeholder="Ex: João da Silva" required />
                <flux:input wire:model="newStudentEmail" type="email" label="Email (Opcional)" placeholder="joao@escola.com" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Adicionar</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal Editar Turma -->
    <flux:modal name="edit-classroom" class="max-w-xl">
        <form wire:submit="updateClassroom">
            <div>
                <flux:heading size="lg">Editar Turma</flux:heading>
                <flux:subheading>Atualize as informações da turma abaixo.</flux:subheading>
            </div>
            
            <div class="space-y-4 mt-6">
                <flux:input wire:model="editName" label="Nome da Turma" placeholder="Ex: 3º Ano A" required />
                <flux:input wire:model="editSubject" label="Disciplina" placeholder="Ex: Matemática" />
                <flux:input wire:model="editInstitution" label="Instituição de Ensino" placeholder="Ex: Escola Estadual..." />
                <flux:textarea wire:model="editDescription" label="Descrição (Opcional)" rows="3" placeholder="Informações adicionais sobre a turma..." />
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar Alterações</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Modal Importar Alunos -->
    <flux:modal name="import-students" class="max-w-md">
        <form wire:submit="importStudents">
            <flux:heading size="lg" class="mb-2">Importar Lista de Alunos</flux:heading>
            <flux:subheading class="mb-6">Importe alunos a partir de um arquivo .txt ou .csv (um por linha, formato: Nome ou Nome,Email).</flux:subheading>
            
            <div class="space-y-4">
                <flux:input wire:model="studentListFile" type="file" label="Arquivo de Alunos" accept=".txt,.csv" required />
                
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>Atenção</flux:callout.heading>
                    <flux:callout.text>
                        Ao importar uma nova lista, <strong>todos os alunos anteriores desta turma serão removidos</strong>, restando apenas os contidos no arquivo.
                    </flux:callout.text>
                </flux:callout>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Importar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>