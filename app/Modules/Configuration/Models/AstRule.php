<?php

namespace App\Modules\Configuration\Models;

use App\Modules\Configuration\Enums\AstRuleEffect;
use App\Modules\Configuration\Enums\Interpretation;
use App\Modules\Configuration\Enums\OrganismGroup;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Regla de antibiograma (revisión de marchas C2): para ciertos grupos de germen y muestras
 * (sin muestras = todas), y opcionalmente cuando una prueba dio cierto resultado, a ciertos
 * antibióticos no se los informa, se les agrega un comentario o se les fija la interpretación.
 * Se aplica al leer el antibiograma y queda anotada en el resultado.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property AstRuleEffect $effect
 * @property Interpretation|null $interpretation
 * @property string|null $comment
 * @property SupportCollection<int, OrganismGroup> $organism_groups
 * @property int|null $identification_test_id
 * @property string|null $test_result_code
 * @property bool $is_active
 * @property bool $is_reference_template
 * @property int $lock_version
 * @property-read Collection<int, Antibiotic> $antibiotics
 * @property-read Collection<int, SampleType> $sampleTypes
 * @property-read IdentificationTest|null $test
 */
class AstRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effect' => AstRuleEffect::class,
            'interpretation' => Interpretation::class,
            'organism_groups' => AsEnumCollection::of(OrganismGroup::class),
            'is_active' => 'boolean',
            'is_reference_template' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Antibiotic, $this>
     */
    public function antibiotics(): BelongsToMany
    {
        return $this->belongsToMany(Antibiotic::class, 'ast_rule_antibiotics');
    }

    /**
     * @return BelongsToMany<SampleType, $this>
     */
    public function sampleTypes(): BelongsToMany
    {
        return $this->belongsToMany(SampleType::class, 'ast_rule_sample_types');
    }

    /**
     * @return BelongsTo<IdentificationTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(IdentificationTest::class, 'identification_test_id');
    }

    /**
     * Describe la regla en una línea, para la nota del resultado.
     */
    public function note(): string
    {
        return match ($this->effect) {
            AstRuleEffect::Suppress => "No se informa ({$this->name})",
            AstRuleEffect::Comment => (string) $this->comment,
            AstRuleEffect::Interpret => "Interpretación {$this->interpretation?->value} por regla experta: {$this->name}".($this->comment ? ". {$this->comment}" : ''),
        };
    }
}
