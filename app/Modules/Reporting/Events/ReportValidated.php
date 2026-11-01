<?php

namespace App\Modules\Reporting\Events;

use App\Modules\Reporting\Models\Report;
use App\Modules\Samples\Models\SampleStudy;

final readonly class ReportValidated
{
    public function __construct(
        public Report $report,
        public SampleStudy $sampleStudy,
        public int $userId,
    ) {}
}
