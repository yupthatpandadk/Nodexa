<?php
namespace Pterodactyl\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Repositories\Wings\DaemonConfigurationRepository;
class ErrorCenterController extends Controller {
 public function __construct(private DaemonConfigurationRepository $wings) {}
 public function index(){
  $checks=$this->checks();
  $logs=$this->recentErrors();
  return view('admin.errors.index',compact('checks','logs'));
 }
 private function checks(): array {
  $out=[];
  try{DB::select('select 1');$out[]=$this->ok('database','Database','Databaseforbindelsen svarer normalt.');}catch(\Throwable $e){$out[]=$this->bad('database','Database',$e->getMessage(),'Kontrollér DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME og at MariaDB kører.');}
  $writable=is_writable(storage_path())&&is_writable(base_path('bootstrap/cache'));
  $out[]=$writable?$this->ok('permissions','Storage & cache','Laravel kan skrive til storage og bootstrap/cache.'):$this->bad('permissions','Storage & cache','En eller flere Laravel-mapper er ikke skrivbare.','Ret ejerskab/rettigheder på storage og bootstrap/cache.');
  foreach(Node::query()->get() as $node){try{$info=$this->wings->setNode($node)->getSystemInformation();$out[]=$this->ok('node-'.$node->id,'Node: '.$node->name,'Wings '.($info['version']??''). ' svarer.');}catch(\Throwable $e){$out[]=$this->bad('node-'.$node->id,'Node: '.$node->name,$e->getMessage(),'Kontrollér Wings service, node FQDN, SSL, port '.$node->daemonListen.' og firewall.');}}
  return $out;
 }
 private function ok($id,$name,$detail){return compact('id','name','detail')+['status'=>'ok','fix'=>null];}
 private function bad($id,$name,$detail,$fix){return compact('id','name','detail','fix')+['status'=>'error'];}
 private function recentErrors(): array {
  $file=storage_path('logs/laravel.log');if(!is_file($file))return [];
  $size=filesize($file);$h=fopen($file,'rb');if($size>200000)fseek($h,-200000,SEEK_END);$raw=stream_get_contents($h);fclose($h);
  preg_match_all('/\[(.*?)\] .*?\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*?)(?=\n\[|\z)/s',$raw,$m,PREG_SET_ORDER);
  return array_slice(array_reverse(array_map(function($x){$message=trim(preg_split('/\n/',$x[3])[0]);return ['time'=>$x[1],'level'=>$x[2],'message'=>$message,'reason'=>$this->reason($message),'fix'=>$this->fix($message)];},$m)) ,0,30);
 }
 private function reason(string $m): string {
  $s=strtolower($m);
  if(str_contains($s,'connection refused'))return 'En service afviser forbindelsen eller lytter ikke på den forventede port.';
  if(str_contains($s,'foreign key constraint'))return 'Databasens foreign-key typer eller relationer matcher ikke.';
  if(str_contains($s,'permission denied'))return 'Processen mangler fil- eller mappeadgang.';
  if(str_contains($s,'csrf')||str_contains($s,'token mismatch'))return 'Session/CSRF-token matcher ikke requesten.';
  if(str_contains($s,'could not resolve host')||str_contains($s,'getaddrinfo'))return 'DNS-navnet kan ikke opløses fra panelet.';
  if(str_contains($s,'certificate')||str_contains($s,'ssl'))return 'TLS/SSL-validering eller certifikat fejlede.';
  if(str_contains($s,'no space left'))return 'Disken eller den relevante partition er fuld.';
  if(str_contains($s,'out of memory')||str_contains($s,'allowed memory'))return 'Processen løb tør for tilgængelig hukommelse.';
  return 'Laravel registrerede en exception. Åbn detaljen/loggen for den præcise fejltekst.';
 }
 private function fix(string $m): string {
  $s=strtolower($m);
  if(str_contains($s,'connection refused'))return 'Kontrollér service-status, host, port og firewall.';
  if(str_contains($s,'foreign key constraint'))return 'Sammenlign kolonnens type/signering med den referenced primary key før migrationen køres igen.';
  if(str_contains($s,'permission denied'))return 'Kontrollér ejer og skriveadgang på den nævnte sti; undgå brede 777-rettigheder.';
  if(str_contains($s,'csrf')||str_contains($s,'token mismatch'))return 'Ryd Laravel cache/session og kontrollér APP_URL, HTTPS/proxy og session-cookie indstillinger.';
  if(str_contains($s,'could not resolve host')||str_contains($s,'getaddrinfo'))return 'Kontrollér DNS-record og serverens DNS resolver.';
  if(str_contains($s,'certificate')||str_contains($s,'ssl'))return 'Kontrollér certifikat, hostname, chain og udløbsdato.';
  if(str_contains($s,'no space left'))return 'Frigør diskplads og kontrollér inode-forbrug.';
  if(str_contains($s,'out of memory')||str_contains($s,'allowed memory'))return 'Kontrollér RAM/swap og PHP memory_limit; find processen der bruger hukommelsen.';
  return 'Se den fulde Laravel-log og den berørte komponent før der ændres konfiguration.';
 }
 public function repair(Request $r){
  $action=$r->validate(['action'=>'required|in:clear_cache'])['action'];
  if($action==='clear_cache'){
   // A web request must never clear the cache/session store it is currently
   // using. File-based Laravel caches can safely be removed directly.
   $targets=[
    storage_path('framework/views'),
    base_path('bootstrap/cache'),
   ];
   $removed=0;
   foreach($targets as $dir){
    if(!is_dir($dir)) continue;
    foreach(new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS) as $file){
     if(!$file->isFile()) continue;
     $name=$file->getFilename();
     if($name==='.gitignore') continue;
     if($dir===base_path('bootstrap/cache') && !str_ends_with($name,'.php')) continue;
     if(@unlink($file->getPathname())) $removed++;
    }
   }
   Log::info('Nodexa Error Center: safe file caches cleared',['user'=>$r->user()->id,'files'=>$removed]);
   return redirect()->route('admin.errors')->with('success',"Laravel file-cache blev ryddet ({$removed} filer). Sessionen blev bevaret.");
  }
 }
}