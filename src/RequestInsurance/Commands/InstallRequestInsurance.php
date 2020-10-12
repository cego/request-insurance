<?php

namespace Cego\RequestInsurance\Commands;

use Illuminate\Console\Command;

class InstallRequestInsurance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'install:request-insurance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install RequestInsurance';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Installing RequestInsurance');

        // Publish files
        $this->call('vendor:publish', [
            '--provider' => "Cego\RequestInsurance\RequestInsuranceServiceProvider",
            '--force'    => true
        ]);

        // Add Files to gitignore if not already present
        $pathToGitIgnoreFile = base_path() . DIRECTORY_SEPARATOR . '.gitignore';

        $filesThatNeedsToBeAdded = collect([
            '/app/RequestInsurance.php',
            '/app/RequestInsuranceLog.php',
            '/config/request-insurance.php',
        ]);

        // We need to make sure we do not add existing lines to gitignore
        $currentGitIgnoreContents = file_get_contents($pathToGitIgnoreFile);
        $currentFiles = collect(explode("\n", $currentGitIgnoreContents));

        // Reject files that already exist in gitignore
        $filesThatNeedsToBeAdded = $filesThatNeedsToBeAdded
            ->reject(function ($file) use ($currentFiles) {
                foreach ($currentFiles as $currentFile) {
                    if ($file == $currentFile) {
                        return true;
                    }
                }

                $this->info(sprintf('Adding file to .gitignore <comment>[%s]</comment>', $file));

                return false;
            });

        // Write files to gitignore files
        file_put_contents($pathToGitIgnoreFile, $filesThatNeedsToBeAdded->implode("\n") . "\n", FILE_APPEND);

        return 0;
    }
}
