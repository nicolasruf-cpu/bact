<?php

use App\Models\User;
use App\Modules\Configuration\Models\SampleType;
use App\Modules\Configuration\Models\Study;
use App\Modules\Identity\Enums\Role;
use App\Modules\Organization\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| Ayudantes compartidos (datos SINTÉTICOS).
*/

function userWithRole(Site $site, Role $role): User
{
    $user = User::factory()->create();
    setPermissionsTeamId($site->id);
    $user->unsetRelation('roles')->assignRole($role->value);
    setPermissionsTeamId(null);

    return $user;
}

/**
 * Recibe una orden con urocultivos por HTTP, como lo haría la pantalla de recepción.
 *
 * @param  list<string>  $barcodes
 */
function receiveUrineOrder(User $user, array $barcodes, string $priority = 'routine', string $sampleType = 'URI-CM'): void
{
    test()->actingAs($user)->post(route('reception.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'document_number' => fake()->unique()->numerify('########'),
        'last_name' => 'Sintético',
        'first_name' => 'Paciente',
        'sex' => 'U',
        'priority' => $priority,
        'samples' => array_map(fn (string $barcode) => [
            'barcode' => $barcode,
            'sample_type_id' => SampleType::where('code', $sampleType)->value('id'),
            'study_ids' => [Study::where('code', 'URO')->value('id')],
        ], $barcodes),
    ])->assertSessionHasNoErrors();
}
