<?php

namespace Pterodactyl\Http\Controllers\Admin\Nests;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Eggs\EggUpdateService;
use Pterodactyl\Services\Eggs\EggCreationService;
use Pterodactyl\Services\Eggs\EggDeletionService;
use Pterodactyl\Http\Requests\Admin\Egg\EggFormRequest;
use Pterodactyl\Contracts\Repository\EggRepositoryInterface;
use Pterodactyl\Contracts\Repository\NestRepositoryInterface;

class EggController extends Controller
{
    public function __construct(
        protected AlertsMessageBag $alert,
        protected EggCreationService $creationService,
        protected EggDeletionService $deletionService,
        protected EggRepositoryInterface $repository,
        protected EggUpdateService $updateService,
        protected NestRepositoryInterface $nestRepository,
        protected ViewFactory $view,
    ) {
    }

    public function create(): View
    {
        $nests = $this->nestRepository->getWithEggs();
        \JavaScript::put(['nests' => $nests->keyBy('id')]);

        return view('admin.eggs.new', ['nests' => $nests]);
    }

    public function store(EggFormRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['docker_images'] = $this->normalizeDockerImages($data['docker_images'] ?? null);

        $icon = $request->file('icon');
        unset($data['icon'], $data['remove_icon']);
        if ($icon instanceof UploadedFile) {
            $data['icon_path'] = $this->storeEggIcon($icon);
        }

        $egg = $this->creationService->handle($data);
        $this->alert->success(trans('admin/nests.eggs.notices.egg_created'))->flash();

        return redirect()->route('admin.nests.egg.view', $egg->id);
    }

    public function view(Egg $egg): View
    {
        return view('admin.eggs.view', [
            'egg' => $egg,
            'images' => array_map(
                fn ($key, $value) => $key === $value ? $value : "$key|$value",
                array_keys($egg->docker_images),
                $egg->docker_images,
            ),
        ]);
    }

    public function update(EggFormRequest $request, Egg $egg): RedirectResponse
    {
        $data = $request->validated();
        $data['docker_images'] = $this->normalizeDockerImages($data['docker_images'] ?? null);

        $icon = $request->file('icon');
        $removeIcon = (bool) ($data['remove_icon'] ?? false);
        unset($data['icon'], $data['remove_icon']);

        if ($icon instanceof UploadedFile) {
            $newPath = $this->storeEggIcon($icon);
            if ($egg->icon_path) {
                Storage::disk('public')->delete($egg->icon_path);
            }
            $data['icon_path'] = $newPath;
        } elseif ($removeIcon) {
            if ($egg->icon_path) {
                Storage::disk('public')->delete($egg->icon_path);
            }
            $data['icon_path'] = null;
        }

        $this->updateService->handle($egg, $data);
        $this->alert->success(trans('admin/nests.eggs.notices.updated'))->flash();

        return redirect()->route('admin.nests.egg.view', $egg->id);
    }

    public function destroy(Egg $egg): RedirectResponse
    {
        if ($egg->icon_path) {
            Storage::disk('public')->delete($egg->icon_path);
        }

        $this->deletionService->handle($egg->id);
        $this->alert->success(trans('admin/nests.eggs.notices.deleted'))->flash();

        return redirect()->route('admin.nests.view', $egg->nest_id);
    }

    protected function storeEggIcon(UploadedFile $file): string
    {
        return $file->storePublicly('egg-icons', ['disk' => 'public']);
    }

    protected function normalizeDockerImages(?string $input = null): array
    {
        $data = array_map(fn ($value) => trim($value), explode("\n", $input ?? ''));

        $images = [];
        foreach ($data as $value) {
            $parts = explode('|', $value, 2);
            $images[$parts[0]] = empty($parts[1]) ? $parts[0] : $parts[1];
        }

        return $images;
    }
}
