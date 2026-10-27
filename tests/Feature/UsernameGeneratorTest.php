<?php

use App\Models\User;
use App\Support\UsernameGenerator;
use Illuminate\Support\Facades\Hash;

function makeUserWithUsername(string $username): User
{
    return User::create([
        'name' => 'Existing '.$username,
        'username' => $username,
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

it('takes the username from the email', function () {
    expect(UsernameGenerator::generate('Rowshan.Runa@rsc-bd.org', 'Rowshan Runa'))->toBe('rowshan.runa');
});

it('takes the username from the name without titles when there is no email', function (string $name, string $username) {
    expect(UsernameGenerator::generate(null, $name))->toBe($username);
})->with([
    ['Md. Shajahan Mia', 'shajahan.mia'],
    ['MD SHAJAHAN MIA', 'shajahan.mia'],
    ['Md. Abdul Karim Khan', 'abdul.khan'],
    ['Dr. Nasreen', 'nasreen'],
    ['Md.', 'md'],
]);

it('puts the title in front when the username is taken, then numbers it', function () {
    makeUserWithUsername('shajahan.mia');

    expect(UsernameGenerator::generate(null, 'Md. Shajahan Mia'))->toBe('mdshajahan.mia');

    makeUserWithUsername('mdshajahan.mia');
    makeUserWithUsername('shajahan.mia2');

    expect(UsernameGenerator::generate(null, 'Md. Shajahan Mia'))->toBe('shajahan.mia3')
        ->and(UsernameGenerator::generate(null, 'Md. Shajahan Mia', ['shajahan.mia3']))->toBe('shajahan.mia4');
});

it('numbers a taken username straight away when there is no title', function () {
    makeUserWithUsername('shajahan.mia');
    makeUserWithUsername('rowshan.runa');

    expect(UsernameGenerator::generate(null, 'Shajahan Mia'))->toBe('shajahan.mia2')
        ->and(UsernameGenerator::generate('rowshan.runa@rsc-bd.org', 'Md. Rowshan Runa'))->toBe('rowshan.runa2');
});

it('returns null when there is nothing to build a username from', function () {
    expect(UsernameGenerator::generate(null, null))->toBeNull()
        ->and(UsernameGenerator::generate('', '  '))->toBeNull();
});
