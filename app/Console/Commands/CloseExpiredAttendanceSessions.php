<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:close-expired')]
#[Description('Encerra automaticamente chamadas cujo tempo limite de disponibilidade foi atingido')]
class CloseExpiredAttendanceSessions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Verificando chamadas online expiradas...');

        $closed = AttendanceSession::closeAllExpired();

        if ($closed > 0) {
            $this->info("{$closed} chamada(s) expirada(s) encerrada(s) com sucesso.");
        } else {
            $this->line('Nenhuma chamada expirada para encerrar.');
        }

        return self::SUCCESS;
    }
}
