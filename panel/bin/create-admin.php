<?php
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$email = strtolower(trim(getenv('NODEXA_ADMIN_EMAIL') ?: 'admin@nodexa.local'));
$name = trim(getenv('NODEXA_ADMIN_NAME') ?: 'Administrator');
$password = getenv('NODEXA_ADMIN_PASSWORD') ?: '';

if ($password === '' || strlen($password) < 12) {
    fwrite(STDERR, "NODEXA_ADMIN_PASSWORD must be at least 12 characters.\n");
    exit(1);
}

$user = User::updateOrCreate(
    ['email'=>$email],
    ['name'=>$name,'password'=>Hash::make($password),'is_admin'=>true]
);

echo "Nodexa administrator ready: {$user->email}\n";
