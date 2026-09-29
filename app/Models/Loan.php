<?php

namespace App\Models;

use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    protected $fillable = ['borrower_name', 'loaned_on', 'returned_on'];

    protected function casts(): array
    {
        return [
            'loaned_on' => 'date',
            'returned_on' => 'date',
            'is_overdue' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
