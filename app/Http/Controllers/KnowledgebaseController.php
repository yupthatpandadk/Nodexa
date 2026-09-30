<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Models\KnowledgebaseArticle;
use Pterodactyl\Models\KnowledgebaseCategory;
use Pterodactyl\Services\Knowledgebase\KnowledgebaseRenderer;

class KnowledgebaseController extends Controller
{
    public function __construct(private KnowledgebaseRenderer $renderer)
    {
    }

    public function index(): View
    {
        $categories = KnowledgebaseCategory::query()
            ->where('published', true)
            ->withCount(['articles' => fn ($query) => $query->where('published', true)])
            ->with(['articles' => fn ($query) => $query
                ->where('published', true)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->limit(5)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $featured = KnowledgebaseArticle::query()
            ->where('published', true)
            ->whereHas('category', fn ($query) => $query->where('published', true))
            ->where('featured', true)
            ->with('category')
            ->orderByDesc('views')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        $popular = KnowledgebaseArticle::query()
            ->where('published', true)
            ->whereHas('category', fn ($query) => $query->where('published', true))
            ->with('category')
            ->orderByDesc('views')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();

        return view('store.knowledgebase.index', compact('categories', 'featured', 'popular'));
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $articles = KnowledgebaseArticle::query()
            ->where('published', true)
            ->whereHas('category', fn ($query) => $query->where('published', true))
            ->with('category')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $like = '%' . $term . '%';
                    $query->where('title', 'like', $like)
                        ->orWhere('summary', 'like', $like)
                        ->orWhere('content', 'like', $like);
                });
            })
            ->orderByDesc('featured')
            ->orderByDesc('views')
            ->limit(50)
            ->get();

        return view('store.knowledgebase.search', compact('term', 'articles'));
    }

    public function category(KnowledgebaseCategory $category): View
    {
        abort_unless($category->published, 404);

        $articles = $category->articles()
            ->where('published', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('store.knowledgebase.category', compact('category', 'articles'));
    }

    public function article(KnowledgebaseArticle $article): View
    {
        $article->load('category');

        abort_unless($article->published && $article->category?->published, 404);

        $article->increment('views');

        $related = KnowledgebaseArticle::query()
            ->where('published', true)
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->orderByDesc('featured')
            ->orderByDesc('views')
            ->limit(5)
            ->get();

        $renderedContent = $this->renderer->render($article->content);

        return view('store.knowledgebase.article', compact('article', 'related', 'renderedContent'));
    }
}
