<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('storefront_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('primary_domain', 255)->unique();
            $table->json('aliases')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('logo_url')->nullable();
            $table->string('primary_color', 20)->default('#745cff');
            $table->string('accent_color', 20)->default('#9a6dff');
            $table->string('currency', 3)->default('DKK');
            $table->string('locale', 12)->default('da-DK');
            $table->string('title')->nullable();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('support_email')->nullable();
            $table->string('panel_url')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('storefront_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_site_id')->constrained('storefront_sites')->cascadeOnDelete();
            $table->string('slug', 120);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->string('billing_period', 30)->default('monthly');
            $table->string('type', 40)->default('game');
            $table->json('features')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['storefront_site_id', 'slug']);
            $table->index(['storefront_site_id', 'enabled', 'sort_order']);
        });

        $panelHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: '');
        $domain = trim((string) env('NODEXA_STOREFRONT_DOMAIN', ''));
        if ($domain === '' && str_starts_with($panelHost, 'panel.')) {
            $domain = substr($panelHost, 6);
        }

        if ($domain !== '' && $domain !== $panelHost) {
            $name = (string) (config('app.name') ?: 'Nodexa Hosting');
            $siteId = DB::table('storefront_sites')->insertGetId([
                'name' => $name,
                'slug' => 'default',
                'primary_domain' => strtolower($domain),
                'aliases' => json_encode(['www.'.strtolower($domain)]),
                'enabled' => true,
                'is_default' => true,
                'currency' => 'DKK',
                'locale' => 'da-DK',
                'title' => $name.' · Game Server Hosting',
                'tagline' => 'Hurtig hosting. Ét samlet kontrolpanel.',
                'description' => 'Game server hosting med hurtig provisionering, backups og Nodexa kontrolpanel.',
                'panel_url' => (string) config('app.url'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $products = [
                ['fivem', 'FiveM Performance', 'Optimeret FiveM hosting med NVMe og hurtig CPU.', 12900, 'game', ['4 vCPU high-frequency','8 GB RAM','50 GB NVMe','DDoS-beskyttelse','Nodexa Game Panel'], 10],
                ['minecraft', 'Minecraft Pro', 'Minecraft hosting til vanilla, plugins og modpacks.', 7900, 'game', ['3 vCPU','6 GB RAM','35 GB NVMe','Automatiske backups','Nodexa Game Panel'], 20],
                ['vps', 'Cloud VPS', 'Fleksibel Linux VPS til bots, websites og services.', 9900, 'vps', ['4 vCPU','8 GB RAM','80 GB NVMe','1 Gbit netværk','Root adgang'], 30],
            ];
            foreach ($products as [$slug, $productName, $description, $price, $type, $features, $sort]) {
                DB::table('storefront_products')->insert([
                    'storefront_site_id' => $siteId,
                    'slug' => $slug,
                    'name' => $productName,
                    'description' => $description,
                    'price_cents' => $price,
                    'billing_period' => 'monthly',
                    'type' => $type,
                    'features' => json_encode($features),
                    'enabled' => true,
                    'sort_order' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_products');
        Schema::dropIfExists('storefront_sites');
    }
};
