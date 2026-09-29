<?php

namespace App\Models;

use Database\Factories\BookcaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Bookcase extends Model
{
    /** @use HasFactory<BookcaseFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<Shelf, $this>
     */
    public function shelves(): HasMany
    {
        return $this->hasMany(Shelf::class);
    }

    /**
     * @return HasManyThrough<Compartment, Shelf, $this>
     */
    public function compartments(): HasManyThrough
    {
        return $this->hasManyThrough(Compartment::class, Shelf::class);
    }
}
