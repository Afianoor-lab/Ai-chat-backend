<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{   
    use SoftDeletes;
    
        protected $fillable = [
        'user_id', 'project_id', 'title', 'model', 'persona', 'pinned', 'archived',
         'locked', 'deleted_at'
    ];
    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'archived' => 'boolean',
            'locked' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }    
   


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
