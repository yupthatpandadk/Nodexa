<?php

namespace Pterodactyl\Models;

use Pterodactyl\Contracts\Models\Identifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pterodactyl\Models\Traits\HasRealtimeIdentifier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $uuid
 * @property int $nest_id
 * @property string $author
 * @property string $name
 * @property string|null $description
 * @property string|null $icon_path
 * @property array|null $features
 * @property string $docker_image -- deprecated, use $docker_images
 * @property array<string, string> $docker_images
 * @property string $update_url
 * @property bool $force_outgoing_ip
 * @property array|null $file_denylist
 * @property string|null $config_files
 * @property string|null $config_startup
 * @property string|null $config_logs
 * @property string|null $config_stop
 * @property int|null $config_from
 * @property string|null $startup
 * @property bool $script_is_privileged
 * @property string|null $script_install
 * @property string $script_entry
 * @property string $script_container
 * @property int|null $copy_script_from
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string|null $copy_script_install
 * @property string $copy_script_entry
 * @property string $copy_script_container
 * @property string|null $inherit_config_files
 * @property string|null $inherit_config_startup
 * @property string|null $inherit_config_logs
 * @property string|null $inherit_config_stop
 * @property string $inherit_file_denylist
 * @property array|null $inherit_features
 * @property Nest $nest
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Server[] $servers
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\EggVariable[] $variables
 * @property Egg|null $scriptFrom
 * @property Egg|null $configFrom
 */
#[Attributes\Identifiable('eegg')]
class Egg extends Model implements Identifiable
{
    /** @use HasFactory<\Database\Factories\EggFactory> */
    use HasFactory;
    use HasRealtimeIdentifier;

    public const RESOURCE_NAME = 'egg';
    public const EXPORT_VERSION = 'PTDL_v2';

    public const FEATURE_EULA_POPUP = 'eula';
    public const FEATURE_FASTDL = 'fastdl';

    protected $table = 'eggs';

    protected $fillable = [
        'name',
        'description',
        'icon_path',
        'features',
        'docker_images',
        'force_outgoing_ip',
        'file_denylist',
        'config_files',
        'config_startup',
        'config_logs',
        'config_stop',
        'config_from',
        'startup',
        'script_is_privileged',
        'script_install',
        'script_entry',
        'script_container',
        'copy_script_from',
    ];

    protected $casts = [
        'nest_id' => 'integer',
        'config_from' => 'integer',
        'script_is_privileged' => 'boolean',
        'force_outgoing_ip' => 'boolean',
        'copy_script_from' => 'integer',
        'features' => 'array',
        'docker_images' => 'array',
        'file_denylist' => 'array',
    ];

    public static array $validationRules = [
        'nest_id' => 'required|bail|numeric|exists:nests,id',
        'uuid' => 'required|string|size:36',
        'name' => 'required|string|max:191',
        'description' => 'string|nullable',
        'icon_path' => 'string|nullable|max:191',
        'features' => 'array|nullable',
        'author' => 'required|string|email',
        'file_denylist' => 'array|nullable',
        'file_denylist.*' => 'string',
        'docker_images' => 'required|array|min:1',
        'docker_images.*' => ['required', 'string', 'max:191', 'regex:/^[\w#\.\/\- ]*\|?~?[\w\.\/\-:@ ]*$/'],
        'startup' => 'required|nullable|string',
        'config_from' => 'sometimes|bail|nullable|numeric|exists:eggs,id',
        'config_stop' => 'required_without:config_from|nullable|string|max:191',
        'config_startup' => 'required_without:config_from|nullable|json',
        'config_logs' => 'required_without:config_from|nullable|json',
        'config_files' => 'required_without:config_from|nullable|json',
        'update_url' => 'sometimes|nullable|string',
        'force_outgoing_ip' => 'sometimes|boolean',
    ];

    protected $attributes = [
        'icon_path' => null,
        'features' => null,
        'file_denylist' => null,
        'config_stop' => null,
        'config_startup' => null,
        'config_logs' => null,
        'config_files' => null,
        'update_url' => null,
    ];

    public function getCopyScriptInstallAttribute(): ?string
    {
        if (!is_null($this->script_install) || is_null($this->copy_script_from)) {
            return $this->script_install;
        }

        return $this->scriptFrom->script_install;
    }

    public function getCopyScriptEntryAttribute(): string
    {
        if (is_null($this->copy_script_from)) {
            return $this->script_entry;
        }

        return $this->scriptFrom->script_entry;
    }

    public function getCopyScriptContainerAttribute(): string
    {
        if (is_null($this->copy_script_from)) {
            return $this->script_container;
        }

        return $this->scriptFrom->script_container;
    }

    public function getInheritConfigFilesAttribute(): ?string
    {
        if (!is_null($this->config_files) || is_null($this->config_from)) {
            return $this->config_files;
        }

        return $this->configFrom->config_files;
    }

    public function getInheritConfigStartupAttribute(): ?string
    {
        if (!is_null($this->config_startup) || is_null($this->config_from)) {
            return $this->config_startup;
        }

        return $this->configFrom->config_startup;
    }

    public function getInheritConfigLogsAttribute(): ?string
    {
        if (!is_null($this->config_logs) || is_null($this->config_from)) {
            return $this->config_logs;
        }

        return $this->configFrom->config_logs;
    }

    public function getInheritConfigStopAttribute(): ?string
    {
        if (!is_null($this->config_stop) || is_null($this->config_from)) {
            return $this->config_stop;
        }

        return $this->configFrom->config_stop;
    }

    public function getInheritFeaturesAttribute(): ?array
    {
        if (!is_null($this->features) || is_null($this->config_from)) {
            return $this->features;
        }

        return $this->configFrom->features;
    }

    public function getInheritFileDenylistAttribute(): ?array
    {
        if (is_null($this->config_from)) {
            return $this->file_denylist;
        }

        return $this->configFrom->file_denylist;
    }

    public function nest(): BelongsTo
    {
        return $this->belongsTo(Nest::class);
    }

    public function servers(): HasMany
    {
        return $this->hasMany(Server::class, 'egg_id');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(EggVariable::class, 'egg_id');
    }

    public function scriptFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copy_script_from');
    }

    public function configFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'config_from');
    }
}
