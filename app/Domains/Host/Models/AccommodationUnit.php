<?php

declare(strict_types=1);

namespace App\Domains\Host\Models;

if (! class_exists(\App\Domains\Host\Models\AccommodationUnit::class, false)) {
    class_alias(\App\Domains\Stay\Models\AccommodationUnit::class, \App\Domains\Host\Models\AccommodationUnit::class);
}
