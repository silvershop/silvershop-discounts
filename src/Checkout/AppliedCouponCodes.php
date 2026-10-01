<?php

namespace SilverShop\Discounts\Checkout;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Session;

/**
 * Stores the coupon codes the customer has entered for the current cart.
 *
 * Codes are kept in the session as a list under `cart.couponcodes`. The
 * legacy `cart.couponcode` key is kept in sync with the most recently applied
 * code for backwards compatibility with code that reads it directly.
 */
class AppliedCouponCodes
{
    public const SESSION_KEY = 'cart.couponcodes';

    public const LEGACY_SESSION_KEY = 'cart.couponcode';

    /**
     * @return array<int, string> uppercase codes, in the order they were applied
     */
    public static function get(): array
    {
        $session = static::session();

        if (!$session instanceof Session) {
            return [];
        }

        $codes = $session->get(static::SESSION_KEY);
        $codes = is_array($codes) ? $codes : [];

        // pick up a code set by older code (or an older session)
        $legacy = $session->get(static::LEGACY_SESSION_KEY);

        if (is_string($legacy) && $legacy !== '') {
            $codes[] = $legacy;
        }

        return static::normalise($codes);
    }

    /**
     * @param array<int, string> $codes
     */
    public static function set(array $codes): void
    {
        $session = static::session();

        if (!$session instanceof Session) {
            return;
        }

        $codes = static::normalise($codes);

        if ($codes === []) {
            static::clear();
            return;
        }

        $session->set(static::SESSION_KEY, $codes);
        $session->set(static::LEGACY_SESSION_KEY, end($codes));
    }

    public static function add(string $code): void
    {
        $codes = static::get();
        $codes[] = $code;

        static::set($codes);
    }

    public static function remove(string $code): void
    {
        $code = strtoupper(trim($code));

        static::set(array_filter(static::get(), fn (string $existing): bool => $existing !== $code));
    }

    public static function clear(): void
    {
        $session = static::session();

        if (!$session instanceof Session) {
            return;
        }

        $session->clear(static::SESSION_KEY);
        $session->clear(static::LEGACY_SESSION_KEY);
    }

    /**
     * @param array<mixed> $codes
     * @return array<int, string>
     */
    public static function normalise(array $codes): array
    {
        $output = [];

        foreach ($codes as $code) {
            if (!is_string($code)) {
                continue;
            }

            $code = strtoupper(trim($code));

            if ($code !== '' && !in_array($code, $output, true)) {
                $output[] = $code;
            }
        }

        return $output;
    }

    protected static function session(): ?Session
    {
        $request = Controller::curr()?->getRequest();

        return $request && $request->hasSession() ? $request->getSession() : null;
    }
}
