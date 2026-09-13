<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\Process\Process;

class UpdateController extends Controller
{
    private const STATE_DIR='/var/lib/nodexa';
    private const VERSION_FILE=self::STATE_DIR.'/version.json';
    private const STATE_FILE=self::STATE_DIR.'/update-state.json';
    private const REPO='yupthatpandadk/Nodexa';
    private const BRANCH='main';

    public function index(): View
    {
        $installed=$this->installedVersion();
        $latest=$this->latestVersion();
        return view('admin.updates.index',[
            'installed'=>$installed,'latest'=>$latest,'state'=>$this->updateState(),
            'changelog'=>$this->changelog(),'updateAvailable'=>$this->updateAvailable($installed,$latest),
        ]);
    }

    public function status(): JsonResponse
    {
        $installed=$this->installedVersion(); $latest=$this->latestVersion();
        return response()->json(['state'=>$this->updateState(),'installed'=>$installed,'latest'=>$latest,'update_available'=>$this->updateAvailable($installed,$latest)]);
    }

    public function run(): RedirectResponse
    {
        if(($this->updateState()['status']??'idle')==='running') return redirect()->route('admin.updates')->with('update_message','En Nodexa-opdatering kører allerede.');
        $installed=$this->installedVersion(); $latest=$this->latestVersion();
        if(!$this->updateAvailable($installed,$latest)) return redirect()->route('admin.updates')->with('update_message','Nodexa er allerede opdateret til den nyeste GitHub-version.');
        $trigger='/usr/local/sbin/nodexa-update-trigger';
        if(!is_executable($trigger)) return redirect()->route('admin.updates')->with('update_error','Nodexa update-triggeren er ikke installeret på denne server. Kør setup-updater.sh som root.');
        $p=new Process(['/usr/bin/sudo',$trigger]); $p->setTimeout(15); $p->run();
        if(!$p->isSuccessful()){ $e=trim($p->getErrorOutput()?:$p->getOutput()); return redirect()->route('admin.updates')->with('update_error','Kunne ikke starte updateren.'.($e!==''?' '.$e:'')); }
        return redirect()->route('admin.updates')->with('update_message','Nodexa-opdateringen er startet. Siden følger status automatisk.');
    }

    private function installedVersion(): array
    {
        $d=$this->readJson(self::VERSION_FILE); $version=(string)($d['version']??'unknown');
        if($version==='unknown'||$version==='') foreach([base_path('../VERSION'),base_path('VERSION'),dirname(base_path()).'/VERSION'] as $p) if(is_readable($p)){ $v=ltrim(trim((string)@file_get_contents($p)),'vV'); if($this->validVersion($v)){ $version=$v; break; } }
        return ['version'=>$version,'commit'=>$d['commit']??null,'repository'=>(string)($d['repository']??self::REPO),'branch'=>(string)($d['branch']??self::BRANCH),'installed_at'=>$d['installed_at']??null];
    }

    /** Always resolve the current main head and read VERSION from that exact SHA. No Laravel cache is used. */
    private function latestVersion(): array
    {
        $commit=null; $version=null; $meta=[]; $errors=[]; $nonce=bin2hex(random_bytes(8));
        try {
            $r=$this->github("https://api.github.com/repos/".self::REPO."/commits/".self::BRANCH,['cb'=>$nonce]);
            if($r->successful()){
                $c=$r->json(); $commit=$c['sha']??null;
                $meta=['message'=>trim((string)data_get($c,'commit.message','')),'author'=>data_get($c,'commit.author.name'),'date'=>data_get($c,'commit.author.date'),'url'=>$c['html_url']??null];
            } else $errors[]='GitHub commit HTTP '.$r->status();
        } catch(\Throwable $e){ $errors[]='GitHub commit: '.$e->getMessage(); }

        $ref=($commit&&preg_match('/^[0-9a-f]{40}$/i',$commit))?$commit:self::BRANCH;
        try {
            $r=$this->github("https://api.github.com/repos/".self::REPO."/contents/VERSION",['ref'=>$ref,'cb'=>$nonce]);
            if($r->successful()){
                $j=$r->json(); $raw=base64_decode(preg_replace('/\s+/','',(string)($j['content']??'')),true); $v=ltrim(trim((string)$raw),'vV');
                if($this->validVersion($v)) $version=$v; else $errors[]='VERSION indhold er ugyldigt';
            } else $errors[]='VERSION API HTTP '.$r->status();
        } catch(\Throwable $e){ $errors[]='VERSION API: '.$e->getMessage(); }

        return array_merge(['version'=>$version,'commit'=>$commit,'error'=>$errors?implode(' · ',$errors):null],$meta);
    }

