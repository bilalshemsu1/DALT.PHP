<?php

declare(strict_types=1);

$password = 'correct horse battery staple';
$aliceHash = password_hash($password, PASSWORD_DEFAULT);
$bobHash = password_hash($password, PASSWORD_DEFAULT);

$databaseRow = [
    'id' => 7,
    'email' => 'alice@example.com',
    'password' => $aliceHash,
];

$publicUser = [
    'id' => (string) $databaseRow['id'],
    'email' => $databaseRow['email'],
];

echo 'stored plaintext: ', $databaseRow['password'] === $password ? 'yes' : 'no', "\n";
echo 'same password, same hash: ', $aliceHash === $bobHash ? 'yes' : 'no', "\n";
echo 'correct password verifies: ', password_verify($password, $aliceHash) ? 'yes' : 'no', "\n";
echo 'wrong password verifies: ', password_verify('definitely wrong', $aliceHash) ? 'yes' : 'no', "\n";
echo 'public fields: ', implode(',', array_keys($publicUser)), "\n";
