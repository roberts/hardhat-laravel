<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;

class HardhatLaravelCommand extends Command
{
    public $signature = 'hardhat-laravel';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
