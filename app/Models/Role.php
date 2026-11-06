<?php

declare(strict_types=1);

namespace App\Models;

if (! class_exists(\App\Models\Role::class, false)) {
    class_alias(\App\Domains\User\Models\Role::class, \App\Models\Role::class);
}
