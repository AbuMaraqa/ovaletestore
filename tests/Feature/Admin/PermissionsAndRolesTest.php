<?php

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('syncs one permission per button and keeps role edits on re-seed', function () {
    expect(Permission::query()->pluck('name')->sort()->values()->all())->toBe(collect(Permissions::all())->sort()->values()->all());

    $role = Role::findByName('order-manager');
    $role->revokePermissionTo('customers.view'); // edited in the admin panel
    (new RolesAndPermissionsSeeder)->run();

    expect($role->fresh()->hasPermissionTo('customers.view'))->toBeFalse();
});

it('enforces granular permissions on every screen', function () {
    $products = staffUser('product-manager');
    $this->actingAs($products)->get(route('admin.categories.index'))->assertOk();
    $this->actingAs($products)->get(route('admin.orders.index'))->assertForbidden();
    $this->actingAs($products)->get(route('admin.customers.index'))->assertForbidden();
    $this->actingAs($products)->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($products)->get(route('admin.activity.index'))->assertForbidden();

    $content = staffUser('content-manager');
    $this->actingAs($content)->get(route('admin.settings.edit', 'contact'))->assertOk();
    $this->actingAs($content)->get(route('admin.settings.edit', 'mail'))->assertForbidden();
});

it('lets a role manager create roles but never grant a permission they lack', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->givePermissionTo(['admin.access', 'roles.view', 'roles.create', 'roles.update', 'products.view']);

    $this->actingAs($manager)->post(route('admin.roles.store'), ['name' => 'محاسب', 'permissions' => ['admin.access', 'products.view']])
        ->assertSessionHasNoErrors();
    expect(Role::findByName('محاسب')->permissions->pluck('name')->sort()->values()->all())->toBe(['admin.access', 'products.view']);

    $this->actingAs($manager)->post(route('admin.roles.store'), ['name' => 'مدير طلبات 2', 'permissions' => ['orders.delete']])
        ->assertSessionHasErrors('permissions');
    expect(Role::query()->where('name', 'مدير طلبات 2')->exists())->toBeFalse();
});

it('locks the super-admin role and protects built-in or in-use roles from deletion', function () {
    $admin = staffUser('admin');
    $super = Role::findByName('super-admin');

    $this->actingAs($admin)->put(route('admin.roles.update', $super), ['name' => 'super-admin', 'permissions' => []])->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.roles.destroy', Role::findByName('order-manager')))->assertSessionHas('error');

    $custom = Role::create(['name' => 'مؤقت', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($custom);
    $this->actingAs($admin)->delete(route('admin.roles.destroy', $custom))->assertSessionHas('error');
    expect(Role::query()->whereKey($custom->id)->exists())->toBeTrue();
});

it('records role permission changes in the activity log', function () {
    $admin = staffUser('admin');
    $role = Role::create(['name' => 'مخزن', 'guard_name' => 'web']);

    $this->actingAs($admin)->put(route('admin.roles.update', $role), ['name' => 'مخزن', 'permissions' => ['products.view']])->assertSessionHasNoErrors();

    $entry = Activity::query()->where('subject_type', Role::class)->latest('id')->firstOrFail();
    expect($entry->attribute_changes['attributes']['permissions'])->toBe(['products.view'])
        ->and($entry->causer_id)->toBe($admin->id);
});

it('never lets a user manager create or take over a more powerful account', function () {
    $manager = User::factory()->create(['is_active' => true]);
    $manager->givePermissionTo(['admin.access', 'users.view', 'users.create', 'users.update', 'users.delete', 'products.view']);
    $admin = staffUser('admin');

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'دخيل', 'email' => 'x@example.test', 'password' => 'Secret-Pass-123', 'password_confirmation' => 'Secret-Pass-123', 'roles' => ['admin'],
    ])->assertSessionHasErrors('roles.0');
    expect(User::query()->where('email', 'x@example.test')->exists())->toBeFalse();

    $this->actingAs($manager)->put(route('admin.users.update', $admin), ['name' => 'x', 'email' => 'taken@example.test'])->assertForbidden();
    $this->actingAs($manager)->delete(route('admin.users.destroy', $admin))->assertForbidden();
});

it('only checks permissions that exist in the registry', function () {
    $pattern = '/(?:authorize|can|permission)\(\s*[\'"`]([a-z_]+\.[a-z_.]+)[\'"`]/';
    $used = collect(File::allFiles(app_path()))->merge(File::allFiles(resource_path('js')))
        ->flatMap(fn ($file) => preg_match_all($pattern, $file->getContents(), $m) ? $m[1] : [])
        ->unique()->values();

    expect($used->diff(Permissions::all())->values()->all())->toBe([]);
});
