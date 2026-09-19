<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Services\Api\KeyCreationService;
use Illuminate\Support\Facades\Log;

class NodeAutoDeployController extends Controller
{
    /**
     * NodeAutoDeployController constructor.
     */
    public function __construct(
        private Encrypter $encrypter,
        private KeyCreationService $keyCreationService,
    ) {
    }

    /**
     * Generates a new API key for the logged-in user with read and write permission
     * to nodes, and returns that as the deployment key for a node.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function __invoke(Request $request, Node $node): JsonResponse
    {
        // Do not query an encrypted/cast token while generating the page response.
        // Fetch only the columns needed here; this also avoids model hydration side effects
        // from unrelated legacy API-key columns.
        $key = ApiKey::query()
            ->select(['id', 'identifier', 'token'])
            ->where('user_id', $request->user()->id)
            ->where('key_type', ApiKey::TYPE_APPLICATION)
            ->where('r_nodes', 3)
            ->first();

        // We couldn't find a key that exists for this user with read and write
        // permission for nodes. Go ahead and create it now.
        if (!$key) {
            $key = $this->keyCreationService->setKeyType(ApiKey::TYPE_APPLICATION)->handle([
                'user_id' => $request->user()->id,
                'memo' => 'Automatically generated node deployment key.',
                'allowed_ips' => [],
            ], ['r_nodes' => 3]);
        }

        try {
            $secret = $this->encrypter->decrypt($key->token);
        } catch (\Throwable $exception) {
            // Existing deployment keys from older Nodexa/Pterodactyl builds may no
            // longer be decryptable (for example after APP_KEY changes). Remove the
            // stale key and create a fresh one instead of leaving the UI spinning.
            Log::warning('Replacing unreadable node auto-deploy API key.', [
                'key_id' => $key->id,
                'user_id' => $request->user()->id,
                'error' => $exception->getMessage(),
            ]);

            $key->delete();
            $key = $this->keyCreationService->setKeyType(ApiKey::TYPE_APPLICATION)->handle([
                'user_id' => $request->user()->id,
                'memo' => 'Automatically generated node deployment key.',
                'allowed_ips' => [],
            ], ['r_nodes' => 3]);

            $secret = $this->encrypter->decrypt($key->token);
        }

        return new JsonResponse([
            'node' => $node->id,
            'token' => $key->identifier . $secret,
        ]);
    }
}
