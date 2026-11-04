<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Checkout\AppliedCouponCodes;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Model\DiscountCode;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\ORM\DataList;

/**
 * Restricts a discount to orders where its code has been entered.
 *
 * A discount can be coded two ways, which coexist: a single legacy `Code` on the discount itself,
 * and/or many child {@link DiscountCode} records (one promotion handed out as many codes - issue
 * silvershop/silvershop-discounts#31). A coded discount matches when the legacy code, or any of its
 * active child codes, is among the codes applied to the cart.
 *
 * The context may provide a single `CouponCode`, a list of `CouponCodes`, or both.
 *
 * @property ?string $Code
 * @method   \SilverStripe\ORM\HasManyList<DiscountCode> Codes()
 */
class CodeDiscountConstraint extends DiscountConstraint
{
    private static array $db = [
        'Code' => 'Varchar(25)'
    ];

    private static array $has_many = [
        'Codes' => DiscountCode::class . '.Discount'
    ];

    private static array $owns = [
        'Codes'
    ];

    private static array $cascade_deletes = [
        'Codes'
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        $owner = $this->getOwner();

        if (!$owner->isInDB()) {
            return;
        }

        $fieldList->addFieldToTab(
            'Root.Codes',
            GridField::create(
                'Codes',
                _t(__CLASS__ . '.Codes', 'Codes'),
                $owner->Codes(),
                GridFieldConfig_RecordEditor::create()
            )
        );
    }

    public function filter(DataList $dataList): DataList
    {
        $table = Discount::config()->get('table_name');
        $codeTable = DiscountCode::config()->get('table_name');
        $codeColumn = sprintf('"%s"."Code"', $table);
        $codes = $this->findCouponCodes();

        if ($codes !== []) {
            $inList = implode(', ', array_map(
                fn (string $code): string => "'" . Convert::raw2sql($code) . "'",
                $codes
            ));

            // match: legacy code in the applied list, OR a child code in the applied list,
            // OR the discount is not coded at all (no legacy code AND no child codes).
            return $dataList->where(sprintf(
                '(%1$s IN (%2$s)) '
                . 'OR ("%3$s"."ID" IN (SELECT "DiscountID" FROM "%4$s" WHERE "Code" IN (%2$s))) '
                . 'OR (%1$s IS NULL AND "%3$s"."ID" NOT IN (SELECT "DiscountID" FROM "%4$s"))',
                $codeColumn,
                $inList,
                $table,
                $codeTable
            ));
        }

        // nothing entered: only discounts with neither a legacy code nor any child code.
        return $dataList->where(sprintf(
            '(%1$s IS NULL) AND ("%2$s"."ID" NOT IN (SELECT "DiscountID" FROM "%3$s"))',
            $codeColumn,
            $table,
            $codeTable
        ));
    }

    public function check(Discount $discount): bool
    {
        // a discount with no code at all is unconstrained by code
        if (!$discount->Code && !$discount->Codes()->exists()) {
            return true;
        }

        if ($this->matchedCode($discount, $this->findCouponCodes()) !== null) {
            return true;
        }

        $this->error("Coupon code doesn't match " . implode(', ', $this->findCouponCodes()));

        return false;
    }

    /**
     * The code by which $discount is matched among the applied $codes - its legacy `Code` or the
     * `Code` of a matching active child {@link DiscountCode} - or null if none matches. Shared with
     * the calculator so a redemption can be attributed to the specific code that unlocked it.
     *
     * @param array<int, string> $codes uppercase, normalised
     */
    public function matchedCode(Discount $discount, array $codes): ?string
    {
        if ($codes === []) {
            return null;
        }

        if ($discount->Code && in_array(strtoupper((string) $discount->Code), $codes, true)) {
            return strtoupper((string) $discount->Code);
        }

        foreach ($discount->Codes() as $child) {
            if ($child->Active && in_array(strtoupper((string) $child->Code), $codes, true)) {
                return strtoupper((string) $child->Code);
            }
        }

        return null;
    }

    /**
     * The matching active child {@link DiscountCode} for $discount among the applied $codes, or null.
     *
     * @param array<int, string> $codes uppercase, normalised
     */
    public function matchedDiscountCode(Discount $discount, array $codes): ?DiscountCode
    {
        if ($codes === []) {
            return null;
        }

        foreach ($discount->Codes() as $child) {
            if ($child->Active && in_array(strtoupper((string) $child->Code), $codes, true)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return array<int, string> uppercase codes
     */
    protected function findCouponCodes(): array
    {
        $codes = $this->context['CouponCodes'] ?? [];
        $codes = is_array($codes) ? $codes : [$codes];

        if (!empty($this->context['CouponCode'])) {
            $codes[] = $this->context['CouponCode'];
        }

        return AppliedCouponCodes::normalise($codes);
    }
}
