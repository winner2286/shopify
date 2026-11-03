<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_conversation_id',
    'role',
    'content',
    'tool_calls',
    'tool_call_id',
    'tool_name',
    'tokens_input',
    'tokens_output',
    'cost_fcfa',
    'action_required',
])]
class AiMessage extends Model
{
    public const UPDATED_AT = null; // Only created_at

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'action_required' => 'array',
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'cost_fcfa' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    public function isTool(): bool
    {
        return $this->role === 'tool';
    }

    public function hasActionRequired(): bool
    {
        return ! empty($this->action_required) && ($this->action_required['status'] ?? '') === 'pending';
    }

    public function feedbacks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AiFeedback::class, 'ai_message_id');
    }
}
