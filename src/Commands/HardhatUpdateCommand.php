<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class HardhatUpdateCommand extends Command
{
    protected $signature = 'hardhat:update {--dry-run : Show what would be updated without installing} {--silent : Reduce npm output noise}';

    protected $description = 'Run npm update in the configured Hardhat project directory';

    public function handle(): int
    {
        $path = config('hardhat-laravel.project_path', base_path('blockchain'));

        $args = ['npm', 'update'];
        if ($this->option('dry-run')) {
            $args[] = '--dry-run';
        }
        if ($this->option('silent')) {
            $args[] = '--silent';
        }

        $this->info("Running '".implode(' ', $args)."' in {$path}...\n");

        $result = Process::path($path)->run($args, function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if ($result->exitCode() !== 0) {
            $this->error('npm update failed.');

            return $result->exitCode();
        }

        $this->info('npm update completed successfully.');

        return self::SUCCESS;
    }
}
