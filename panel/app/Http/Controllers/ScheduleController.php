<?php
namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Server;
use App\Services\ScheduleRunner;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    private function authorizeServer(Request $request, Server $server, string $permission): void
    {
        $user = $request->user();
        if ((bool)$user->is_admin || $server->owner_id === $user->id) return;
        $permissions = $server->subusers()->where('user_id',$user->id)->first()?->permissions ?? [];
        abort_unless(in_array('schedule.*',$permissions,true) || in_array($permission,$permissions,true),403);
    }

    public function index(Request $request, Server $server) {
        $this->authorizeServer($request,$server,'schedule.read');
        return $server->schedules()->with('tasks')->orderBy('name')->get();
    }

    public function store(Request $request, Server $server, ScheduleRunner $runner) {
        $this->authorizeServer($request,$server,'schedule.create');
        $data = $this->validateSchedule($request);
        $schedule = DB::transaction(function() use ($server,$data,$runner) {
            $tasks = $data['tasks']; unset($data['tasks']);
            $schedule = $server->schedules()->create($data);
            foreach ($tasks as $i=>$task) $schedule->tasks()->create($task + ['sequence'=>$i+1]);
            $schedule->update(['next_run_at'=>$runner->nextRun($schedule)]);
            return $schedule;
        });
        return response()->json($schedule->load('tasks'),201);
    }

    public function update(Request $request, Server $server, Schedule $schedule, ScheduleRunner $runner) {
        $this->authorizeServer($request,$server,'schedule.update');
        abort_unless($schedule->server_id === $server->id,404);
        $data = $this->validateSchedule($request);
        DB::transaction(function() use ($schedule,$data,$runner) {
            $tasks=$data['tasks']; unset($data['tasks']); $schedule->update($data); $schedule->tasks()->delete();
            foreach($tasks as $i=>$task) $schedule->tasks()->create($task + ['sequence'=>$i+1]);
            $schedule->update(['next_run_at'=>$runner->nextRun($schedule->fresh())]);
        });
        return $schedule->fresh()->load('tasks');
    }

    public function destroy(Request $request, Server $server, Schedule $schedule) {
        $this->authorizeServer($request,$server,'schedule.delete');
        abort_unless($schedule->server_id === $server->id,404); $schedule->delete(); return response()->noContent();
    }

    public function run(Request $request, Server $server, Schedule $schedule, ScheduleRunner $runner) {
        $this->authorizeServer($request,$server,'schedule.execute');
        abort_unless($schedule->server_id === $server->id,404);
        $runner->run($schedule);
        return response()->json([
            'ok'=>true,
            'queued_at'=>now()->toIso8601String(),
            'next_run_at'=>$schedule->fresh()->next_run_at?->toIso8601String(),
        ],202);
    }

    private function validateSchedule(Request $request): array
    {
        $data=$request->validate([
            'name'=>'required|string|max:120','mode'=>'required|in:hourly,daily,weekly,monthly,advanced','time'=>'nullable|date_format:H:i','weekday'=>'nullable|integer|min:0|max:6','monthday'=>'nullable|integer|min:1|max:31',
            'cron_minute'=>'nullable|string|max:32','cron_hour'=>'nullable|string|max:32','cron_day_of_month'=>'nullable|string|max:32','cron_month'=>'nullable|string|max:32','cron_day_of_week'=>'nullable|string|max:32',
            'timezone'=>'required|string|max:64','enabled'=>'boolean','only_when_online'=>'boolean','tasks'=>'required|array|min:1|max:25','tasks.*.action'=>'required|in:command,power,backup','tasks.*.payload'=>'nullable|string|max:4096','tasks.*.time_offset'=>'integer|min:0|max:86400','tasks.*.continue_on_failure'=>'boolean'
        ]);
        [$h,$m]=array_pad(explode(':',$data['time'] ?? '00:00'),2,'0');
        if($data['mode']!=='advanced') {
            $data['cron_minute']=$m; $data['cron_hour']=$data['mode']==='hourly'?'*':$h; $data['cron_month']='*';
            $data['cron_day_of_month']=$data['mode']==='monthly'?(string)($data['monthday']??1):'*';
            $data['cron_day_of_week']=$data['mode']==='weekly'?(string)($data['weekday']??1):'*';
        } else foreach(['cron_minute','cron_hour','cron_day_of_month','cron_month','cron_day_of_week'] as $key) $data[$key]=$data[$key] ?: '*';
        unset($data['time'],$data['weekday'],$data['monthday']); return $data;
    }
}
