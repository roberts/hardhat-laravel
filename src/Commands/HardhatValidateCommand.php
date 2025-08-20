<?php

namespace Roberts\HardhatLaravel\Commands;

use Illuminate\Console\Command;
use Roberts\HardhatLaravel\HardhatWrapper;

class HardhatValidateCommand extends Command
{
    protected $signature = 'hardhat:validate
        {--fix : Attempt to fix issues automatically where possible}';

    protected $description = 'Validate Hardhat project setup for Laravel integration';

    public function handle(HardhatWrapper $hardhat): int
    {
        $this->info('🔍 Validating Hardhat project setup...');
        $this->newLine();

        $issues = [];
        $warnings = [];
        $suggestions = [];

        // Check if Hardhat directory exists
        $hardhatPath = $hardhat->getProjectPath();
        if (! is_dir($hardhatPath)) {
            $issues[] = "Hardhat project directory not found at: {$hardhatPath}";
            $this->error("❌ Hardhat project directory not found at: {$hardhatPath}");
            $this->warn('   Expected structure: Laravel app at /path/app, Hardhat at /path/blockchain');
            return self::FAILURE;
        }

        $this->line("✅ Hardhat project directory found at: {$hardhatPath}");

        // Check for package.json
        $packageJsonPath = $hardhatPath.'/package.json';
        if (! file_exists($packageJsonPath)) {
            $issues[] = 'package.json not found in Hardhat project';
            $this->error('❌ package.json not found in Hardhat project');
        } else {
            $this->line('✅ package.json exists');
            
            // Validate package.json content
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            if (! $packageJson) {
                $issues[] = 'package.json is not valid JSON';
                $this->error('❌ package.json is not valid JSON');
            } else {
                // Check for Hardhat dependency
                $hasHardhat = isset($packageJson['devDependencies']['hardhat']) 
                    || isset($packageJson['dependencies']['hardhat']);
                
                if (! $hasHardhat) {
                    $warnings[] = 'Hardhat not found in package.json dependencies';
                    $this->warn('⚠️  Hardhat not found in package.json dependencies');
                    $suggestions[] = 'Run: npm install --save-dev hardhat';
                } else {
                    $this->line('✅ Hardhat dependency found in package.json');
                }
            }
        }

        // Check for Hardhat config
        $configJs = $hardhatPath.'/hardhat.config.js';
        $configTs = $hardhatPath.'/hardhat.config.ts';
        
        if (! file_exists($configJs) && ! file_exists($configTs)) {
            $issues[] = 'Hardhat config file not found (hardhat.config.js or hardhat.config.ts)';
            $this->error('❌ Hardhat config file not found');
        } else {
            $configFile = file_exists($configTs) ? 'hardhat.config.ts' : 'hardhat.config.js';
            $this->line("✅ Hardhat config found: {$configFile}");
        }

        // Check for contracts directory
        $contractsPath = $hardhatPath.'/contracts';
        if (! is_dir($contractsPath)) {
            $warnings[] = 'contracts/ directory not found';
            $this->warn('⚠️  contracts/ directory not found');
            $suggestions[] = 'Create contracts directory: mkdir contracts';
        } else {
            $this->line('✅ contracts/ directory exists');
            
            // Count contract files
            $contractFiles = glob($contractsPath.'/*.sol');
            $contractCount = count($contractFiles);
            
            if ($contractCount === 0) {
                $warnings[] = 'No .sol contract files found in contracts/';
                $this->warn('⚠️  No .sol contract files found in contracts/');
            } else {
                $this->line("✅ Found {$contractCount} contract file(s)");
                if ($this->getOutput()->isVerbose()) {
                    foreach ($contractFiles as $file) {
                        $this->line('   - '.basename($file));
                    }
                }
            }
        }

        // Check for scripts directory
        $scriptsPath = $hardhatPath.'/scripts';
        if (! is_dir($scriptsPath)) {
            $warnings[] = 'scripts/ directory not found';
            $this->warn('⚠️  scripts/ directory not found');
            $suggestions[] = 'Create scripts directory: mkdir scripts';
        } else {
            $this->line('✅ scripts/ directory exists');
            
            // Check for recommended scripts
            $recommendedScripts = [
                'deploy-data.ts' => 'For Laravel evm:deploy integration',
                'deploy-data.js' => 'For Laravel evm:deploy integration',
                'call-data.ts' => 'For Laravel evm:call integration', 
                'call-data.js' => 'For Laravel evm:call integration',
            ];
            
            $foundRecommended = false;
            foreach ($recommendedScripts as $script => $purpose) {
                if (file_exists($scriptsPath.'/'.$script)) {
                    $this->line("✅ Found recommended script: {$script}");
                    $foundRecommended = true;
                }
            }
            
            if (! $foundRecommended) {
                $suggestions[] = 'Consider adding deploy-data.ts/js for Laravel integration';
                $this->warn('⚠️  No recommended integration scripts found');
                if ($this->getOutput()->isVerbose()) {
                    foreach ($recommendedScripts as $script => $purpose) {
                        $this->line("   Suggested: {$script} - {$purpose}");
                    }
                }
            }
        }

        // Check for node_modules (indicates npm install has been run)
        $nodeModulesPath = $hardhatPath.'/node_modules';
        if (! is_dir($nodeModulesPath)) {
            $warnings[] = 'node_modules/ directory not found - dependencies may not be installed';
            $this->warn('⚠️  node_modules/ not found - run npm install in Hardhat project');
            $suggestions[] = 'Run: cd blockchain && npm install';
        } else {
            $this->line('✅ node_modules/ directory exists');
        }

        // Test Hardhat compilation
        if (empty($issues)) {
            $this->info('🔨 Testing Hardhat compilation...');
            try {
                $output = $hardhat->compile();
                $this->line('✅ Hardhat compilation successful');
                if ($this->getOutput()->isVerbose()) {
                    $this->line('   Compilation output:');
                    $this->line('   '.str_replace("\n", "\n   ", trim($output)));
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Hardhat compilation failed: '.$e->getMessage();
                $this->warn('⚠️  Hardhat compilation failed: '.$e->getMessage());
            }
        }

        // Summary
        $this->newLine();
        if (! empty($issues)) {
            $this->error("❌ Found ".count($issues)." critical issue(s) that must be resolved:");
            foreach ($issues as $issue) {
                $this->error("   • {$issue}");
            }
        }

        if (! empty($warnings)) {
            $this->warn("⚠️  Found ".count($warnings)." warning(s):");
            foreach ($warnings as $warning) {
                $this->warn("   • {$warning}");
            }
        }

        if (! empty($suggestions)) {
            $this->info("💡 Suggestions:");
            foreach ($suggestions as $suggestion) {
                $this->info("   • {$suggestion}");
            }
        }

        if (empty($issues) && empty($warnings)) {
            $this->info('🎉 Hardhat project is properly configured for Laravel integration!');
            return self::SUCCESS;
        }

        if (empty($issues)) {
            $this->info('✅ No critical issues found. Consider addressing warnings for optimal setup.');
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
