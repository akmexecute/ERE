<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Administration\Models\AuditLog;
use App\Domains\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = new AuditLog();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit logs are append-only and cannot be deleted.');
        $log->delete();
    }

    public function test_auditable_model_automatically_writes_audit_log_on_create_and_redacts_sensitive_keys(): void
    {
        $user = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Almaz Ayana',
            'phone' => '+251911000111',
            'password' => 'secret-plain-text',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => (string) $user->id,
            'action' => 'User.created',
        ]);

        $log = AuditLog::query()
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', (string) $user->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertSame('Almaz Ayana', $log->new_values['name']);
    }

    public function test_auditable_model_writes_audit_log_on_update_with_reason(): void
    {
        $user = User::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Haile Gebrselassie',
            'phone' => '+251911222333',
            'status' => 'active',
        ]);

        $user->withAuditReason('Promoting seller to verification level 2')
            ->forceFill(['verification_level' => 2])
            ->save();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => (string) $user->id,
            'action' => 'User.updated',
            'reason' => 'Promoting seller to verification level 2',
        ]);
    }
}
