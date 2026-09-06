<?php
namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\DaemonClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServerRuntimeController extends Controller
{
    private function authorizeServer(Request $request, Server $server, string $permission): void
    {
        $user=$request->user();if((bool)$user->is_admin||$server->owner_id===$user->id)return;$entry=$server->subusers()->where('user_id',$user->id)->first();abort_unless(in_array($permission,$entry?->permissions??[],true),403,'You do not have permission to perform this action.');
    }
    public function stats(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'console.read');return $daemon->stats($server->load('node'));}
    public function logs(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'console.read');return response($daemon->logs($server->load('node'),(int)$request->integer('tail',200)),200)->header('Content-Type','text/plain; charset=utf-8');}
    public function streamLogs(Request $request,Server $server,DaemonClient $daemon):StreamedResponse
    {
        $this->authorizeServer($request,$server,'console.read');$server->load('node');$tail=max(0,min(1000,(int)$request->integer('tail',100)));
        return response()->stream(function()use($daemon,$server,$tail){$daemon->streamLogs($server,$tail,function(string $chunk):void{echo $chunk;if(ob_get_level()>0)@ob_flush();flush();});},200,['Content-Type'=>'text/plain; charset=utf-8','Cache-Control'=>'no-cache, no-store, must-revalidate','X-Accel-Buffering'=>'no','Connection'=>'keep-alive']);
    }
    public function files(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.read');return $daemon->files($server->load('node'),$request->string('path','/')->toString());}
    public function readFile(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.read');return response($daemon->readFile($server->load('node'),$request->string('path')->toString()),200)->header('Content-Type','text/plain; charset=utf-8');}
    public function writeFile(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.write');$d=$request->validate(['path'=>'required|string','content'=>'present|string|max:8388608']);return $daemon->writeFile($server->load('node'),$d['path'],$d['content']);}
    public function mkdir(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.write');$d=$request->validate(['path'=>'required|string']);return $daemon->mkdir($server->load('node'),$d['path']);}
    public function renameFile(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.write');$d=$request->validate(['from'=>'required|string','to'=>'required|string']);return $daemon->renameFile($server->load('node'),$d['from'],$d['to']);}
    public function upload(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.write');$request->validate(['path'=>'nullable|string','file'=>'required|file|max:524288']);return $daemon->uploadFile($server->load('node'),$request->string('path','/')->toString(),$request->file('file'));}
    public function download(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.read');$path=$request->string('path')->toString();$body=$daemon->downloadFile($server->load('node'),$path);$name=basename(str_replace('\\','/',$path))?:'download';return response($body,200)->header('Content-Type','application/octet-stream')->header('Content-Disposition',HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,$name));}
    public function archive(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.write');$d=$request->validate(['path'=>'required|string']);return $daemon->archive($server->load('node'),$d['path']);}
    public function extract(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.write');$d=$request->validate(['path'=>'required|string']);return $daemon->extract($server->load('node'),$d['path']);}
    public function deleteFile(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'files.write');$d=$request->validate(['path'=>'required|string']);return $daemon->deleteFile($server->load('node'),$d['path']);}
    public function backups(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'backups.read');return $daemon->backups($server->load('node'));}
    public function backup(Request $request,Server $server,DaemonClient $daemon){$this->authorizeServer($request,$server,'backups.create');$d=$request->validate(['name'=>'nullable|string|max:100']);return $daemon->backup($server->load('node'),$d['name']??'backup');}
    public function downloadBackup(Request $request,Server $server,string $name,DaemonClient $daemon){$this->authorizeServer($request,$server,'backups.download');$body=$daemon->downloadBackup($server->load('node'),$name);return response($body,200)->header('Content-Type','application/gzip')->header('Content-Disposition',HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT,basename($name)));}
    public function restoreBackup(Request $request,Server $server,string $name,DaemonClient $daemon){$this->authorizeServer($request,$server,'backups.restore');return $daemon->restoreBackup($server->load('node'),$name);}
    public function deleteBackup(Request $request,Server $server,string $name,DaemonClient $daemon){$this->authorizeServer($request,$server,'backups.delete');return $daemon->deleteBackup($server->load('node'),$name);}
}
