<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShowFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $venue_label
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Performance> $performances
 */
class Show extends Model
{
    /** @use HasFactory<ShowFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'venue_label',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
    ];

    /**
     * @return HasMany<Performance, $this>
     */
    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }
}
