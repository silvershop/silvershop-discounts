<?php

namespace SilverShop\Discounts\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\i18n\i18n;
use Symfony\Component\Yaml\Yaml;

/**
 * Every language ships the same keys, and customer-facing coupon messages come out translated.
 */
class TranslationsTest extends SapphireTest
{
    private const LANGUAGES = ['en', 'nl', 'de', 'fr', 'it', 'es'];

    public function testLanguageFilesShareTheSameKeys(): void
    {
        $english = $this->keys('en');

        foreach (self::LANGUAGES as $language) {
            $this->assertSame($english, $this->keys($language), "lang/{$language}.yml has the same keys as en");
        }
    }

    public function testCouponMessagesAreTranslated(): void
    {
        $messages = i18n::with_locale('nl_NL', fn (): array => [
            _t('OrderCouponModifier.NOTFOUND', 'Coupon could not be found'),
            _t('OrderCoupon.EXPIRED', 'This coupon has already expired.'),
            _t(
                'SilverShop\Discounts\Checkout\CouponCheckoutComponent.MAXCOUPONS',
                'A maximum of {count} coupons can be used per order.',
                ['count' => 3]
            ),
        ]);

        $this->assertSame([
            'Kortingscode niet gevonden',
            'Deze kortingscode is verlopen.',
            'Je kunt maximaal 3 kortingscodes per bestelling gebruiken.',
        ], $messages);
    }

    /**
     * @return list<string>
     */
    private function keys(string $language): array
    {
        $data = Yaml::parseFile(dirname(__DIR__) . "/lang/{$language}.yml")[$language];
        $keys = [];

        foreach ($data as $namespace => $entries) {
            if (!is_array($entries)) {
                $keys[] = (string) $namespace;
                continue;
            }

            foreach (array_keys($entries) as $key) {
                $keys[] = $namespace . '.' . $key;
            }
        }

        sort($keys);

        return $keys;
    }
}
