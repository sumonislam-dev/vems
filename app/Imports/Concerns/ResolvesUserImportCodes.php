<?php

namespace App\Imports\Concerns;

use App\Models\Department;
use App\Models\User;
use App\Models\Zone;
use App\Support\UsernameGenerator;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Shared by the user and driver imports: department and zone codes, the
 * zone access a row grants, generated usernames and spreadsheet cell
 * clean-up.
 */
trait ResolvesUserImportCodes
{
    public const ALL_ZONES = 'ALL';

    /** @var array<string, int> lowercase department code => id */
    private array $departments;

    /** @var array<string, int> lowercase zone code => id, limited to the importer's zones */
    private array $zones;

    private bool $canGrantAllZones;

    /** @var array<int, string> usernames used by earlier rows of this file */
    private array $usernames = [];

    private ?string $passwordHash = null;

    /**
     * Imported accounts get a password nobody knows (users reset it). One
     * hash serves the whole file: hashing takes ~0.25 s, and doing it per
     * row ran large imports past the server's time limit.
     */
    private function unknownPasswordHash(): string
    {
        return $this->passwordHash ??= Hash::make(Str::random(40));
    }

    private function loadCodes(User $importer): void
    {
        $this->departments = Department::pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [strtolower($code) => $id])
            ->all();

        $this->canGrantAllZones = Zone::canGrantAllZones($importer);
        $this->zones = Zone::whereIn('id', Zone::selectableBy($importer)->pluck('id'))
            ->pluck('id', 'code')
            ->mapWithKeys(fn ($id, $code) => [strtolower($code) => $id])
            ->all();
    }

    /**
     * Cells come back as numbers for numeric-looking values (phone numbers,
     * employee ids) and missing columns are absent; make every expected
     * column a trimmed string (or null).
     *
     * @param  array<int, string>  $columns
     */
    private function trimColumns(array $data, array $columns): array
    {
        foreach ($columns as $column) {
            $value = isset($data[$column]) ? trim((string) $data[$column]) : '';
            $data[$column] = $value === '' ? null : $value;
        }

        return $data;
    }

    /** A blank username is generated from the email or name, unique within the file too */
    private function withUsername(array $data): array
    {
        $data['username'] ??= UsernameGenerator::generate($data['email'], $data['name'], $this->usernames);
        if ($data['username']) {
            $this->usernames[] = $data['username'];
        }

        return $data;
    }

    /**
     * An Excel date cell (a serial number) or text like 2027-12-31,
     * 31/12/2027 or 31-12-2027 => "2027-12-31"; anything else is left as
     * typed so the date rule reports it.
     */
    private function toDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    private function departmentId(?string $code): ?int
    {
        return $code ? $this->departments[strtolower($code)] : null;
    }

    private function departmentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! isset($this->departments[strtolower($value)])) {
                $fail("Unknown department code \"{$value}\".");
            }
        };
    }

    private function zoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $codes = $this->zoneCodes($value);

            if (in_array(self::ALL_ZONES, $codes, true)) {
                if ($codes !== [self::ALL_ZONES]) {
                    $fail('Use ALL on its own, not with other zone codes.');
                } elseif (! $this->canGrantAllZones) {
                    $fail('You are not allowed to grant access to all zones.');
                }

                return;
            }

            $unknown = array_diff($codes, array_keys($this->zones));
            if ($unknown !== []) {
                $fail('Unknown or not allowed zone code "'.strtoupper(implode(', ', $unknown)).'".');
            }
        };
    }

    private function applyZones(User $user, ?string $value): void
    {
        $zoneCodes = $this->zoneCodes($value);
        if ($zoneCodes === [self::ALL_ZONES]) {
            Zone::applyAccess($user, 'all');
        } else {
            Zone::applyAccess($user, 'specific', array_map(fn ($code) => $this->zones[$code], $zoneCodes));
        }
    }

    /**
     * "dhk, CTG" => ['dhk', 'ctg']; "all" => ['ALL'].
     *
     * @return array<int, string>
     */
    private function zoneCodes(?string $value): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($code) => strtolower(trim($code)),
            explode(',', (string) $value)
        ))));

        return array_map(fn ($code) => $code === strtolower(self::ALL_ZONES) ? self::ALL_ZONES : $code, $codes);
    }
}
