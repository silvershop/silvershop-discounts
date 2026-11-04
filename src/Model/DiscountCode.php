<?php

namespace SilverShop\Discounts\Model;

use SilverShop\Model\Order;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\RandomGenerator;

/**
 * A redeemable code belonging to a {@link Discount} (typically an {@link OrderCoupon}).
 *
 * A discount can own many codes, so one promotion - one set of rules, one usage report - can be
 * handed out as many unique codes, optionally each with its own use limit. Entering any of a
 * discount's codes at checkout resolves to the same discount, which still applies once.
 *
 * Usage is tracked directly on the record: `TimesUsed` is a stored counter (shown in the CMS
 * grid and incremented when an order that redeemed the code is paid). It is named `TimesUsed`
 * rather than `UseCount` so it never collides with {@link Discount::getUseCount()}, which stays
 * the authoritative derived count used for reconciliation.
 *
 * @property string $Code
 * @property int $UseLimit
 * @property int $PerCustomerUseLimit
 * @property int $TimesUsed
 * @property bool $Active
 * @property int $DiscountID
 * @method   Discount Discount()
 */
class DiscountCode extends DataObject
{
    private static array $db = [
        'Code' => 'Varchar(25)',
        'UseLimit' => 'Int',
        'PerCustomerUseLimit' => 'Int',
        'TimesUsed' => 'Int',
        'Active' => 'Boolean'
    ];

    private static array $has_one = [
        'Discount' => Discount::class
    ];

    private static array $defaults = [
        'Active' => true
    ];

    private static array $indexes = [
        'Code' => true
    ];

    private static array $summary_fields = [
        'Code',
        'TimesUsed' => 'Used',
        'UseLimit' => 'Limit',
        'Active.Nice' => 'Active'
    ];

    private static array $field_labels = [
        'UseLimit' => 'Maximum number of uses (0 = unlimited)',
        'PerCustomerUseLimit' => 'Maximum uses per customer (0 = unlimited)',
        'TimesUsed' => 'Used'
    ];

    private static string $singular_name = 'Code';

    private static string $plural_name = 'Codes';

    private static string $default_sort = 'Code ASC';

    private static string $table_name = 'SilverShop_DiscountCode';

    /**
     * Resolve a code string to its DiscountCode, matching case-insensitively.
     */
    public static function get_by_code(string $code): ?self
    {
        $code = self::normalise_code($code);

        if ($code === '') {
            return null;
        }

        return self::get()->filter('Code:nocase', $code)->first();
    }

    /**
     * Whether a code is already taken by a DiscountCode or an OrderCoupon, so generated and
     * hand-entered codes never collide across either table.
     */
    public static function code_exists(string $code): bool
    {
        $code = self::normalise_code($code);

        if ($code === '') {
            return false;
        }

        return self::get()->filter('Code:nocase', $code)->exists()
            || OrderCoupon::get()->filter('Code:nocase', $code)->exists();
    }

    /**
     * Generate a code that is unique across both DiscountCode and OrderCoupon.
     */
    public static function generate_code(?int $length = null, string $prefix = ''): string
    {
        $length = $length !== null && $length !== 0
            ? $length
            : (int) OrderCoupon::config()->get('generated_code_length');
        $randomGenerator = Injector::inst()->create(RandomGenerator::class);

        do {
            $code = $prefix . strtoupper(substr($randomGenerator->randomToken(), 0, $length));
        } while (self::code_exists($code));

        return $code;
    }

    public static function normalise_code(string $code): string
    {
        return strtoupper(trim((string) preg_replace('/[^0-9a-zA-Z]+/', '', $code)));
    }

    /**
     * Forces codes to be alpha-numeric, uppercase, and trimmed (mirrors OrderCoupon::setCode()).
     */
    public function setCode(string $code): static
    {
        $this->setField('Code', self::normalise_code($code));

        return $this;
    }

    public function validate(): ValidationResult
    {
        $validationResult = parent::validate();
        $code = (string) $this->getField('Code');

        if ($code === '') {
            $validationResult->addError(
                _t(__CLASS__ . '.CODEREQUIRED', 'A code is required.'),
                ValidationResult::TYPE_ERROR,
                'CODEREQUIRED'
            );

            return $validationResult;
        }

        // unique across both tables (ignoring this record itself)
        if ($this->isChanged('Code')) {
            $clash = self::get()->filter('Code:nocase', $code)->exclude('ID', (int) $this->ID)->exists()
                || OrderCoupon::get()->filter('Code:nocase', $code)->exists();

            if ($clash) {
                $validationResult->addError(
                    _t(__CLASS__ . '.CODETAKEN', 'The code "{code}" is already in use.', ['code' => $code]),
                    ValidationResult::TYPE_ERROR,
                    'CODETAKEN'
                );
            }
        }

        return $validationResult;
    }

    /**
     * Orders this code has been redeemed on - paid, and optionally in-flight (unpaid within the
     * timeout). Delegates to the owning discount's query, narrowed to this code via the
     * DiscountCodeID link column.
     */
    public function getAppliedOrders(bool $includeunpaid = false): DataList
    {
        $discount = $this->Discount();

        if (!$discount->exists() || !$this->isInDB()) {
            return Order::get()->filter('ID', 0);
        }

        return $discount->getAppliedOrders($includeunpaid, (int) $this->ID);
    }

    /**
     * Effective number of uses for limit checks: the derived, pending-aware count, so a limited code
     * can't be oversold while carts that applied it are still in flight. Deliberately not named
     * getUseCount() so it never shadows a DB field.
     */
    public function effectiveUseCount(?int $orderID = null): int
    {
        $used = $this->getAppliedOrders(true);

        if ($orderID !== null && $orderID !== 0) {
            $used = $used->exclude('ID', $orderID);
        }

        return $used->count();
    }

    /**
     * Recompute and store the paid-only {@see $TimesUsed} counter for this code.
     */
    public function reconcileTimesUsed(): void
    {
        $count = $this->getAppliedOrders(false)->count();

        if ((int) $this->getField('TimesUsed') !== $count) {
            $this->setField('TimesUsed', $count);
            $this->write();
        }
    }

    public function canView($member = null): bool
    {
        return true;
    }

    /** @param array<string, mixed> $context */
    public function canCreate($member = null, $context = []): bool
    {
        $memberArg = $member instanceof Member ? $member : 0;

        return (bool) Permission::checkMember($memberArg, 'MANAGE_DISCOUNTS');
    }

    public function canEdit($member = null): bool
    {
        $memberArg = $member instanceof Member ? $member : 0;

        return (bool) Permission::checkMember($memberArg, 'MANAGE_DISCOUNTS');
    }

    public function canDelete($member = null): bool
    {
        return $this->effectiveUseCount() === 0 && $this->canEdit($member);
    }
}
