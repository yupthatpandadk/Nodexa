<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $next = (int) (DB::table('servers')->max('server_number') ?? 0);
        DB::table('servers')->whereNull('server_number')->orderBy('created_at')->orderBy('id')->select('id')->chunk(100, function ($servers) use (&$next) {
            foreach ($servers as $server) {
                $next++;
                DB::table('servers')->where('id', $server->id)->update([
                    'server_number'=>$next,
                    'identifier'=>'s'.$next,
                ]);
            }
        });
    }

    public function down(): void {}
};
