<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Builds a unique username for a user who wasn't given one: the part of the
 * email before "@" (rowshan.runa@rsc-bd.org -> rowshan.runa), or else the
 * first and last word of the name without titles (Md. Shajahan Mia ->
 * shajahan.mia). If that's taken, a name with a title tries it in front
 * (mdshajahan.mia); after that the first choice gets a number:
 * shajahan.mia2, 3, ...
 */
class UsernameGenerator
{
    /** Titles left out of a name-based username */
    private const TITLES = ['md', 'mr', 'mrs', 'ms', 'miss', 'dr', 'engr', 'prof'];

    /**
     * @param  array<int, string>  $reserved  usernames to avoid that aren't saved yet (e.g. earlier rows of an import)
     */
    public static function generate(?string $email, ?string $name, array $reserved = []): ?string
    {
        $fromEmail = self::fromEmail($email);
        $candidates = $fromEmail !== null ? [$fromEmail] : self::fromName($name);

        if ($candidates === []) {
            return null;
        }

        $reserved = array_map('strtolower', $reserved);
        $isFree = fn (string $username) => ! in_array($username, $reserved, true)
            && ! User::where('username', $username)->exists();

        foreach ($candidates as $username) {
            if ($isFree($username)) {
                return $username;
            }
        }

        for ($n = 2; ! $isFree($candidates[0].$n); $n++);

        return $candidates[0].$n;
    }

    private static function fromEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        $local = Str::before(Str::lower(Str::ascii($email)), '@');
        $local = trim(preg_replace('/[^a-z0-9._-]/', '', $local), '._-');

        return $local === '' ? null : $local;
    }

    /**
     * @return array<int, string> shajahan.mia, then mdshajahan.mia when the name has a title
     */
    private static function fromName(?string $name): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii((string) $name)), -1, PREG_SPLIT_NO_EMPTY);
        $titles = array_values(array_intersect($words, self::TITLES));
        $withoutTitles = array_values(array_diff($words, self::TITLES));

        if ($withoutTitles === []) {
            return $words === [] ? [] : [implode('', $words)];
        }

        $base = count($withoutTitles) === 1 ? $withoutTitles[0] : $withoutTitles[0].'.'.end($withoutTitles);

        return $titles === [] ? [$base] : [$base, implode('', $titles).$base];
    }
}
