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
}
