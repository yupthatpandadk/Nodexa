<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\KnowledgebaseArticle;
use Pterodactyl\Models\KnowledgebaseCategory;

class KnowledgebaseController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.knowledgebase.index', [
            'categories' => KnowledgebaseCategory::query()
                ->withCount('articles')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'articles' => KnowledgebaseArticle::query()
                ->with('category')
                ->orderByDesc('updated_at')
                ->limit(250)
                ->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:40',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'published' => 'nullable|boolean',
        ]);

        KnowledgebaseCategory::query()->create([
            'name' => trim($data['name']),
            'slug' => $this->uniqueSlug(KnowledgebaseCategory::class, $data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'icon' => trim((string) ($data['icon'] ?? '')) ?: '📚',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'published' => $request->boolean('published'),
        ]);

        $this->alert->success('Vidensbase-kategorien er oprettet.')->flash();

        return redirect()->route('admin.knowledgebase');
    }

    public function updateCategory(Request $request, KnowledgebaseCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:40',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'published' => 'nullable|boolean',
        ]);

        $category->update([
            'name' => trim($data['name']),
            'slug' => $this->uniqueSlug(KnowledgebaseCategory::class, $data['name'], $category->id),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'icon' => trim((string) ($data['icon'] ?? '')) ?: '📚',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'published' => $request->boolean('published'),
        ]);

        $this->alert->success('Kategorien er opdateret.')->flash();

        return redirect()->route('admin.knowledgebase');
    }

    public function destroyCategory(KnowledgebaseCategory $category): RedirectResponse
    {
        $category->delete();
        $this->alert->success('Kategorien og dens artikler er slettet.')->flash();

        return redirect()->route('admin.knowledgebase');
    }

    public function createArticle(): View
    {
        return view('admin.knowledgebase.article', [
            'article' => new KnowledgebaseArticle(),
            'categories' => KnowledgebaseCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeArticle(Request $request): RedirectResponse
    {
        $data = $this->validateArticle($request);

        $article = KnowledgebaseArticle::query()->create([
            ...$data,
            'slug' => $this->uniqueSlug(KnowledgebaseArticle::class, $data['title']),
            'author_id' => $request->user()->id,
            'published' => $request->boolean('published'),
            'featured' => $request->boolean('featured'),
        ]);

        $this->alert->success('Artiklen er oprettet.')->flash();

        return redirect()->route('admin.knowledgebase.articles.edit', $article);
    }

    public function editArticle(KnowledgebaseArticle $article): View
    {
        return view('admin.knowledgebase.article', [
            'article' => $article,
            'categories' => KnowledgebaseCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function updateArticle(Request $request, KnowledgebaseArticle $article): RedirectResponse
    {
        $data = $this->validateArticle($request);

        $article->update([
            ...$data,
            'slug' => $this->uniqueSlug(KnowledgebaseArticle::class, $data['title'], $article->id),
            'published' => $request->boolean('published'),
            'featured' => $request->boolean('featured'),
        ]);

        $this->alert->success('Artiklen er opdateret.')->flash();

        return redirect()->route('admin.knowledgebase.articles.edit', $article);
    }

    public function destroyArticle(KnowledgebaseArticle $article): RedirectResponse
    {
        $article->delete();
        $this->alert->success('Artiklen er slettet.')->flash();

        return redirect()->route('admin.knowledgebase');
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:10240',
        ]);

        $path = $data['image']->store('knowledgebase', 'local');
        $filename = basename($path);
        $url = route('knowledgebase.media', ['filename' => $filename]);

        return response()->json([
            'url' => $url,
            'markdown' => '![Billede](' . $url . ' "Billedtekst")',
        ]);
    }

    private function validateArticle(Request $request): array
    {
        $data = $request->validate([
            'category_id' => 'required|exists:knowledgebase_categories,id',
            'title' => 'required|string|max:180',
            'summary' => 'nullable|string|max:500',
            'content' => 'required|string|max:200000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);

        $data['category_id'] = (int) $data['category_id'];
        $data['title'] = trim($data['title']);
        $data['summary'] = trim((string) ($data['summary'] ?? '')) ?: null;
        $data['content'] = trim($data['content']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    private function uniqueSlug(string $model, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'artikel';
        $slug = $base;
        $number = 2;

        while ($model::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $number++;
        }

        return $slug;
    }
}
