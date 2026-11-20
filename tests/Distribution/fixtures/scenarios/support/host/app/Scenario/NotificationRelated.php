<?php

declare(strict_types=1);

namespace App\Scenario;

use Illuminate\Database\Eloquent\Model;

final class NotificationRelated extends Model
{
    public const string HANDLE = 'scenario.notification-related';

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'scenario_records';

    protected $keyType = 'string';
}
