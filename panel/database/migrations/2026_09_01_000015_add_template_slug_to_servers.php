<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('template_slug', 64)->default('custom')->after('template_id');
        });

        DB::table('servers')
            ->where(function ($query) {
                $query->where('docker_image', 'like', '%java%')
                    ->orWhere('startup', 'like', '%server.jar%');
            })
            ->update(['template_slug' => 'minecraft-java']);

        DB::table('servers')
            ->where('template_slug', 'custom')
            ->where(function ($query) {
                $query->where('startup', 'like', '%run.sh%')
                    ->orWhere('startup', 'like', '%FXServer%');
            })
            ->update(['template_slug' => 'fivem']);
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('template_slug');
        });
    }
};
