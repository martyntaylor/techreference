<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('audit log redacts sensitive fields when creating user', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret-password', // Will be hashed by casts
    ]);

    $auditLog = AuditLog::where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('action', 'created')
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->new_values['password'])->toBe('[REDACTED]');
    expect($auditLog->new_values['name'])->toBe('Test User');
    expect($auditLog->new_values['email'])->toBe('test@example.com');
});

test('audit log redacts sensitive fields when updating user', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $user->update([
        'name' => 'Updated Name',
        'password' => Hash::make('new-password'),
    ]);

    $auditLog = AuditLog::where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('action', 'updated')
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->old_values['password'] ?? null)->toBe('[REDACTED]');
    expect($auditLog->new_values['password'] ?? null)->toBe('[REDACTED]');
    expect($auditLog->new_values['name'])->toBe('Updated Name');
});

test('audit log redacts sensitive fields when deleting user', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    $user = User::factory()->create([
        'password' => Hash::make('secret-password'),
        'remember_token' => 'secret-token-456',
    ]);

    $userId = $user->id;
    $user->delete();

    $auditLog = AuditLog::where('auditable_type', User::class)
        ->where('auditable_id', $userId)
        ->where('action', 'deleted')
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->old_values['password'])->toBe('[REDACTED]');
    expect($auditLog->old_values['remember_token'])->toBe('[REDACTED]');
    expect($auditLog->old_values['name'])->toBe($user->name);
});

test('audit log is only created after database commit', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    // Start transaction
    \DB::beginTransaction();

    try {
        $user = User::create([
            'name' => 'Rollback User',
            'email' => 'rollback@example.com',
            'password' => Hash::make('password'),
        ]);

        // Audit log should not exist yet (before commit)
        $auditLog = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->first();

        expect($auditLog)->toBeNull();

        // Rollback transaction
        \DB::rollBack();

        // User should not exist
        expect(User::find($user->id))->toBeNull();

        // Audit log should still not exist (ghost entry prevented)
        $auditLog = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->first();

        expect($auditLog)->toBeNull();
    } catch (\Exception $e) {
        \DB::rollBack();
        throw $e;
    }
});

test('audit log is created after successful database commit', function () {
    $admin = User::factory()->create();
    actingAs($admin);

    \DB::beginTransaction();

    $user = User::create([
        'name' => 'Committed User',
        'email' => 'commit@example.com',
        'password' => Hash::make('password'),
    ]);

    \DB::commit();

    // After commit, audit log should exist
    $auditLog = AuditLog::where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('action', 'created')
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->user_id)->toBe($admin->id);
});
