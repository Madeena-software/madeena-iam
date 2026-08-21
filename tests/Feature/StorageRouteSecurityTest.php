<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageRouteSecurityTest extends TestCase
{
    public function test_public_media_in_allowed_namespace_is_successfully_retrieved(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/test-logo.png', 'fake-image-data');

        $response = $this->get('/storage/logos/test-logo.png');

        $response->assertOk();
        $this->assertEquals('fake-image-data', $response->streamedContent());
    }

    public function test_missing_public_media_returns_404(): void
    {
        Storage::fake('public');

        $response = $this->get('/storage/logos/not-found.png');

        $response->assertNotFound();
    }

    public function test_private_backup_namespaces_are_blocked_and_return_404(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        Storage::fake('enterprise_backups');

        Storage::disk('public')->put('backups/database.sql.gz', 'backup-sql-data');
        Storage::disk('public')->put('madeena-iam-backups/database.sql.gz', 'backup-sql-data');
        Storage::disk('public')->put('storage-check-example.txt', 'health-check-data');
        Storage::disk('public')->put('private/secret.txt', 'secret-data');

        Storage::disk('s3')->put('backups/database.sql.gz', 'backup-sql-data');
        Storage::disk('s3')->put('madeena-iam-backups/database.sql.gz', 'backup-sql-data');
        Storage::disk('enterprise_backups')->put('database.sql.gz', 'backup-sql-data');

        $this->get('/storage/backups/database.sql.gz')->assertNotFound();
        $this->get('/storage/madeena-iam-backups/database.sql.gz')->assertNotFound();
        $this->get('/storage/storage-check-example.txt')->assertNotFound();
        $this->get('/storage/private/secret.txt')->assertNotFound();
        $this->get('/storage/enterprise_backups/database.sql.gz')->assertNotFound();
    }

    public function test_generic_s3_disk_is_not_the_public_storage_source(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        // Placed only on generic s3 disk, not on public disk
        Storage::disk('s3')->put('logos/s3-only-logo.png', 's3-only-content');

        $response = $this->get('/storage/logos/s3-only-logo.png');

        // Since it does not exist on the public disk, it must return 404
        $response->assertNotFound();
    }

    public function test_unsafe_and_traversal_paths_are_rejected_with_404(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/valid-logo.png', 'logo-data');
        Storage::disk('public')->put('backups/database.sql.gz', 'backup-data');

        // Path traversal attempts
        $this->get('/storage/logos/../backups/database.sql.gz')->assertNotFound();
        $this->get('/storage/logos/..')->assertNotFound();
        $this->get('/storage/logos/./valid-logo.png')->assertNotFound();
        $this->get('/storage/logos//valid-logo.png')->assertNotFound();

        // Empty subpath or root-only
        $this->get('/storage/logos/')->assertNotFound();
        $this->get('/storage/logos')->assertNotFound();

        // Non-allowed prefixes
        $this->get('/storage/avatars/user.png')->assertNotFound();
        $this->get('/storage/documents/file.pdf')->assertNotFound();
    }
}
