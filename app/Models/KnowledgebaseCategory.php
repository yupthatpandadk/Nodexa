<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgebaseCategory extends Model
{
    protected $table = 'knowledgebase_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
        'published',
    ];

    protected $casts = [
        'published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgebaseArticle::class, 'category_id');
    }
}
