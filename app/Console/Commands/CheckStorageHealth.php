<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckStorageHealth extends Command
{
    protected $signature = 'storage:check {--disk=* : Disk name(s) to check}';

    protected $description = 'Write, read, verify, and delete a probe file on the specified storage disks.';

    public function handle(): int
    {
        $disks = $this->option('disk') ?: ['public', 'enterprise_backups'];
        $failed = false;

        foreach ($disks as $diskName) {
            $diskName = (string) $diskName;
            $disk = Storage::disk($diskName);
            $path = sprintf('storage-check-%s.txt', bin2hex(random_bytes(8)));
            $payload = sprintf(
                "madeena-cp-storage-check\n%s\n%s\n",
                $diskName,
                hash('sha256', random_bytes(32)),
            );

            $this->line("Checking {$diskName}...");

            try {
                if (! $disk->put($path, $payload)) {
                    throw new \RuntimeException('write returned false');
                }

                $readBack = $disk->get($path);

                if ($readBack !== $payload) {
                    throw new \RuntimeException('readback content mismatch');
                }

                if (! $disk->delete($path)) {
                    throw new \RuntimeException('delete returned false');
                }

                $this->info("{$diskName}: ok");
            } catch (\Throwable $exception) {
                $failed = true;
                $errorMessage = $exception->getMessage();

                if ($exception->getPrevious()) {
                    $errorMessage .= ' (Caused by: '.$exception->getPrevious()->getMessage().')';
                }

                $this->error("{$diskName} failure: {$errorMessage}");

                if ($this->getOutput()->isVerbose()) {
                    $this->error('Exception: '.get_class($exception));

                    if ($exception->getPrevious()) {
                        $this->error('Previous Exception: '.get_class($exception->getPrevious()));
                    }

                    $this->line($exception->getTraceAsString());
                }

                try {
                    $disk->delete($path);
                } catch (\Throwable) {
                    //
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
