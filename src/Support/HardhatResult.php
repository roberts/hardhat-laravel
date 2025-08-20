<?php

namespace Roberts\HardhatLaravel\Support;

use Illuminate\Contracts\Process\ProcessResult;

class HardhatResult
{
    public function __construct(
        public readonly string $output,
        public readonly string $errorOutput,
        public readonly int $exitCode,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    public static function fromProcessResult(ProcessResult $result): self
    {
        return new self($result->output(), $result->errorOutput(), $result->exitCode());
    }

    public function toArray(): array
    {
        return [
            'output' => $this->output,
            'error' => $this->errorOutput,
            'exitCode' => $this->exitCode,
            'successful' => $this->successful(),
        ];
    }
}