    /** CHANGELOG is remote-first. This means a newly pushed GitHub release is visible before Nodexa itself is updated. */
    private function changelog(): array
    {
        $entries=[]; $head=null; $nonce=bin2hex(random_bytes(8));
        try {
            $h=$this->github("https://api.github.com/repos/".self::REPO."/commits/".self::BRANCH,['cb'=>$nonce]);
            if($h->successful()) $head=$h->json('sha');
        } catch(\Throwable $e) {}
        $ref=($head&&preg_match('/^[0-9a-f]{40}$/i',$head))?$head:self::BRANCH;

        try {
            $r=$this->github("https://api.github.com/repos/".self::REPO."/contents/CHANGELOG.json",['ref'=>$ref,'cb'=>$nonce]);
            if($r->successful()){
                $j=$r->json(); $raw=base64_decode(preg_replace('/\s+/','',(string)($j['content']??'')),true); $remote=json_decode((string)$raw,true);
                if(is_array($remote)) foreach($remote as $e) $entries[]=$this->mapEntry($e);
            }
        } catch(\Throwable $e) {}

        // Local changelog is fallback/history only; GitHub entries win for identical versions/titles.
        foreach([base_path('../CHANGELOG.json'),base_path('CHANGELOG.json'),dirname(base_path()).'/CHANGELOG.json'] as $path){
            if(!is_readable($path)) continue; $local=json_decode((string)@file_get_contents($path),true); if(!is_array($local)) continue;
            foreach($local as $e){ $m=$this->mapEntry($e); $key=$m['version'].'|'.$m['title']; if(!collect($entries)->contains(fn($x)=>($x['version'].'|'.$x['title'])===$key)) $entries[]=$m; }
            break;
        }

        // GitHub commits provide release history if a changelog entry was accidentally omitted.
        $known=collect($entries)->pluck('version')->filter()->unique()->flip()->all();
        try {
            for($page=1;$page<=10;$page++){
                $r=$this->github("https://api.github.com/repos/".self::REPO.'/commits',['sha'=>self::BRANCH,'per_page'=>100,'page'=>$page,'cb'=>$nonce]);
                if(!$r->successful()) break; $commits=$r->json(); if(!is_array($commits)||!count($commits)) break;
                foreach($commits as $c){
                    $msg=trim((string)data_get($c,'commit.message','')); $first=trim(strtok($msg,"\n")?:$msg);
                    if(!preg_match('/(?:release|bump)\s*(?:nodexa\s*)?(?:to\s*)?v?(\d+\.\d+(?:\.\d+){1,2})/i',$first,$m)) continue;
                    $v=$m[1]; if(isset($known[$v])) continue;
                    $entries[]=['version'=>$v,'sha'=>(string)($c['sha']??''),'title'=>$first,'body'=>'Release fundet direkte i GitHub commit-historikken.','author'=>(string)data_get($c,'commit.author.name','Nodexa'),'date'=>data_get($c,'commit.author.date'),'url'=>$c['html_url']??null,'type'=>'changed']; $known[$v]=true;
                }
                if(count($commits)<100) break;
            }
        } catch(\Throwable $e) {}

        usort($entries,fn($a,$b)=>version_compare(ltrim((string)$b['version'],'vV'),ltrim((string)$a['version'],'vV')) ?: strcmp((string)($b['date']??''),(string)($a['date']??'')));
        return $entries;
    }

    private function github(string $url,array $query=[])
    {
        return Http::acceptJson()->withUserAgent('Nodexa-Panel-Updater/4')->withHeaders([
            'Cache-Control'=>'no-cache, no-store, must-revalidate, max-age=0','Pragma'=>'no-cache','Expires'=>'0','X-GitHub-Api-Version'=>'2022-11-28'
        ])->timeout(12)->retry(2,250)->get($url,$query);
    }

    private function mapEntry(array $e): array { return ['version'=>(string)($e['version']??''),'sha'=>$e['sha']??'','title'=>(string)($e['title']??'Nodexa update'),'body'=>(string)($e['description']??$e['body']??''),'author'=>$e['author']??'Nodexa','date'=>$e['date']??null,'url'=>$e['url']??null,'type'=>$e['type']??null]; }
    private function validVersion(string $v): bool { return (bool)preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/',$v); }
    private function updateAvailable(array $i,array $l): bool { $iv=ltrim(trim((string)($i['version']??'')),'vV'); $lv=ltrim(trim((string)($l['version']??'')),'vV'); if($iv!==''&&$lv!==''&&$iv!=='unknown'){ if(version_compare($lv,$iv,'>'))return true; if(version_compare($lv,$iv,'<'))return false; if(!empty($i['commit'])&&!empty($l['commit']))return strtolower((string)$i['commit'])!==strtolower((string)$l['commit']); return false;} return !empty($i['commit'])&&!empty($l['commit'])&&strtolower((string)$i['commit'])!==strtolower((string)$l['commit']); }
    private function updateState(): array { $s=$this->readJson(self::STATE_FILE); return ['status'=>(string)($s['status']??'idle'),'message'=>(string)($s['message']??'Ingen opdatering kører.'),'progress'=>max(0,min(100,(int)($s['progress']??0))),'step'=>(string)($s['step']??''),'updated_at'=>$s['updated_at']??null]; }
    private function readJson(string $p): array { if(!is_readable($p))return []; $d=json_decode((string)@file_get_contents($p),true); return is_array($d)?$d:[]; }
}
