<?php
namespace App\Http\Controllers;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class NodeController extends Controller
{
    public function index() { return Node::paginate(50); }
    public function store(Request $r) {
        $d=$r->validate(['name'=>'required','fqdn'=>'required','scheme'=>'required|in:http,https','daemon_port'=>'required|integer','sftp_port'=>'required|integer','memory_mb'=>'required|integer','disk_mb'=>'required|integer','location'=>'nullable|string']);
        $d['token']=Str::random(64);
        $node=Node::create($d);
        return response()->json(['node'=>$node,'token'=>$d['token']],201);
    }
}
