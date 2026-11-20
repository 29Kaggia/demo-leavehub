<?php
declare(strict_types=1);

// The normal front controller loads Composer before bootstrapping CakePHP.
// This standalone deployment page must do the same.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

use Cake\Core\Configure;
use Cake\Log\Log;
use Migrations\Migrations;

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if (Configure::read('debug')) {
    http_response_code(404);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>LeaveHub database setup</title>';
    echo '<form method="post">';
    echo '<label>One-time setup token <input type="password" name="token" required autocomplete="off"></label><br>';
    echo '<label>First HR admin name <input name="first_name" required></label><br>';
    echo '<label>Last name <input name="last_name" required></label><br>';
    echo '<label>Work email <input type="email" name="email" required></label><br>';
    echo '<label>Admin password (at least 8 characters) <input type="password" name="password" minlength="8" required autocomplete="new-password"></label><br>';
    echo '<button>Run database setup and create HR admin</button></form>';
    exit;
}

$expected = (string)Configure::read('Deployment.migrationToken');
$provided = (string)($_POST['token'] ?? '');
if (strlen($expected) < 32 || str_starts_with($expected, 'REPLACE_') || !hash_equals($expected, $provided)) {
    http_response_code(403);
    exit('Setup token is invalid.');
}

try {
    if (!(new Migrations())->migrate()) {
        throw new RuntimeException('Migration runner reported failure.');
    }

    $employees = \Cake\ORM\TableRegistry::getTableLocator()->get('Employees');
    if (!$employees->exists(['role' => 'hr_admin'])) {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            http_response_code(400);
            exit('Enter a valid name, work email, and password of at least 8 characters.');
        }
        $admin = $employees->newEntity([
            'employee_number' => 'EMP-' . strtoupper(bin2hex(random_bytes(4))),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'hr_admin',
            'is_active' => true,
        ]);
        if (!$employees->save($admin)) {
            throw new RuntimeException('Could not create the first HR administrator.');
        }
    }

    @unlink(__FILE__);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database setup completed. This one-time page has been removed.';
} catch (Throwable $exception) {
    Log::error('Production database setup failed: ' . $exception->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database setup failed. Check the CakePHP error log before retrying.';
}
