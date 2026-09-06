<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function ask(string $label, ?string $default = null): string
{
    $suffix = $default !== null && $default !== '' ? " [{$default}]" : '';
    fwrite(STDOUT, $label.$suffix.': ');
    $value = trim((string) fgets(STDIN));
    return $value !== '' ? $value : (string) ($default ?? '');
}

function secret(string $label): string
{
    fwrite(STDOUT, $label.': ');
    if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) {
        shell_exec('stty -echo 2>/dev/null');
        $value = rtrim((string) fgets(STDIN), "\r\n");
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, PHP_EOL);
        return $value;
    }
    return rtrim((string) fgets(STDIN), "\r\n");
}

fwrite(STDOUT, "\n========================================\n");
fwrite(STDOUT, " Nodexa Administrator Account Tool\n");
fwrite(STDOUT, "========================================\n\n");
fwrite(STDOUT, "Create a new administrator or reset an existing one.\n\n");

$email = strtolower(ask('Email'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}

$existing = User::whereRaw('LOWER(email) = ?', [$email])->first();
$username = strtolower(ask('Username', $existing?->username));
$firstName = ask('First name', $existing?->first_name ?: null);
$lastName = ask('Last name', $existing?->last_name ?: null);

if (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
    fwrite(STDERR, "Username must be 3-64 characters using letters, numbers, dot, underscore or dash.\n");
    exit(1);
}
if ($firstName === '' || $lastName === '') {
    fwrite(STDERR, "First and last name are required.\n");
    exit(1);
}

while (true) {
    $password = secret('Password (minimum 12 characters)');
    $confirm = secret('Confirm password');
    if (strlen($password) < 12) {
        fwrite(STDOUT, "Password must be at least 12 characters.\n");
        continue;
    }
    if (!hash_equals($password, $confirm)) {
        fwrite(STDOUT, "Passwords do not match.\n");
        continue;
    }
    break;
}

$duplicateUsername = User::whereRaw('LOWER(username) = ?', [$username])
    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
    ->exists();
if ($duplicateUsername) {
    fwrite(STDERR, "That username is already in use.\n");
    exit(1);
}

$user = User::updateOrCreate(
    ['email' => $email],
    [
        'name' => trim($firstName.' '.$lastName),
        'username' => $username,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'password' => Hash::make($password),
        'is_admin' => true,
    ]
);

$user->tokens()->delete();

fwrite(STDOUT, "\nAdministrator ready.\n");
fwrite(STDOUT, "Email: {$user->email}\n");
fwrite(STDOUT, "Username: {$user->username}\n");
fwrite(STDOUT, "You can now sign in to Nodexa.\n\n");
