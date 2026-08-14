<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountReviewDecision extends Model
{
    use HasUlids;

    protected $fillable = [
        'account_review_request_id',
        'reviewer_id',
        'decision',
        'reason',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(AccountReviewRequest::class, 'account_review_request_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
