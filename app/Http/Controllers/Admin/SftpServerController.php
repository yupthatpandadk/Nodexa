<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Pterodactyl\Models\SftpServer;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodexa\SftpFileManagerService;

class SftpServerController extends Controller
{
    public function __construct(private SftpFileManagerService $files)
    {
    }

    public function index(): View
    {
        return view('admin.sftp-servers.index', [
            'servers' => SftpServer::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
            'root_path' => ['required', 'string', 'max:1024'],
        ]);

        $server = SftpServer::create($data + [
            'uuid' => (string) Str::uuid(),
            'enabled' => true,
        ]);

        try {
            $this->files->connect($server);
            return redirect()->route('admin.sftp-servers.files', $server)->with('success', 'SFTP-serveren blev oprettet og forbindelsen virker.');
        } catch (Throwable $e) {
            return redirect()->route('admin.sftp-servers')->with('warning', 'Serveren blev gemt, men forbindelsen fejlede: ' . $e->getMessage());
        }
    }

    public function update(Request $request, SftpServer $sftpServer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:4096'],
            'root_path' => ['required', 'string', 'max:1024'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['enabled'] = $request->boolean('enabled');
        $sftpServer->update($data);

        return back()->with('success', 'SFTP-serveren blev opdateret.');
    }

    public function destroy(SftpServer $sftpServer): RedirectResponse
    {
        $sftpServer->delete();
        return redirect()->route('admin.sftp-servers')->with('success', 'SFTP-serveren blev fjernet fra Nodexa. Ingen filer på Windows-serveren blev slettet.');
    }

    public function test(SftpServer $sftpServer): RedirectResponse
    {
        try {
            $this->files->list($sftpServer);
            return back()->with('success', 'SFTP-forbindelsen virker, og rodmappen kan læses.');
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function files(Request $request, SftpServer $sftpServer): View
    {
        $path = $this->files->sanitizeRelativePath((string) $request->query('path', ''));
        $edit = $request->query('edit');
        $contents = null;

        if (is_string($edit) && $edit !== '') {
            $edit = $this->files->sanitizeRelativePath($edit);
            $contents = $this->files->read($sftpServer, $edit);
        }

        return view('admin.sftp-servers.files', [
            'server' => $sftpServer,
            'path' => $path,
            'items' => $this->files->list($sftpServer, $path),
            'edit' => $edit,
            'contents' => $contents,
        ]);
    }

    public function save(Request $request, SftpServer $sftpServer): RedirectResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:4096'],
            'contents' => ['present', 'string'],
        ]);
        $this->files->write($sftpServer, $data['path'], $data['contents']);

        return back()->with('success', 'Filen blev gemt.');
    }

    public function createFile(Request $request, SftpServer $sftpServer): RedirectResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:4096']]);
        $this->files->write($sftpServer, $data['path'], '');
        return back()->with('success', 'Filen blev oprettet.');
    }

    public function createFolder(Request $request, SftpServer $sftpServer): RedirectResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:4096']]);
        $this->files->mkdir($sftpServer, $data['path']);
        return back()->with('success', 'Mappen blev oprettet.');
    }

    public function rename(Request $request, SftpServer $sftpServer): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:4096'],
            'to' => ['required', 'string', 'max:4096'],
        ]);
        $this->files->rename($sftpServer, $data['from'], $data['to']);
        return back()->with('success', 'Elementet blev omdøbt/flyttet.');
    }

    public function delete(Request $request, SftpServer $sftpServer): RedirectResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:4096']]);
        $this->files->delete($sftpServer, $data['path']);
        return back()->with('success', 'Elementet blev slettet.');
    }

    public function upload(Request $request, SftpServer $sftpServer): RedirectResponse
    {
        $request->validate([
            'directory' => ['nullable', 'string', 'max:4096'],
            'file' => ['required', 'file', 'max:102400'],
        ]);

        $file = $request->file('file');
        $directory = $this->files->sanitizeRelativePath((string) $request->input('directory', ''));
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $target = ltrim(($directory !== '' ? $directory . '/' : '') . $name, '/');
        $this->files->write($sftpServer, $target, file_get_contents($file->getRealPath()));

        return back()->with('success', 'Filen blev uploadet.');
    }

    public function download(Request $request, SftpServer $sftpServer): Response
    {
        $path = $this->files->sanitizeRelativePath((string) $request->query('path'));
        abort_if($path === '', 404);
        $contents = $this->files->read($sftpServer, $path);

        return response($contents, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes(basename($path)) . '"',
        ]);
    }
}
