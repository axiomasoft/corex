<?php

declare(strict_types=1);

use CoreX\Database\Laravel\BaseModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('persists generated UUIDv7 keys and preserves an explicit UUID key', function (): void {
    Schema::create('identity_probes', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->timestamps();
        $table->softDeletes();
    });

    $model = new class extends BaseModel
    {
        protected $table = 'identity_probes';

        protected $guarded = [];
    };
    $model->save();

    expect(Str::isUuid($model->getKey()))->toBeTrue()
        ->and($model->getKey()[14])->toBe('7')
        ->and($model->getKeyType())->toBe('string')
        ->and($model->getIncrementing())->toBeFalse()
        ->and($model->fresh()->getKey())->toBe($model->getKey());

    $explicitId = '01932f5a-0000-7000-8000-000000000001';
    $explicit = $model->newInstance(['id' => $explicitId]);
    $explicit->save();

    expect($explicit->fresh()->getKey())->toBe($explicitId);
});
