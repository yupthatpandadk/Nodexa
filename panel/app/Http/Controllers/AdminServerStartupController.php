<?php
namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\DaemonClient;
use App\Services\ServerTemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminServerStartupController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless((bool)$request->user()->is_admin, 403, 'Administrator access required.');
    }

    public function show(Request $request, Server $server, ServerTemplateCatalog $catalog)
    {
        $this->admin($request);
        $template = $catalog->for($server);
        $environment = $catalog->environmentFor($server);
        $images = $template['docker_images'];
        if ($server->docker_image && !collect($images)->contains(fn (array $image) => $image['value'] === $server->docker_image)) {
            array_unshift($images, ['label' => 'Current image', 'value' => $server->docker_image]);
        }

        $variables = array_map(function (array $variable) use ($environment) {
            $variable['value'] = (string) ($environment[$variable['key']] ?? $variable['default'] ?? '');
            unset($variable['rules']);
            return $variable;
        }, $template['variables']);

        return response()->json([
            'id' => $server->id,
            'identifier' => $server->identifier,
            'name' => $server->name,
            'template' => [
                'slug' => $template['slug'],
                'name' => $template['name'],
                'startup_template' => $template['startup_template'],
            ],
            'startup' => $server->startup,
            'docker_image' => $server->docker_image,
            'docker_images' => $images,
            'environment' => $environment,
            'variables' => $variables,
        ]);
    }

    public function update(
        Request $request,
        Server $server,
        DaemonClient $daemon,
        ServerTemplateCatalog $catalog
    ) {
        $this->admin($request);
        $data = $request->validate([
            'startup' => 'required|string|max:12000',
            'docker_image' => 'required|string|max:500',
            'environment' => 'nullable|array',
        ]);

        $template = $catalog->for($server);
        $allowedImages = collect($template['docker_images'])->pluck('value')->all();
        if ($allowedImages !== [] && $data['docker_image'] !== $server->docker_image && !in_array($data['docker_image'], $allowedImages, true)) {
            throw ValidationException::withMessages(['docker_image' => 'Det valgte Docker-image er ikke tilladt for denne template.']);
        }

        $environment = is_array($data['environment'] ?? null) ? $data['environment'] : [];
        foreach ($template['variables'] as $variable) {
            $key = $variable['key'];
            $value = $environment[$key] ?? $variable['default'] ?? '';
            $validator = Validator::make(['value' => $value], ['value' => $variable['rules']]);
            if ($validator->fails()) {
                throw ValidationException::withMessages([$key => $validator->errors()->first('value')]);
            }
            $environment[$key] = (string) $value;
        }

        $before = [
            'startup' => $server->startup,
            'docker_image' => $server->docker_image,
            'environment' => $server->environment ?? [],
        ];

        $server->update([
            'startup' => $data['startup'],
            'docker_image' => $data['docker_image'],
            'environment' => $environment,
        ]);

        try {
            $daemon->reconfigure($server->fresh()->load('node'));
        } catch (Throwable $e) {
            $server->update($before);
            try {
                $daemon->reconfigure($server->fresh()->load('node'));
            } catch (Throwable) {
                // The original exception is the actionable error returned below.
            }
            return response()->json([
                'message' => 'Startup-indstillingerne kunne ikke anvendes på Nodexa Agent. De gemte værdier er rullet tilbage.',
                'error' => $e->getMessage(),
            ], 502);
        }

        return $this->show($request, $server->fresh(), $catalog);
    }
}
