<div class="flex-1 p-4 max-lg:hidden">
    <div class="relative rounded-2xl h-full w-full bg-gradient-to-br from-zinc-950 via-slate-900 to-indigo-950 text-white flex flex-col justify-between p-10 xl:p-12 overflow-hidden border border-zinc-800 shadow-2xl">
        
        <!-- Luzes e Efeitos de Fundo (Mesh Glow) -->
        <div class="absolute -top-32 -right-32 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/2 -left-32 w-80 h-80 bg-purple-500/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 right-1/4 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Grade Sutil de Fundo -->
        <div class="absolute inset-0 bg-[radial-gradient(#ffffff0a_1px,transparent_1px)] [background-size:24px_24px] pointer-events-none"></div>

        <!-- Conteúdo do Topo: Badges & Headline -->
        <div class="relative z-10 space-y-4">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 backdrop-blur-md shadow-sm">
                <flux:icon.sparkles class="w-3.5 h-3.5 text-indigo-400" />
                <span>Inteligência Artificial & Gestão Pedagógica</span>
            </div>

            <h2 class="text-2xl xl:text-3xl font-extrabold tracking-tight text-white leading-tight">
                Sua rotina pedagógica potencializada pelo que realmente importa.
            </h2>

            <p class="text-xs xl:text-sm text-zinc-300/90 leading-relaxed max-w-lg">
                Elabore avaliações completas alinhadas à BNCC em segundos e realize chamadas dinâmicas antifraude com QR Code e geolocalização.
            </p>
        </div>

        <!-- Conteúdo Central: Mini-Cards Flutuantes de Demonstração (UI Preview) -->
        <div class="relative z-10 my-4 space-y-3.5">
            <!-- Card 1: Geração de Provas com IA -->
            <div class="rounded-xl p-4 bg-white/[0.06] border border-white/10 backdrop-blur-md shadow-lg transition-transform hover:scale-[1.01] duration-200">
                <div class="flex items-center justify-between mb-2.5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400">
                            <flux:icon.academic-cap class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="text-xs font-semibold text-white">Avaliação de Genética & Biologia Celular</div>
                            <div class="text-[10px] text-zinc-400">3º Ano Ensino Médio • 10 questões</div>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full">
                        <flux:icon.sparkles class="w-3 h-3" />
                        Gemini IA
                    </span>
                </div>

                <div class="bg-black/30 rounded-lg p-2.5 text-xs text-zinc-300 border border-white/5 space-y-1.5">
                    <div class="font-medium text-zinc-200 text-[11px] line-clamp-1">
                        1. A recombinação gênica durante o crossing-over ocorre principalmente em qual fase celular?
                    </div>
                    <div class="grid grid-cols-2 gap-1.5 pt-1 text-[10.5px]">
                        <div class="px-2 py-1 rounded bg-white/5 text-zinc-400">A) Prófase I (Meiose)</div>
                        <div class="px-2 py-1 rounded bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 flex items-center justify-between">
                            <span>B) Paquíteno (Gabarito)</span>
                            <flux:icon.check-circle class="w-3 h-3 text-emerald-400 shrink-0" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2.5 mt-2 text-[10px] text-zinc-400 border-t border-white/5">
                    <span>Exportação instantânea:</span>
                    <div class="flex items-center gap-1.5 font-medium text-zinc-300">
                        <span class="px-1.5 py-0.5 rounded bg-white/10 text-white">PDF</span>
                        <span class="px-1.5 py-0.5 rounded bg-white/10 text-white">Word (.docx)</span>
                        <span class="px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300">Gabarito</span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Chamada Online com QR Code & GPS -->
            <div class="rounded-xl p-3.5 bg-white/[0.06] border border-white/10 backdrop-blur-md shadow-lg transition-transform hover:scale-[1.01] duration-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                            <flux:icon.qr-code class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-white">Chamada Online ao Vivo</span>
                                <span class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                            </div>
                            <div class="text-[10px] text-zinc-400">Turma 3º Ano A • Geofencing 100m ativo</div>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-xs font-bold text-emerald-400">32 / 36</div>
                        <div class="text-[9px] text-zinc-400 uppercase tracking-wider">Presentes (89%)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conteúdo do Rodapé: Depoimento & Social Proof -->
        <div class="relative z-10 pt-4 border-t border-white/10 space-y-2.5">
            <div class="flex items-center gap-1 text-amber-400">
                <flux:icon.star variant="solid" class="w-3.5 h-3.5" />
                <flux:icon.star variant="solid" class="w-3.5 h-3.5" />
                <flux:icon.star variant="solid" class="w-3.5 h-3.5" />
                <flux:icon.star variant="solid" class="w-3.5 h-3.5" />
                <flux:icon.star variant="solid" class="w-3.5 h-3.5" />
                <span class="text-xs font-semibold text-zinc-300 ml-1.5">4.9 / 5.0</span>
                <span class="text-[11px] text-zinc-400 ml-1">• Aprovado por educadores</span>
            </div>

            <p class="text-xs italic text-zinc-200 leading-snug">
                "O avali.ai transformou minha rotina, permitindo que eu foque no que realmente importa: o aprendizado dos meus alunos."
            </p>

            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-[11px] font-bold text-white shadow-inner">
                    MS
                </div>
                <div>
                    <div class="text-xs font-semibold text-white">Profa. Maria Silva</div>
                    <div class="text-[10px] text-zinc-400">Docente de Ensino Médio & Coordenadora</div>
                </div>
            </div>
        </div>

    </div>
</div>
