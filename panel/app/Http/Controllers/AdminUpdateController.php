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
    private const UPDATE_TRIGGER='/usr/local/sbin/nodexa-update-trigger';

    private function admin(Request $request):void{abort_unless((bool)$request->user()?->is_admin,403,'Administrator permission required.');}
    private function readJson(string $path):array{if(!is_readable($path))return[];$decoded=json_decode((string)file_get_contents($path),true);return is_array($decoded)?$decoded:[];}
    private function executable(array $candidates):?string{foreach($candidates as $candidate){if(is_file($candidate)&&is_executable($candidate))return $candidate;}return null;}

    private function remote():array
    {
        $repo=config('nodexa.update_repository','yupthatpandadk/Nodexa');
        $branch=config('nodexa.update_branch','main');

        $response=Http::withHeaders([
            'Accept'=>'application/vnd.github+json',
            'User-Agent'=>'Nodexa-Panel-Updater',
        ])->timeout(8)->get("https://api.github.com/repos/{$repo}/commits/{$branch}");
        $response->throw();
        $data=$response->json();
        $sha=$data['sha']??null;

        $version=null;
        try{
            $versionResponse=Http::withHeaders(['User-Agent'=>'Nodexa-Panel-Updater'])
                ->timeout(8)
                ->get("https://raw.githubusercontent.com/{$repo}/{$branch}/VERSION");
            if($versionResponse->successful()){
                $candidate=trim($versionResponse->body());
                if($candidate!=='')$version=$candidate;
            }
        }catch(Throwable $ignored){}

        return [
            'version'=>$version,
            'commit'=>$sha,
            'short_commit'=>$sha?substr($sha,0,8):null,
            'message'=>trim((string)($data['commit']['message']??'')),
            'date'=>$data['commit']['committer']['date']??null,
            'repository'=>$repo,
            'branch'=>$branch,
        ];
    }

    private function updateAvailable(array $installed,array $remote):bool
    {
        $installedVersion=trim((string)($installed['version']??''));
        $remoteVersion=trim((string)($remote['version']??''));
        if($installedVersion!==''&&$remoteVersion!==''&&$installedVersion!==$remoteVersion)return true;

        $installedCommit=trim((string)($installed['commit']??''));
        $remoteCommit=trim((string)($remote['commit']??''));
        return $installedCommit!==''&&$remoteCommit!==''&&$installedCommit!==$remoteCommit;
    }

    public function check(Request $request)
    {
        $this->admin($request);
        $installed=$this->readJson(self::VERSION_FILE);
        try{
            $remote=$this->remote();
            $installedCommit=trim((string)($installed['commit']??''));
            $remoteCommit=trim((string)($remote['commit']??''));
            $installedVersion=trim((string)($installed['version']??''));
            $remoteVersion=trim((string)($remote['version']??''));
            $known=($installedCommit!==''&&$remoteCommit!=='')||($installedVersion!==''&&$remoteVersion!=='');

            return [
                'available'=>$this->updateAvailable($installed,$remote),
                'installation_known'=>$known,
                'installed'=>[
                    'version'=>$installedVersion?:'unknown',
                    'commit'=>$installedCommit?:null,
                    'short_commit'=>$installedCommit!==''?substr($installedCommit,0,8):null,
                    'installed_at'=>$installed['installed_at']??null,
                ],
                'latest'=>$remote,
                'state'=>$this->readJson(self::STATE_FILE),
            ];
        }catch(Throwable $e){
            SystemIssue::report(source:'updater',title:'Kunne ikke kontrollere Nodexa-opdateringer',message:$e->getMessage(),severity:'warning',type:'update_check_failed');
            return response()->json(['message'=>'Kunne ikke kontakte GitHub for at kontrollere opdateringer.','installed'=>$installed,'state'=>$this->readJson(self::STATE_FILE)],503);
        }
    }

    public function status(Request $request){$this->admin($request);$state=$this->readJson(self::STATE_FILE);$installed=$this->readJson(self::VERSION_FILE);$log='';if(is_readable(self::LOG_FILE)){$lines=file(self::LOG_FILE,FILE_IGNORE_NEW_LINES)?:[];$log=implode("\n",array_slice($lines,-120));}return['state'=>$state,'installed'=>$installed,'log'=>$log];}

    public function start(Request $request)
    {
        $this->admin($request);
        $state=$this->readJson(self::STATE_FILE);
        abort_if(($state['status']??null)==='running',409,'En Nodexa-opdatering kører allerede.');

        try{
            $remote=$this->remote();
            $installed=$this->readJson(self::VERSION_FILE);
            if(!$this->updateAvailable($installed,$remote))return response()->json(['message'=>'Nodexa er allerede opdateret.','available'=>false],409);
        }catch(Throwable $e){
            return response()->json(['message'=>'GitHub kunne ikke kontaktes: '.$e->getMessage()],503);
        }

        if(!is_file(self::UPDATE_TRIGGER)||!is_executable(self::UPDATE_TRIGGER)){
            $message='Updater-triggeren mangler eller kan ikke køres. Kør Nodexa updater-setup én gang som root for at reparere systemd og sudoers.';
            SystemIssue::report(source:'updater',title:'Nodexa updater-trigger mangler',message:$message,severity:'error',type:'update_trigger_missing');
            return response()->json(['message'=>$message,'repair_required'=>true],500);
        }

        $sudo=$this->executable(['/usr/bin/sudo','/bin/sudo']);
        if($sudo===null){
            $message='sudo blev ikke fundet på Nodexa-serveren. Installer sudo og kør updater-setup igen.';
            SystemIssue::report(source:'updater',title:'sudo mangler til Nodexa updater',message:$message,severity:'error',type:'update_sudo_missing');
            return response()->json(['message'=>$message,'repair_required'=>true],500);
        }

        try{
            $process=new Process([$sudo,'-n',self::UPDATE_TRIGGER]);
            $process->setTimeout(10);
            $process->run();
        }catch(Throwable $e){
            $detail=trim($e->getMessage())?:'Updater-triggeren kunne ikke startes.';
            SystemIssue::report(source:'updater',title:'Nodexa-opdatering kunne ikke startes',message:$detail,severity:'error',type:'update_start_failed');
            return response()->json(['message'=>'Updater-triggeren fejlede: '.$detail,'repair_required'=>true],500);
        }

        if(!$process->isSuccessful()){
            $detail=trim($process->getErrorOutput().' '.$process->getOutput());
            $message='Updater-servicen kunne ikke startes'.($detail!==''?': '.$detail:'. Kør updater-setup igen for at reparere sudoers/systemd.');
            SystemIssue::report(source:'updater',title:'Nodexa-opdatering kunne ikke startes',message:$detail!==''?$detail:$message,severity:'error',type:'update_start_failed');
            return response()->json(['message'=>$message,'repair_required'=>true],500);
        }

        return response()->json(['message'=>'Opdateringen er startet.','status'=>'running'],202);
    }

    public function run(Request $request)
    {
        return $this->start($request);
    }
}
