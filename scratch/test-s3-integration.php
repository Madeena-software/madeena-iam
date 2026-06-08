<?php

declare(strict_types=1);

# ═══════════════════════════════════════════════════════════════════════════════
# SIMAMA — Official AWS S3 SDK CRUD & Overwrite Integration Test
# ═══════════════════════════════════════════════════════════════════════════════

echo "================================================================\n";
echo "      STARTING AWS S3 SDK CRUD & OVERWRITE TEST                \n";
echo "================================================================\n";

# 1. Load Composer Autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "❌ ERROR: Composer autoloader not found! Please run 'composer install' first.\n";
    exit(1);
}
require $autoloadPath;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function resolveS3Endpoint(?string $parsedEndpoint = null): string
{
    if (is_string($parsedEndpoint) && trim($parsedEndpoint) !== '') {
        return trim($parsedEndpoint);
    }

    $configuredEndpoint = getenv('AWS_ENDPOINT');
    if (is_string($configuredEndpoint) && trim($configuredEndpoint) !== '') {
        return trim($configuredEndpoint);
    }

    $dockerEndpoint = getenv('AWS_ENDPOINT_DOCKER');
    if (is_string($dockerEndpoint) && trim($dockerEndpoint) !== '') {
        return trim($dockerEndpoint);
    }

    if (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER') === '1') {
        return 'http://host.docker.internal:9000';
    }

    return 'http://127.0.0.1:9000';
}

# 2. Parse .env.local manually to get S3 credentials
$envPath = __DIR__ . '/../.env.local';
if (!file_exists($envPath)) {
    echo "❌ ERROR: .env.local file not found at $envPath\n";
    exit(1);
}

echo "[*] Parsing S3 credentials from .env.local...\n";
$config = [];
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) {
        continue;
    }
    if (str_contains($line, '=')) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (in_array($key, [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_DEFAULT_REGION',
            'AWS_BUCKET',
            'AWS_ENDPOINT',
            'AWS_ENDPOINT_DOCKER'
        ])) {
            $config[$key] = $value;
        }
    }
}

# Verify configuration
$requiredKeys = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'AWS_BUCKET'];
foreach ($requiredKeys as $key) {
    if (!isset($config[$key]) || empty($config[$key])) {
        echo "❌ ERROR: Missing required configuration '$key' in .env.local!\n";
        exit(1);
    }
}

$config['AWS_ENDPOINT'] = resolveS3Endpoint($config['AWS_ENDPOINT'] ?? null);

echo "  ✅ AWS_ENDPOINT: " . $config['AWS_ENDPOINT'] . "\n";
echo "  ✅ AWS_BUCKET:   " . $config['AWS_BUCKET'] . "\n";
echo "  ✅ AWS_REGION:   " . $config['AWS_DEFAULT_REGION'] . "\n\n";

# 3. Instantiate S3 Client using the official AWS SDK
echo "[*] Initializing AWS S3 Client...\n";
$s3 = new S3Client([
    'version'     => 'latest',
    'region'      => $config['AWS_DEFAULT_REGION'],
    'endpoint'    => $config['AWS_ENDPOINT'],
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key'    => $config['AWS_ACCESS_KEY_ID'],
        'secret' => $config['AWS_SECRET_ACCESS_KEY'],
    ],
    'http'        => [
        'timeout'         => 300, // Response timeout in seconds (slow FUSE writes)
        'connect_timeout' => 30,  // Connection timeout in seconds
    ],
]);

$bucket = $config['AWS_BUCKET'];
$testKey = 'wsl-integration-test-' . uniqid() . '.txt';

