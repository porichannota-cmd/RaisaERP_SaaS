<?php

namespace Tests\Unit\Audit;

use App\Domain\Audit\Auditable;
use App\Domain\Tenant\ActiveTenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class DummyAuditableModel extends Model
{
    use Auditable;
    
    protected $table = 'dummy_auditables';
    protected $fillable = ['name'];
}

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Schema::create('dummy_auditables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        ActiveTenantContext::clear();
        Schema::dropIfExists('dummy_auditables');
        parent::tearDown();
    }

    public function test_it_creates_audit_log_on_model_creation()
    {
        ActiveTenantContext::set('TENANT-AUDIT');
        
        $model = DummyAuditableModel::create(['name' => 'Original Name']);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => 'TENANT-AUDIT',
            'event_type' => 'created',
            'auditable_type' => DummyAuditableModel::class,
            'auditable_id' => $model->id,
        ]);
    }

    public function test_it_does_not_audit_when_tenant_is_null()
    {
        ActiveTenantContext::clear();
        
        $model = DummyAuditableModel::create(['name' => 'Original Name']);

        $this->assertDatabaseMissing('audit_logs', [
            'auditable_id' => $model->id,
        ]);
    }
}
