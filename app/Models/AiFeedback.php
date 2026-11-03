<?php

namespace App\Models;

use App\Models\Scopes\BelongsToShopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToShopScope::class])]
#[Fillable([
    'ai_message_id',
    'shop_id',
    'user_id',
    'rating',
    'comment',
    'category',
    'admin_reviewed',
    'admin_notes',
])]
class AiFeedback extends Model
{
    protected $table = 'ai_feedbacks';

    public const RATING_HELPFUL = 'helpful';
    public const RATING_INCORRECT = 'incorrect';
    public const RATING_INCOMPLETE = 'incomplete';
    public const RATING_IRRELEVANT = 'irrelevant';

    protected function casts(): array
    {
        return [
            'admin_reviewed' => 'boolean',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
