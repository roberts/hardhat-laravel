<?php

namespace Roberts\HardhatLaravel\Services;

use Roberts\Web3Laravel\Enums\TokenType;

class TokenDetectionService
{
    /**
     * Detect token standard from ABI.
     */
    public function detect(?array $abi): ?TokenType
    {
        if (! is_array($abi) || empty($abi)) {
            return null;
        }

    $has = fn(string $name, ?array $inputs = null) => $this->hasFunction($abi, $name, $inputs);

        // Heuristic detection
        $isErc20 = $has('totalSupply') && $has('decimals') && $has('symbol') && $has('name') && $has('balanceOf', [['type' => 'address']]);
        if ($isErc20) {
            return TokenType::ERC20;
        }

        // ERC-721 typical surface
        $erc721Fns = $has('ownerOf') && $has('balanceOf') && ($has('safeTransferFrom') || $has('transferFrom'));
        // ERC-1155 typical surface
        $erc1155Fns = $has('balanceOf', [['type' => 'address'], ['type' => 'uint256']]) && $has('balanceOfBatch') && $has('safeTransferFrom', [['type' => 'address'], ['type' => 'address'], ['type' => 'uint256'], ['type' => 'uint256']]);

        if ($erc1155Fns) {
            return TokenType::ERC1155;
        }
        if ($erc721Fns) {
            return TokenType::ERC721;
        }

        return null;
    }

    private function hasFunction(array $abi, string $name, ?array $inputsSpec = null): bool
    {
        foreach ($abi as $item) {
            if (($item['type'] ?? '') !== 'function') {
                continue;
            }
            if (($item['name'] ?? '') !== $name) {
                continue;
            }
            if ($inputsSpec === null) {
                return true;
            }
            $inputs = $item['inputs'] ?? [];
            if (count($inputs) < count($inputsSpec)) {
                continue;
            }
            $ok = true;
            foreach ($inputsSpec as $i => $spec) {
                if (! isset($inputs[$i]['type']) || stripos((string) $inputs[$i]['type'], (string) $spec['type']) === false) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return true;
            }
        }

        return false;
    }
}
