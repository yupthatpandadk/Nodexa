<?php

namespace Pterodactyl\Services\Nodexa;

use RuntimeException;
use phpseclib3\Net\SFTP;
use Pterodactyl\Models\SftpServer;

class SftpFileManagerService
{
    public function connect(SftpServer $server): SFTP
    {
        if (!$server->enabled) {
            throw new RuntimeException('Denne SFTP-server er deaktiveret.');
        }

        $sftp = new SFTP($server->host, $server->port, 12);
        if (!$sftp->login($server->username, $server->password)) {
            throw new RuntimeException('Kunne ikke logge ind på SFTP-serveren. Kontroller host, port, brugernavn og adgangskode.');
        }

        return $sftp;
    }

    public function remotePath(SftpServer $server, ?string $path = null): string
    {
        $relative = $this->sanitizeRelativePath($path ?? '');
        $root = rtrim(str_replace('\\', '/', trim($server->root_path)), '/');
        if ($root === '') {
            $root = '/';
        }

        if ($relative === '') {
            return $root;
        }

        return ($root === '/' ? '' : $root) . '/' . $relative;
    }

    public function sanitizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if (str_contains($path, "\0")) {
            throw new RuntimeException('Ugyldig filsti.');
        }

        $safe = [];
        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new RuntimeException('Det er ikke tilladt at gå uden for den valgte rodmappe.');
            }
            $safe[] = $segment;
        }

        return implode('/', $safe);
    }

    public function list(SftpServer $server, string $path = ''): array
    {
        $sftp = $this->connect($server);
        $items = $sftp->rawlist($this->remotePath($server, $path));
        if ($items === false) {
            throw new RuntimeException('Kunne ikke læse mappen via SFTP.');
        }

        unset($items['.'], $items['..']);
        $result = [];
        foreach ($items as $name => $meta) {
            $result[] = [
                'name' => $name,
                'type' => (($meta['type'] ?? 0) === 2) ? 'directory' : 'file',
                'size' => (int) ($meta['size'] ?? 0),
                'mtime' => (int) ($meta['mtime'] ?? 0),
            ];
        }

        usort($result, fn (array $a, array $b) => [$a['type'] !== 'directory', strtolower($a['name'])] <=> [$b['type'] !== 'directory', strtolower($b['name'])]);

        return $result;
    }

    public function read(SftpServer $server, string $path): string
    {
        $data = $this->connect($server)->get($this->remotePath($server, $path));
        if ($data === false) {
            throw new RuntimeException('Kunne ikke læse filen.');
        }

        return $data;
    }

    public function write(SftpServer $server, string $path, string $contents): void
    {
        if (!$this->connect($server)->put($this->remotePath($server, $path), $contents)) {
            throw new RuntimeException('Kunne ikke gemme filen.');
        }
    }

    public function mkdir(SftpServer $server, string $path): void
    {
        if (!$this->connect($server)->mkdir($this->remotePath($server, $path), -1, true)) {
            throw new RuntimeException('Kunne ikke oprette mappen.');
        }
    }

    public function rename(SftpServer $server, string $from, string $to): void
    {
        $sftp = $this->connect($server);
        if (!$sftp->rename($this->remotePath($server, $from), $this->remotePath($server, $to))) {
            throw new RuntimeException('Kunne ikke omdøbe eller flytte elementet.');
        }
    }

    public function delete(SftpServer $server, string $path): void
    {
        $sftp = $this->connect($server);
        if (!$sftp->delete($this->remotePath($server, $path), true)) {
            throw new RuntimeException('Kunne ikke slette elementet.');
        }
    }
}
