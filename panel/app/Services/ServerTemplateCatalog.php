<?php
namespace App\Services;

use App\Models\Server;

final class ServerTemplateCatalog
{
    public function for(Server $server): array
    {
        return $this->definition((string) ($server->template_slug ?? 'custom'));
    }

    public function definition(string $slug): array
    {
        $slug = strtolower(trim($slug));

        if (in_array($slug, ['minecraft', 'minecraft-java'], true)) {
            return [
                'slug' => 'minecraft-java',
                'name' => 'Minecraft Java / Paper',
                'startup_template' => 'java -Xms128M -XX:MaxRAMPercentage=95.0 -jar "${SERVER_JARFILE:-server.jar}" nogui',
                'docker_images' => [
                    ['label' => 'Java 25', 'value' => 'ghcr.io/parkervcp/yolks:java_25'],
                    ['label' => 'Java 21', 'value' => 'ghcr.io/parkervcp/yolks:java_21'],
                    ['label' => 'Java 17', 'value' => 'ghcr.io/parkervcp/yolks:java_17'],
                    ['label' => 'Java 11', 'value' => 'ghcr.io/parkervcp/yolks:java_11'],
                    ['label' => 'Java 8', 'value' => 'ghcr.io/parkervcp/yolks:java_8'],
                ],
                'variables' => [
                    [
                        'key' => 'MINECRAFT_VERSION',
                        'name' => 'Minecraft Version',
                        'description' => 'The version of Minecraft/Paper to install. Use an exact version such as 1.21.8.',
                        'default' => '1.21.8',
                        'rules' => ['required', 'string', 'max:32', 'regex:/^\d+\.\d+(?:\.\d+)?$/'],
                        'user_viewable' => true,
                        'user_editable' => true,
                    ],
                    [
                        'key' => 'SERVER_JARFILE',
                        'name' => 'Server Jar File',
                        'description' => 'The name of the server JAR file to install and run.',
                        'default' => 'server.jar',
                        'rules' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._-]+\.jar$/i'],
                        'user_viewable' => true,
                        'user_editable' => true,
                    ],
                    [
                        'key' => 'BUILD_NUMBER',
                        'name' => 'Build Number',
                        'description' => 'Paper build number. Leave as latest to always install the newest build for the selected Minecraft version.',
                        'default' => 'latest',
                        'rules' => ['required', 'string', 'max:20', 'regex:/^(?:latest|\d+)$/i'],
                        'user_viewable' => true,
                        'user_editable' => true,
                    ],
                ],
            ];
        }

        return [
            'slug' => $slug !== '' ? $slug : 'custom',
            'name' => ucfirst($slug !== '' ? $slug : 'custom'),
            'startup_template' => null,
            'docker_images' => [],
            'variables' => [],
        ];
    }

    public function environmentFor(Server $server): array
    {
        $environment = is_array($server->environment) ? $server->environment : [];
        foreach ($this->for($server)['variables'] as $variable) {
            $key = $variable['key'];
            if (!array_key_exists($key, $environment) || $environment[$key] === '') {
                $environment[$key] = $variable['default'];
            }
        }
        return $environment;
    }
}
