<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;
use Roberts\HardhatLaravel\HardhatWrapper;

class HardhatCompileCommand extends Command
{
    protected $signature = 'hardhat:compile {--hh-env=* : Env vars in KEY=VALUE form}';

    protected $description = 'Run hardhat compile in the configured project';

    public function handle(HardhatWrapper $hardhat): int
    {
        $env = $this->parseEnv($this->option('hh-env'));
        $result = $hardhat->runStreaming('compile', [], $env, function ($type, $buffer) {
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
