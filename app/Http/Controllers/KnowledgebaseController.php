<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
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

    public function media(string $filename)
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9._-]+$/', $filename), 404);

        $path = 'knowledgebase/' . $filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
        $helpfulYes = DB::table('nodexa_kb_feedback')->where('article_id', $article->id)->where('helpful', true)->count();
        $helpfulNo = DB::table('nodexa_kb_feedback')->where('article_id', $article->id)->where('helpful', false)->count();

        return view('store.knowledgebase.article', compact('article', 'related', 'renderedContent', 'helpfulYes', 'helpfulNo'));
    }

    public function feedback(Request $request, KnowledgebaseArticle $article)
    {
        abort_unless($article->published, 404);

        $data = $request->validate(['helpful' => 'required|boolean']);
        $user = $request->user();
        $fingerprint = hash('sha256', ($request->ip() ?? '') . '|' . (string) $request->userAgent());

        $query = DB::table('nodexa_kb_feedback')->where('article_id', $article->id);
        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('fingerprint', $fingerprint);
        }
        $query->delete();

        DB::table('nodexa_kb_feedback')->insert([
            'article_id' => $article->id,
            'user_id' => $user?->id,
            'helpful' => (bool) $data['helpful'],
            'fingerprint' => $user ? null : $fingerprint,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('kb_feedback', 'Tak for din feedback.');
    }
}
