<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Console;

use iEducar\Packages\BuritiHorarioAutomatico\Services\HorarioDemoService;
use Illuminate\Console\Command;

class HorarioDemoCommand extends Command
{
    protected $signature = 'horario:demo {--force : Recria aulas demo}';

    protected $description = 'PoC — povoa quadro de horários com aulas demo';

    public function handle(HorarioDemoService $demo): int
    {
        $this->info('Horário automático · carga demo');
        $r = $demo->executar((bool) $this->option('force'));
        if ($r['pulado']) {
            $this->warn('  '.$r['mensagem']);

            return self::SUCCESS;
        }
        if (str_contains($r['mensagem'], 'ausente')) {
            $this->error('  '.$r['mensagem']);

            return self::FAILURE;
        }
        $this->line('  '.$r['mensagem']);

        return self::SUCCESS;
    }
}
