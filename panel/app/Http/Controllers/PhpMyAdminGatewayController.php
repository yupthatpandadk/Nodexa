<?php
namespace App\Http\Controllers;

use App\Models\ServerDatabase;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

class PhpMyAdminGatewayController extends Controller
{
    public function __invoke(string $token)
    {
        $payload = Cache::pull('nodexa:pma:'.$token);
        abort_unless(is_array($payload), 410, 'This phpMyAdmin link has expired or has already been used.');
        $database = ServerDatabase::findOrFail($payload['database_id']);
        abort_unless($database->server_id === $payload['server_id'], 403);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name(config('nodexa.phpmyadmin_signon_session', 'NodexaSignon'));
        session_start();
        $_SESSION['PMA_single_signon_user'] = $database->username;
        $_SESSION['PMA_single_signon_password'] = $database->plainPassword();
        $_SESSION['PMA_single_signon_host'] = $database->host;
        $_SESSION['PMA_single_signon_port'] = (string)$database->port;
        $_SESSION['PMA_single_signon_database'] = $database->name;
        session_write_close();

        return redirect(config('nodexa.phpmyadmin_url', '/phpmyadmin/'));
    }
}
