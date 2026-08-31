<?php

namespace App\OmniSignal\Commands;

use Illuminate\Console\Command;
use ZipArchive;

class BuildPluginCommand extends Command
{
    protected $signature = 'omnisignal:build-plugin';

    protected $description = 'Packages the WordPress / WooCommerce plugin into a distributable zip archive in public/downloads/';

    public function handle(): int
    {
        $this->info('📦 Packaging OmniSignal WordPress & WooCommerce Plugin...');

        $sourceDir = base_path('packages/wp-omnisignal');
        $outputDir = public_path('downloads');
        $zipFile = $outputDir.'/omnisignal-woocommerce.zip';

        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return Command::FAILURE;
        }

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if (file_exists($zipFile)) {
            unlink($zipFile);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Failed to create zip file: {$zipFile}");

            return Command::FAILURE;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $count = 0;
        foreach ($files as $name => $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = 'wp-omnisignal/'.substr($filePath, strlen($sourceDir) + 1);

                $zip->addFile($filePath, $relativePath);
                $count++;
            }
        }

        $zip->close();

        $size = round(filesize($zipFile) / 1024, 1);
        $this->info("✅ Successfully packaged {$count} files into {$zipFile} ({$size} KB).");
        $this->line('Download URL: <fg=green>'.url('downloads/omnisignal-woocommerce.zip').'</>');

        return Command::SUCCESS;
    }
}
