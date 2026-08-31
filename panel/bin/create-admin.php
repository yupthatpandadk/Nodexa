<?php
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = strtolower(trim(getenv('NODEXA_ADMIN_EMAIL') ?: ''));
$username = strtolower(trim(getenv('NODEXA_ADMIN_USERNAME') ?: ''));
$firstName = trim(getenv('NODEXA_ADMIN_FIRST_NAME') ?: '');
$lastName = trim(getenv('NODEXA_ADMIN_LAST_NAME') ?: '');
$password = getenv('NODEXA_ADMIN_PASSWORD') ?: '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "NODEXA_ADMIN_EMAIL must be a valid email address.\n");
    exit(1);
}
if (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
    fwrite(STDERR, "NODEXA_ADMIN_USERNAME must be 3-64 characters and contain only letters, numbers, dot, underscore or dash.\n");
    exit(1);
}
if ($firstName === '' || $lastName === '') {
    fwrite(STDERR, "First name and last name are required.\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "NODEXA_ADMIN_PASSWORD must be at least 12 characters.\n");
    exit(1);
}

$existingUsername = User::where('username', $username)->where('email', '!=', $email)->exists();
if ($existingUsername) {
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

echo "Nodexa administrator ready: {$user->email} ({$user->username})\n";
