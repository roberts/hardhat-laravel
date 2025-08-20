<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;
use Roberts\HardhatLaravel\HardhatWrapper;

class HardhatRunCommand extends Command
{
    protected $signature = 'hardhat:run {script : Path to hardhat script (ts/js)} {--arg=* : Pass-through args} {--hh-env=* : Env vars KEY=VALUE}';

    protected $description = 'Run a hardhat script with optional args and env';

    public function handle(HardhatWrapper $hardhat): int
    {
        $script = $this->argument('script');
        $args = $this->option('arg');
    $env = $this->parseEnv($this->option('hh-env'));

        $result = $hardhat->runScriptStreaming($script, $args, $env, function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function parseEnv(array $pairs): array
    {
        $env = [];
        foreach ($pairs as $pair) {
            if (str_contains($pair, '=')) {
                [$k, $v] = explode('=', $pair, 2);
                $env[$k] = $v;
            }
        }

        return $env;
    }
}
