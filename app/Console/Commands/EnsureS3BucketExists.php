<?php

namespace App\Console\Commands;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Throwable;

class EnsureS3BucketExists extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:ensure-s3-bucket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure that the configured S3 bucket exists, and create it if not.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking S3 bucket configuration...');

        $bucket = config('filesystems.disks.s3.bucket');

        if (empty($bucket)) {
            $this->warn('S3 bucket is not configured. Skipping bucket check/creation.');

            return 0;
        }

        $endpoint = config('filesystems.disks.s3.endpoint');
        $cleanedEndpoint = $endpoint;
        if (! empty($endpoint)) {
            // Clean up potentially malformed port syntax, e.g. /:9000 -> :9000
            $cleanedEndpoint = preg_replace('#/:(\d+)#', ':$1', $endpoint);
            $parsed = parse_url($cleanedEndpoint);
            $host = $parsed['host'] ?? null;
            $port = $parsed['port'] ?? (($parsed['scheme'] ?? 'http') === 'https' ? 443 : 80);

            if ($host) {
                $this->info("Checking S3 endpoint connectivity at {$host}:{$port}...");
                $connection = @fsockopen($host, (int) $port, $errno, $errstr, 2.0);
                if (! $connection) {
                    $this->warn("S3 endpoint {$host}:{$port} is not reachable (Timeout after 2s). Skipping bucket creation.");

                    return 0;
                }
                fclose($connection);
                $this->info('S3 endpoint is reachable.');
            }
        }

        try {
            $config = config('filesystems.disks.s3');

            // Build custom client with fast fail options (no retries, short timeout)
            $s3Config = [
                'version' => 'latest',
                'region' => $config['region'] ?? 'us-east-1',
                'endpoint' => $cleanedEndpoint,
                'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
                'credentials' => new Credentials(
                    $config['key'] ?? '',
                    $config['secret'] ?? ''
                ),
                'http' => [
                    'connect_timeout' => 2.0,
                    'timeout' => 3.0,
                ],
                'retries' => 0, // Fail fast immediately
            ];

            $client = new S3Client($s3Config);

            $this->info("Checking if bucket '{$bucket}' exists...");
            if ($client->doesBucketExistV2($bucket)) {
                $this->info("S3 bucket '{$bucket}' already exists.");

                return 0;
            }

            $this->info("S3 bucket '{$bucket}' does not exist. Attempting to create...");
            $client->createBucket([
                'Bucket' => $bucket,
            ]);

            $this->info("S3 bucket '{$bucket}' successfully created.");

            return 0;
        } catch (Throwable $e) {
            $this->error('Failed to ensure S3 bucket exists: '.$e->getMessage());

            // Since it's a deployment check, return 0 to not block the deployment if it fails due to network/creds
            return 0;
        }
    }
}
