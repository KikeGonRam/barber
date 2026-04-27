<?php

namespace Tests\Unit\WhiteBox;

use App\Models\Inventory;
use App\Models\User;
use App\Policies\InventoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    private InventoryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new InventoryPolicy;
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_view_any(): void
    {
        $admin = $this->makeUser('administrador');

        $this->assertTrue($this->policy->viewAny($admin));
    }

    public function test_recepcionista_can_view_any(): void
    {
        $user = $this->makeUser('recepcionista');

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_barbero_can_view_any(): void
    {
        $user = $this->makeUser('barbero');

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_cliente_cannot_view_any(): void
    {
        $user = $this->makeUser('cliente');

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_admin_can_view_single(): void
    {
        $admin = $this->makeUser('administrador');
        $inventory = Inventory::factory()->create();

        $this->assertTrue($this->policy->view($admin, $inventory));
    }

    public function test_cliente_cannot_view_single(): void
    {
        $user = $this->makeUser('cliente');
        $inventory = Inventory::factory()->create();

        $this->assertFalse($this->policy->view($user, $inventory));
    }

    public function test_admin_can_create(): void
    {
        $admin = $this->makeUser('administrador');

        $this->assertTrue($this->policy->create($admin));
    }

    public function test_recepcionista_can_create(): void
    {
        $user = $this->makeUser('recepcionista');

        $this->assertTrue($this->policy->create($user));
    }

    public function test_barbero_cannot_create(): void
    {
        $user = $this->makeUser('barbero');

        $this->assertFalse($this->policy->create($user));
    }

    public function test_admin_can_update(): void
    {
        $admin = $this->makeUser('administrador');
        $inventory = Inventory::factory()->create();

        $this->assertTrue($this->policy->update($admin, $inventory));
    }

    public function test_recepcionista_can_update(): void
    {
        $user = $this->makeUser('recepcionista');
        $inventory = Inventory::factory()->create();

        $this->assertTrue($this->policy->update($user, $inventory));
    }

    public function test_barbero_cannot_update(): void
    {
        $user = $this->makeUser('barbero');
        $inventory = Inventory::factory()->create();

        $this->assertFalse($this->policy->update($user, $inventory));
    }

    public function test_admin_can_delete(): void
    {
        $admin = $this->makeUser('administrador');
        $inventory = Inventory::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $inventory));
    }

    public function test_non_admin_cannot_delete(): void
    {
        $user = $this->makeUser('recepcionista');
        $inventory = Inventory::factory()->create();

        $this->assertFalse($this->policy->delete($user, $inventory));
    }

    public function test_admin_can_restore(): void
    {
        $admin = $this->makeUser('administrador');
        $inventory = Inventory::factory()->create();

        $this->assertTrue($this->policy->restore($admin, $inventory));
    }

    public function test_non_admin_cannot_restore(): void
    {
        $user = $this->makeUser('barbero');
        $inventory = Inventory::factory()->create();

        $this->assertFalse($this->policy->restore($user, $inventory));
    }

    public function test_admin_can_force_delete(): void
    {
        $admin = $this->makeUser('administrador');
        $inventory = Inventory::factory()->create();

        $this->assertTrue($this->policy->forceDelete($admin, $inventory));
    }

    public function test_non_admin_cannot_force_delete(): void
    {
        $user = $this->makeUser('recepcionista');
        $inventory = Inventory::factory()->create();

        $this->assertFalse($this->policy->forceDelete($user, $inventory));
    }

    public function test_before_returns_true_for_admin(): void
    {
        $admin = $this->makeUser('administrador');

        $this->assertTrue($this->policy->before($admin, 'delete'));
    }

    public function test_before_returns_null_for_non_admin(): void
    {
        $user = $this->makeUser('barbero');

        $this->assertNull($this->policy->before($user, 'delete'));
    }
}
