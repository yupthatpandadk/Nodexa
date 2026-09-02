<?php
namespace App\Http\Controllers;

use App\Models\SystemIssue;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

class AdminUpdateController extends Controller
{
    private const VERSION_FILE='/var/lib/nodexa/version.json';
    private const STATE_FILE='/var/lib/nodexa/update-state.json';
    private const LOG_FILE='/var/log/nodexa-update.log';
    private function admin(Request $request):void{abort_unless((bool)$request->user()?->is_admin,403,'Administrator permission required.');}
    private function readJson(string $path):array{if(!is_readable($path))return[];$decoded=json_decode((string)file_get_contents($path),true);return is_array($decoded)?$decoded:[];}
    private function remote():array
    {
        $repo=config('nodexa.update_repository','yupthatpandadk/Nodexa');$branch=config('nodexa.update_branch','main');
        $response=Http::withHeaders(['Accept'=>'application/vnd.github+json','User-Agent'=>'Nodexa-Panel-Updater'])->timeout(8)->get("https://api.github.com/repos/{$repo}/commits/{$branch}");
        $response->throw();$data=$response->json();$sha=$data['sha']??null;
        return ['commit'=>$sha,'short_commit'=>$sha?substr($sha,0,8):null,'message'=>trim((string)($data['commit']['message']??'')),'date'=>$data['commit']['committer']['date']??null,'repository'=>$repo,'branch'=>$branch];
    }
    public function check(Request $request)
    {
        $this->admin($request);$installed=$this->readJson(self::VERSION_FILE);
        try{
            $remote=$this->remote();$installedCommit=trim((string)($installed['commit']??''));$remoteCommit=trim((string)($remote['commit']??''));
            $known=$installedCommit!==''&&$remoteCommit!=='';
            return ['available'=>$known&&$installedCommit!==$remoteCommit,'installation_known'=>$known,'installed'=>['version'=>$installed['version']??'unknown','commit'=>$installedCommit?:null,'short_commit'=>$installedCommit!==''?substr($installedCommit,0,8):null,'installed_at'=>$installed['installed_at']??null],'latest'=>$remote,'state'=>$this->readJson(self::STATE_FILE)];
        }catch(Throwable $e){
            SystemIssue::report(source:'updater',title:'Kunne ikke kontrollere Nodexa-opdateringer',message:$e->getMessage(),severity:'warning',type:'update_check_failed');
            return response()->json(['message'=>'Kunne ikke kontakte GitHub for at kontrollere opdateringer.','installed'=>$installed,'state'=>$this->readJson(self::STATE_FILE)],503);
        }
    }
    public function status(Request $request){$this->admin($request);$state=$this->readJson(self::STATE_FILE);$installed=$this->readJson(self::VERSION_FILE);$log='';if(is_readable(self::LOG_FILE)){$lines=file(self::LOG_FILE,FILE_IGNORE_NEW_LINES)?:[];$log=implode("\n",array_slice($lines,-120));}return['state'=>$state,'installed'=>$installed,'log'=>$log];}
    public function start(Request $request)
    {
        $this->admin($request);$state=$this->readJson(self::STATE_FILE);abort_if(($state['status']??null)==='running',409,'En Nodexa-opdatering kører allerede.');
        try{$remote=$this->remote();$installed=$this->readJson(self::VERSION_FILE);if(!empty($installed['commit'])&&$installed['commit']===$remote['commit'])return response()->json(['message'=>'Nodexa er allerede opdateret.','available'=>false],409);}catch(Throwable $e){return response()->json(['message'=>'GitHub kunne ikke kontaktes: '.$e->getMessage()],503);}

        // Keep this invocation byte-for-byte compatible with the sudoers rule
        // installed by deploy/setup-updater.sh. Some distributions resolve
        // systemctl to /usr/bin/systemctl while the old controller hard-coded a
        // fallback path independently, causing sudo to reject the request and
        // the UI to show only "Server Error".
        $systemctl='/usr/bin/systemctl';
        if(!is_executable($systemctl)){$resolved=trim((string)shell_exec('command -v systemctl 2>/dev/null'));if($resolved!=='')$systemctl=$resolved;}
        if(!is_executable($systemctl))return response()->json(['message'=>'systemctl blev ikke fundet på Nodexa-serveren.'],500);

        $process=new Process(['/usr/bin/sudo','-n',$systemctl,'--no-block','start','nodexa-update.service']);$process->setTimeout(10);$process->run();
        if(!$process->isSuccessful()){
            $detail=trim($process->getErrorOutput().' '.$process->getOutput());
            SystemIssue::report(source:'updater',title:'Nodexa-opdatering kunne ikke startes',message:$detail,severity:'error',type:'update_start_failed');
            return response()->json(['message'=>'Updater-servicen kunne ikke startes. '.($detail!==''?$detail:'Kontroller nodexa-update.service og sudoers-reglen.')],500);
        }
        return response()->json(['message'=>'Opdateringen er startet.','status'=>'running'],202);
    }
}
