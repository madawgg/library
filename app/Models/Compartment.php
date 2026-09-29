<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compartment extends Model
{
    protected $fillable = ['number', 'name'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Shelf, $this>
     */
    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
