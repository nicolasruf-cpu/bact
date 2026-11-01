<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Configuration\Models\ProtocolVersion;
use App\Modules\Organization\Models\Site;
use App\Modules\Patients\Models\Patient;
use App\Modules\Samples\Models\Sample;
use App\Modules\Samples\Models\SampleStudy;
use App\Modules\Workflow\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Alias estables para relaciones polimórficas (actividades, auditoría): la base no
     * guarda nombres de clase PHP. Todo modelo usado como sujeto tiene que estar aquí.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'site' => Site::class,
            'patient' => Patient::class,
            'sample' => Sample::class,
            'sample_study' => SampleStudy::class,
            'protocol_version' => ProtocolVersion::class,
            'activity' => Activity::class,
        ]);
    }
}
