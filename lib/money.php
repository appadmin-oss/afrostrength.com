<?php
declare(strict_types=1);

/**
 * Money, in kobo.
 *
 * Every amount in this platform is an integer number of kobo. Not a float,
 * not a decimal string, not naira — kobo, as a whole number.
 *
 * The reason is the usual one and it is not theoretical: 0.1 + 0.2 is
 * 0.30000000000000004 in binary floating point, and a ledger that is out by a
 * kobo is a ledger the office stops trusting. PHP's int is 64-bit on every
 * host this will run on, which is more kobo than Afrostrength will ever
 * invoice.
 *
 * Naira is a presentation concern. It appears in this file and nowhere else.
 */

const KOBO_PER_NAIRA = 100;

/** "₦12,500.00". The symbol, grouped thousands, always two decimal places. */
function naira(int $kobo): string
{
    $sign = $kobo < 0 ? '-' : '';
    $kobo = abs($kobo);
    return $sign . '₦' . number_format(intdiv($kobo, KOBO_PER_NAIRA))
        . '.' . str_pad((string)($kobo % KOBO_PER_NAIRA), 2, '0', STR_PAD_LEFT);
}

/** "NGN 12,500.00" — for email and CSV, where the symbol may not survive. */
function naira_plain(int $kobo): string
{
    return 'NGN ' . number_format($kobo / KOBO_PER_NAIRA, 2, '.', ',');
}

/**
 * Read an amount a person typed, in naira, and return kobo.
 *
 * Accepts "12500", "12,500", "₦12,500.00", " 12500.5 ". Refuses anything
 * else rather than guessing — an amount the system misread is worse than an
 * amount it refused, because the first one gets saved.
 *
 * @return array{ok:bool, kobo?:int, error?:string}
 */
function parse_naira(string $input): array
{
    $clean = trim($input);
    $clean = str_replace(['₦', 'NGN', 'ngn', ',', ' ', "\u{00A0}"], '', $clean);

    if ($clean === '') return ['ok' => false, 'error' => 'Put in an amount.'];
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
        return ['ok' => false, 'error' => 'Write it as naira, like 12500 or 12500.00.'];
    }

    [$whole, $fraction] = array_pad(explode('.', $clean, 2), 2, '0');
    // Padded, not cast: "12500.5" means fifty kobo, and (int)"5" is five.
    $fraction = str_pad($fraction, 2, '0');

    if (strlen($whole) > 15) {
        return ['ok' => false, 'error' => 'That is larger than any real fee. Check the figure.'];
    }

    return ['ok' => true, 'kobo' => ((int)$whole * KOBO_PER_NAIRA) + (int)$fraction];
}
