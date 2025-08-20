<?php

namespace Roberts\HardhatLaravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Roberts\HardhatLaravel\Services\VerifyService;
use Roberts\Web3Laravel\Models\Contract;

class VerifyContractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $contractId,
        public string $network,
        public array $constructorArgs = [],
        public array $env = [],
    ) {}

    public function handle(VerifyService $verify): void
    {
        $contract = Contract::query()->find($this->contractId);
        if (! $contract) {
            return;
        }

        $address = $contract->address;
        try {
            $out = $verify->verify($address, $this->network, $this->constructorArgs, $this->env);
            /** @var array<string,mixed> $meta */
            $meta = (array) $contract->getAttribute('meta');
            $meta['verify'] = [
                'status' => 'ok',
                'output' => $out,
            ];
            $contract->setAttribute('meta', $meta);
            $contract->save();
        } catch (\Throwable $e) {
            /** @var array<string,mixed> $meta */
            $meta = (array) $contract->getAttribute('meta');
            $meta['verify'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
            $contract->setAttribute('meta', $meta);
            $contract->save();
            $this->release(60);
        }
    }
}
