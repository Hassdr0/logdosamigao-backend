<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Sync\SyncService;
use App\Modules\Players\Player;

class SyncWarcraftLogs extends Command
{
    protected $signature = 'sync:wcl
                            {--player= : ID do player para sync individual (opcional)}';

    protected $description = 'Sincroniza dados de performance de raids via Warcraft Logs API v2';

    public function handle(SyncService $syncService): int
    {
        $playerId = $this->option('player');

        if ($playerId) {
            return $this->syncSinglePlayer($syncService, (int) $playerId);
        }

        return $this->syncAllPlayers($syncService);
    }

    private function syncSinglePlayer(SyncService $syncService, int $playerId): int
    {
        $player = Player::find($playerId);

        if (!$player) {
            $this->error("Player com ID {$playerId} não encontrado.");
            return self::FAILURE;
        }

        $this->info("Sincronizando player: {$player->name} ({$player->realm}/{$player->region})");

        $syncLog = $syncService->syncPlayer($player);

        if ($syncLog->status === 'failed') {
            $this->error("Sync falhou: " . $syncLog->error_message);
            return self::FAILURE;
        }

        $this->info("Sync concluído: status={$syncLog->status}, reports={$syncLog->reports_fetched}");

        if ($syncLog->status === 'partial') {
            $this->warn("Erros parciais:\n" . $syncLog->error_message);
        }

        return self::SUCCESS;
    }

    private function syncAllPlayers(SyncService $syncService): int
    {
        $total = Player::where('is_active', true)->count();
        $this->info("Iniciando sync global para {$total} player(s) ativos...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $players = Player::where('is_active', true)->get();
        $results = ['success' => 0, 'failed' => 0, 'partial' => 0];

        foreach ($players as $player) {
            $syncLog = $syncService->syncPlayer($player);
            $results[$syncLog->status]++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->table(
            ['Status', 'Count'],
            collect($results)->map(fn($count, $status) => [$status, $count])->values()->toArray()
        );

        return $results['failed'] === $total ? self::FAILURE : self::SUCCESS;
    }
}