try {
    # A. Check/Create Bucket
    echo "[*] 1. Verifying bucket 's3://$bucket'...\n";
    if (!$s3->doesBucketExist($bucket)) {
        echo "  ℹ️ Bucket does not exist. Creating 's3://$bucket'...\n";
        $s3->createBucket(['Bucket' => $bucket]);
        echo "  ✅ Bucket created successfully.\n";
    } else {
        echo "  ✅ Bucket exists and is accessible.\n";
    }

    # =========================================================================
    # PHASE I: Basic Lifecycle (Create, Read, Delete)
    # =========================================================================
    echo "\n--- PHASE I: BASIC LIFECYCLE (CREATE -> READ -> DELETE) ---\n";

    # B. CREATE (Upload initial file)
    $payload1 = "Initial payload created at " . date('Y-m-d H:i:s') . "\n";
    echo "[*] 2. Uploading initial file to 's3://$bucket/$testKey'...\n";
    $s3->putObject([
        'Bucket' => $bucket,
        'Key'    => $testKey,
        'Body'   => $payload1,
        'ContentLength' => strlen($payload1),
        'ContentType' => 'text/plain',
    ]);
    echo "  ✅ Uploaded initial file.\n";

    # C. READ (Verify content)
    echo "[*] 3. Reading and verifying initial content...\n";
    $result = $s3->getObject([
        'Bucket' => $bucket,
        'Key'    => $testKey,
    ]);
    $content = (string) $result['Body'];
    if ($content === $payload1) {
        echo "  ✅ PASS: Content matches initial payload.\n";
    } else {
        throw new \Exception("Content mismatch on initial read! Got: '$content', Expected: '$payload1'");
    }

    # D. DELETE
    echo "[*] Pausing 15 seconds for VFS cache background sync to finalize...\n";
    sleep(15);
    echo "[*] 4. Deleting test object 's3://$bucket/$testKey'...\n";
    $s3->deleteObject([
        'Bucket' => $bucket,
        'Key'    => $testKey,
    ]);
    echo "  ✅ Deleted test object.\n";

    # E. VERIFY DELETION
    echo "[*] 5. Verifying object is no longer accessible...\n";
    if ($s3->doesObjectExistV2($bucket, $testKey)) {
        throw new \Exception("Object still exists after deletion attempt!");
    } else {
        echo "  ✅ PASS: Object successfully deleted and not found.\n";
    }

    # =========================================================================
    # PHASE II: Overwrite Lifecycle (Create, Overwrite, Read, Delete)
    # =========================================================================
    echo "\n--- PHASE II: OVERWRITE LIFECYCLE (CREATE -> OVERWRITE -> READ -> DELETE) ---\n";

    # F. CREATE (Upload initial file again)
    $testKey2 = 'wsl-integration-overwrite-' . uniqid() . '.txt';
    $payload1_2 = "Initial payload for overwrite test created at " . date('Y-m-d H:i:s') . "\n";
    echo "[*] 6. Uploading initial file to 's3://$bucket/$testKey2'...\n";
    $s3->putObject([
        'Bucket' => $bucket,
        'Key'    => $testKey2,
        'Body'   => $payload1_2,
        'ContentLength' => strlen($payload1_2),
        'ContentType' => 'text/plain',
    ]);
    echo "  ✅ Uploaded initial file.\n";

    # G. READ (Verify content)
    echo "[*] 7. Reading and verifying initial content...\n";
    $result = $s3->getObject([
        'Bucket' => $bucket,
        'Key'    => $testKey2,
    ]);
    $content = (string) $result['Body'];
    if ($content === $payload1_2) {
        echo "  ✅ PASS: Content matches initial payload.\n";
    } else {
        throw new \Exception("Content mismatch on initial read! Got: '$content', Expected: '$payload1_2'");
    }

    # H. UPDATE / OVERWRITE (Upload new content to the same key)
    echo "[*] Pausing 15 seconds for initial upload VFS write-back to settle...\n";
    sleep(15);
    $payload2 = "Overwritten payload updated at " . date('Y-m-d H:i:s') . "\nThis is a test of file overwriting without locks.";
    echo "[*] 8. Overwriting file with new content...\n";
    $s3->putObject([
        'Bucket' => $bucket,
        'Key'    => $testKey2,
        'Body'   => $payload2,
        'ContentLength' => strlen($payload2),
        'ContentType' => 'text/plain',
    ]);
    echo "  ✅ Overwrote file successfully.\n";

    # I. READ (Verify overwritten content)
    echo "[*] 9. Reading and verifying overwritten content...\n";
    $result = $s3->getObject([
        'Bucket' => $bucket,
        'Key'    => $testKey2,
    ]);
    $content = (string) $result['Body'];
    if ($content === $payload2) {
        echo "  ✅ PASS: Content matches overwritten payload.\n";
    } else {
        throw new \Exception("Content mismatch on overwrite read! Got: '$content', Expected: '$payload2'");
    }

    # J. DELETE
    echo "[*] Pausing 15 seconds for VFS cache background sync to finalize...\n";
    sleep(15);
    echo "[*] 10. Deleting test object 's3://$bucket/$testKey2'...\n";
    $s3->deleteObject([
        'Bucket' => $bucket,
        'Key'    => $testKey2,
    ]);
    echo "  ✅ Deleted test object.\n";

    # K. VERIFY DELETION
    echo "[*] 11. Verifying object is no longer accessible...\n";
    if ($s3->doesObjectExistV2($bucket, $testKey2)) {
        throw new \Exception("Object still exists after deletion attempt!");
    } else {
        echo "  ✅ PASS: Object successfully deleted and not found.\n";
    }

    echo "\n";
    echo "════════════════════════════════════════════════════════════════\n";
    echo "  🎉 SUCCESS: ALL S3 CRUD & OVERWRITE OPERATIONS PASSED!       \n";
    echo "════════════════════════════════════════════════════════════════\n";

} catch (AwsException $e) {
    echo "❌ AWS S3 EXCEPTION FAILED:\n";
    echo "  Message: " . $e->getAwsErrorMessage() . "\n";
    echo "  Type:    " . $e->getAwsErrorType() . "\n";
    echo "  Code:    " . $e->getAwsErrorCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "❌ GENERAL EXCEPTION FAILED:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    exit(1);
}
