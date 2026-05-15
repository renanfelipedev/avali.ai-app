<flux:sidebar sticky collapsible="mobile"
    class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 overflow-x-hidden">
    <flux:sidebar.header>
        <flux:sidebar.brand href="{{ route('home') }}" name="avali.ai"
            class="font-bold tracking-tight text-indigo-600 dark:text-indigo-400">
            <flux:icon.sparkles class="size-6" />
        </flux:sidebar.brand>

        <flux:sidebar.collapse class="lg:hidden" />
    </flux:sidebar.header>

    <!-- <flux:sidebar.search placeholder="Search..." /> -->

    <flux:sidebar.nav>
        <flux:sidebar.item icon="home" href="/home">Home</flux:sidebar.item>

        @can('admin')
            <flux:sidebar.group expandable heading="Módulo de Administração">
                <flux:sidebar.item icon="users" href="{{ route('users.index') }}">Usuários
                </flux:sidebar.item>
                <flux:sidebar.item icon="cpu-chip" href="{{ route('tasks.index') }}">
                    Tarefas Ativas
                    @php
                        $activeTasks = \App\Models\ExamGenerationRequest::whereIn('status', ['pending', 'processing'])->count() + 
                                      \App\Models\ExamEvaluation::where('status', 'processing')->count();
                    @endphp
                    @if($activeTasks > 0)
                        <flux:badge size="sm" color="amber" inset="top bottom" class="animate-pulse">{{ $activeTasks }}</flux:badge>
                    @endif
                </flux:sidebar.item>
                @if (app()->isProduction())
                    <flux:sidebar.item icon="heart" href="{{ route('health') }}">Saúde do Sistema</flux:sidebar.item>
                @endif
            </flux:sidebar.group>
        @else
            <flux:sidebar.item icon="cpu-chip" href="{{ route('tasks.index') }}">
                Tarefas Ativas
                @php
                    $activeTasks = \App\Models\ExamGenerationRequest::where('user_id', auth()->id())->whereIn('status', ['pending', 'processing'])->count() + 
                                  \App\Models\ExamEvaluation::where('user_id', auth()->id())->where('status', 'processing')->count();
                @endphp
                @if($activeTasks > 0)
                    <flux:badge size="sm" color="amber" inset="top bottom" class="animate-pulse">{{ $activeTasks }}</flux:badge>
                @endif
            </flux:sidebar.item>
        @endcan

        <flux:sidebar.group expandable heading="Módulo de Provas">
            <flux:sidebar.item icon="document-duplicate" href="{{ route('exams.index') }}">Listar Provas
            </flux:sidebar.item>
            <flux:sidebar.item icon="sparkles" href="{{ route('exams.create') }}">Criar Prova</flux:sidebar.item>
            <flux:sidebar.item icon="check-badge" href="{{ route('evaluations.index') }}">Corretor</flux:sidebar.item>
        </flux:sidebar.group>
    </flux:sidebar.nav>

    <flux:sidebar.spacer />

    <flux:sidebar.nav>
        <flux:sidebar.item icon="document-text" href="{{ route('ai-logs.index') }}">Logs da IA</flux:sidebar.item>
        <!-- <flux:sidebar.item icon="cog-6-tooth" href="#">Settings</flux:sidebar.item> -->
    </flux:sidebar.nav>

    <flux:dropdown position="top" align="start" class="max-lg:hidden">
        <flux:sidebar.profile name="{{ auth()->user()->name }}" />

        <flux:menu>
            <flux:menu.item icon="arrow-right-start-on-rectangle" href="{{ route('logout') }}">
                Logout
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</flux:sidebar>
