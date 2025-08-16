<?php

namespace Roberts\HardhatLaravel;

use Illuminate\Support\Facades\Process;

class HardhatWrapper
{
    protected string $projectPath;

    public function __construct(string $projectPath)
    {
        $this->projectPath = $projectPath;
    }

    /**
     * @param  string  $command  The Hardhat command (e.g., 'compile', 'run').
     * @param  array  $args  Command-line arguments.
     * @param  array  $env  Environment variables.
     * @return string The command's standard output.
     */
    public function runCommand(string $command, array $args = [], array $env = []): string
    {
        $pending = Process::path($this->projectPath);

        if (! empty($env)) {
            $pending = $pending->env($env);
        }

        $result = $pending->run(array_merge(['npx', 'hardhat', $command], $args));

        // Throws Illuminate\Process\Exceptions\ProcessFailedException on failure
        $result->throw();

        return $result->output();
    }

    public function compile(): string
    {
        return $this->runCommand('compile');
    }

    public function runScript(string $scriptPath, array $args = [], array $env = []): string
    {
        return $this->runCommand('run', array_merge([$scriptPath], $args), $env);
    }

    // Convenience methods
    public function clean(): string
    {
        return $this->runCommand('clean');
    }

    public function test(array $args = [], array $env = []): string
    {
        return $this->runCommand('test', $args, $env);
    }

    public function node(array $args = [], array $env = []): string
    {
        // Run arbitrary node within hardhat context (e.g., --network)
        return $this->runCommand('node', $args, $env);
    }

    public function help(?string $subcommand = null): string
    {
        $args = $subcommand ? [$subcommand, '--help'] : ['--help'];

        return $this->runCommand('', $args);
    }

    // Output helpers that do not throw
    public function tryRun(string $command, array $args = [], array $env = []): \Roberts\HardhatLaravel\Support\HardhatResult
    {
        $pending = Process::path($this->projectPath);
        if (! empty($env)) {
            $pending = $pending->env($env);
        }
        $result = $pending->run(array_merge(['npx', 'hardhat', $command], $args));

        return \Roberts\HardhatLaravel\Support\HardhatResult::fromProcessResult($result);
    }

    public function tryRunScript(string $scriptPath, array $args = [], array $env = []): \Roberts\HardhatLaravel\Support\HardhatResult
    {
        return $this->tryRun('run', array_merge([$scriptPath], $args), $env);
    }
    
    /**
     * Run a Hardhat command and stream output via callback. Returns a structured result.
     * The callback signature is fn(string $type, string $buffer): void where $type is 'out' or 'err'.
     */
    public function runStreaming(string $command, array $args = [], array $env = [], ?callable $onOutput = null): \Roberts\HardhatLaravel\Support\HardhatResult
    {
        $pending = Process::path($this->projectPath);
        if (! empty($env)) {
            $pending = $pending->env($env);
        }

        $callback = null;
        if ($onOutput) {
            $callback = function (string $type, string $buffer) use ($onOutput) {
                $onOutput($type, $buffer);
            };
        }

        $result = $pending->run(array_merge(['npx', 'hardhat', $command], $args), $callback);

        return \Roberts\HardhatLaravel\Support\HardhatResult::fromProcessResult($result);
    }

    public function runScriptStreaming(string $scriptPath, array $args = [], array $env = [], ?callable $onOutput = null): \Roberts\HardhatLaravel\Support\HardhatResult
    {
        return $this->runStreaming('run', array_merge([$scriptPath], $args), $env, $onOutput);
    }
}
