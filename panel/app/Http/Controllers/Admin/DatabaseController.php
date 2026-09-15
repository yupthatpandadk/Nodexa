<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Models\DatabaseHost;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Databases\Hosts\HostUpdateService;
use Pterodactyl\Http\Requests\Admin\DatabaseHostFormRequest;
use Pterodactyl\Services\Databases\Hosts\HostCreationService;
use Pterodactyl\Services\Databases\Hosts\HostDeletionService;
use Pterodactyl\Contracts\Repository\DatabaseRepositoryInterface;
use Pterodactyl\Contracts\Repository\LocationRepositoryInterface;
use Pterodactyl\Contracts\Repository\DatabaseHostRepositoryInterface;

class DatabaseController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private DatabaseHostRepositoryInterface $repository,
        private DatabaseRepositoryInterface $databaseRepository,
        private HostCreationService $creationService,
        private HostDeletionService $deletionService,
        private HostUpdateService $updateService,
        private LocationRepositoryInterface $locationRepository,
    ) {}

    public function index(): View
    {
        return view('admin.databases.index', ['locations' => $this->locationRepository->getAllWithNodes(), 'hosts' => $this->repository->getWithViewDetails()]);
    }

    public function view(int $host): View
    {
        return view('admin.databases.view', ['locations' => $this->locationRepository->getAllWithNodes(), 'host' => $this->repository->find($host), 'databases' => $this->databaseRepository->getDatabasesForHost($host)]);
    }

    public function create(DatabaseHostFormRequest $request): RedirectResponse
    {
        try {
            $host = $this->creationService->handle($request->normalize());
        } catch (\Exception $exception) {
            if ($this->isDatabaseConnectionException($exception)) {
                $data = $request->validated();
                $this->alert->danger($this->friendlyConnectionError($exception, (string) ($data['host'] ?? ''), (int) ($data['port'] ?? 3306)))->flash();
                return redirect()->route('admin.databases')->withInput($data);
            }
            throw $exception;
        }
        $this->alert->success('Database-hosten blev oprettet og forbindelsen blev bekræftet.')->flash();
        return redirect()->route('admin.databases.view', $host->id);
    }

    public function update(DatabaseHostFormRequest $request, DatabaseHost $host): RedirectResponse
    {
        $redirect = redirect()->route('admin.databases.view', $host->id);
        try {
            $data = $request->normalize();
            $this->updateService->handle($host->id, $data);
            $this->alert->success('Database-hosten blev opdateret.')->flash();
        } catch (\Exception $exception) {
            if ($this->isDatabaseConnectionException($exception)) {
                $data = $request->normalize();
                $this->alert->danger($this->friendlyConnectionError($exception, (string) ($data['host'] ?? $host->host), (int) ($data['port'] ?? $host->port)))->flash();
                return $redirect->withInput($data);
            }
            throw $exception;
        }
        return $redirect;
    }

    public function delete(int $host): RedirectResponse
    {
        $this->deletionService->handle($host);
        $this->alert->success('Database-hosten blev slettet.')->flash();
        return redirect()->route('admin.databases');
    }

    private function isDatabaseConnectionException(\Throwable $exception): bool
    {
        return $exception instanceof \PDOException || $exception->getPrevious() instanceof \PDOException;
    }

    private function friendlyConnectionError(\Throwable $exception, string $host, int $port): string
    {
        $message = strtolower($exception->getMessage());
        $target = trim($host) !== '' ? sprintf('%s:%d', $host, $port ?: 3306) : 'den valgte database-host';
        if (str_contains($message, 'connection refused') || str_contains($message, '[2002]')) {
            return sprintf('Kunne ikke forbinde til %s. Forbindelsen blev afvist. Kontrollér at MySQL/MariaDB kører, lytter på den eksterne adresse/port, og at firewall tillader trafik fra Nodexa-panelet. Ingen database-host blev oprettet eller ændret.', $target);
        }
        if (str_contains($message, 'access denied') || str_contains($message, '[1045]')) {
            return sprintf('Forbindelsen til %s blev oprettet, men MySQL afviste login. Kontrollér brugernavn, adgangskode, tilladte hosts og at databasebrugeren har WITH GRANT OPTION.', $target);
        }
        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return sprintf('Forbindelsen til %s fik timeout. Kontrollér firewall, routing, IP-adresse og at MySQL/MariaDB accepterer eksterne forbindelser.', $target);
        }
        if (str_contains($message, 'name or service not known') || str_contains($message, 'getaddrinfo') || str_contains($message, 'unknown host')) {
            return sprintf('Database-hostnavnet for %s kunne ikke slås op. Kontrollér DNS/FQDN eller brug den korrekte IP-adresse.', $target);
        }
        return sprintf('Nodexa kunne ikke validere forbindelsen til %s. Kontrollér host, port, databasebruger, firewall og MySQL/MariaDB-konfigurationen. Den tekniske SQL-fejl skjules her af sikkerhedshensyn; se Laravel-loggen for detaljer.', $target);
    }
}
