<?php

namespace App\Models;

use App\Enums\BookCondition;
use App\Enums\ReadingStatus;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'isbn',
        'publisher',
        'publication_year',
        'genre',
        'language',
        'pages',
        'reading_status',
        'rating',
        'notes',
        'condition',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'pages' => 'integer',
            'rating' => 'integer',
            'position' => 'integer',
            'reading_status' => ReadingStatus::class,
            'condition' => BookCondition::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Compartment, $this>
     */
    public function compartment(): BelongsTo
    {
        return $this->belongsTo(Compartment::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * The loan in progress flagged by the daily check as lasting more than two months.
     *
     * @return HasOne<Loan, $this>
     */
    public function overdueLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->whereNull('returned_on')->where('is_overdue', true);
    }
}
