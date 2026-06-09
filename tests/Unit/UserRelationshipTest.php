<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class UserRelationshipTest extends TestCase
{
    public function test_creator_relationship_definition(): void
    {
        $user = new User;
        $relation = $user->creator();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('created_by', $relation->getForeignKeyName());
        $this->assertEquals('id', $relation->getOwnerKeyName());
        $this->assertEquals(User::class, get_class($relation->getRelated()));
    }

    public function test_updater_relationship_definition(): void
    {
        $user = new User;
        $relation = $user->updater();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('updated_by', $relation->getForeignKeyName());
        $this->assertEquals('id', $relation->getOwnerKeyName());
        $this->assertEquals(User::class, get_class($relation->getRelated()));
    }

    public function test_deleter_relationship_definition(): void
    {
        $user = new User;
        $relation = $user->deleter();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals('deleted_by', $relation->getForeignKeyName());
        $this->assertEquals('id', $relation->getOwnerKeyName());
        $this->assertEquals(User::class, get_class($relation->getRelated()));
    }
}
