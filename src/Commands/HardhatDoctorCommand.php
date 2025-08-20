<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Roberts\HardhatLaravel\HardhatWrapper;

class HardhatDoctorCommand extends Command
{
    protected $signature = 'hardhat:doctor';

    protected $description = 'Diagnose Hardhat integration: path resolution and tool availability';

    public function handle(HardhatWrapper $hardhat): int
    {
        $path = $hardhat->getProjectPath();
        $this->line('Hardhat project path: '.$path);

        $exists = is_dir($path);
        $this->line('Directory exists: '.($exists ? 'yes' : 'no'));

        // Check for common files
        $cfg = $path.DIRECTORY_SEPARATOR.'hardhat.config.js';
        $cfgTs = $path.DIRECTORY_SEPARATOR.'hardhat.config.ts';
        $this->line('Config present: '.((file_exists($cfg) || file_exists($cfgTs)) ? 'yes' : 'no'));

        // Light-weight checks for Node/npm availability
        $node = Process::run(['node', '--version']);
        $npm = Process::run(['npm', '--version']);
        $this->line('node available: '.($node->successful() ? trim($node->output()) : 'no'));
        $this->line('npm available: '.($npm->successful() ? trim($npm->output()) : 'no'));

        // npx hardhat --version (non-throwing)
        $hh = Process::path($path)->run(['npx', 'hardhat', '--version']);
        $this->line('hardhat available: '.($hh->successful() ? trim($hh->output()) : 'no'));

        return self::SUCCESS;
    }
}
