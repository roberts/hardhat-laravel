<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;
use Roberts\HardhatLaravel\HardhatWrapper;

class HardhatTestCommand extends Command
{
    protected $signature = 'hardhat:test {--arg=* : Pass-through args (e.g., --network localhost)} {--env=* : Env vars KEY=VALUE}';

    protected $description = 'Run hardhat test with optional args and env';

    public function handle(HardhatWrapper $hardhat): int
    {
        $args = $this->option('arg');
        $env = $this->parseEnv($this->option('env'));

        $result = $hardhat->runStreaming('test', $args, $env, function ($type, $buffer) {
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
