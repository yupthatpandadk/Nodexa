<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgebaseArticle extends Model
{
    protected $table = 'knowledgebase_articles';

    protected $fillable = [
        'category_id',
        'author_id',
        'title',
        'slug',
        'summary',
        'content',
        'published',
        'featured',
        'views',
        'sort_order',
    ];

    protected $casts = [
        'published' => 'boolean',
        'featured' => 'boolean',
        'views' => 'integer',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgebaseCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
