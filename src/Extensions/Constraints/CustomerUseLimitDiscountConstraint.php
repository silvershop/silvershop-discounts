<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Checkout\AppliedCouponCodes;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Model\DiscountCode;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

/**
 * Limits how many times a single customer may use a discount, and - when the discount is unlocked
 * by a child code (issue silvershop/silvershop-discounts#31) - how many times that customer may use
 * that specific code.
 *
 * The customer is the order's member when logged in, otherwise the order email. Email matching is
 * best-effort (a guest can use another email); member matching is exact.
 *
 * @property int $PerCustomerUseLimit
 */
class CustomerUseLimitDiscountConstraint extends DiscountConstraint
{
    private static array $db = [
        'PerCustomerUseLimit' => 'Int'
    ];

    private static array $field_labels = [
        'PerCustomerUseLimit' => 'Maximum uses per customer'
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        $owner = $this->getOwner();

        $fieldList->addFieldToTab(
            'Root.Constraints.ConstraintsTabs.General',
            NumericField::create(
                'PerCustomerUseLimit',
                _t(__CLASS__ . '.PERCUSTOMERUSELIMIT', $owner->fieldLabel('PerCustomerUseLimit')),
                0
            )->setDescription(_t(
                __CLASS__ . '.PERCUSTOMERUSELIMITDESC',
                'Note: 0 = unlimited. Logged-in customers are matched exactly; guests by email.'
            ))
        );
    }

    public function check(Discount $discount): bool
    {
        if (!isset($this->order)) {
            return true;
        }

        // discount-level per-customer limit
        if ((int) $discount->PerCustomerUseLimit > 0
            && $this->customerUseCount($discount, null) >= (int) $discount->PerCustomerUseLimit
        ) {
            $this->error(_t(
                __CLASS__ . '.PERCUSTOMERLIMITREACHED',
                'You have already used this discount the maximum number of times.'
            ));

            return false;
        }

        // per-code per-customer limit, when a child code unlocked the discount
        $child = singleton(CodeDiscountConstraint::class)->matchedDiscountCode($discount, $this->findCouponCodes());

        if ($child instanceof DiscountCode
            && (int) $child->PerCustomerUseLimit > 0
            && $this->customerUseCount($discount, (int) $child->ID) >= (int) $child->PerCustomerUseLimit
        ) {
            $this->error(_t(
                __CLASS__ . '.CODEPERCUSTOMERLIMITREACHED',
                'You have already used this code the maximum number of times.'
            ));

            return false;
        }

        return true;
    }

    protected function customerUseCount(Discount $discount, ?int $discountCodeID): int
    {
        $used = $discount->getAppliedOrders(true, $discountCodeID)->exclude('ID', (int) $this->order->ID);

        $memberID = (int) $this->order->MemberID;

        if ($memberID > 0) {
            return $used->filter('MemberID', $memberID)->count();
        }

        $email = (string) $this->order->getLatestEmail();

        if ($email === '') {
            return 0;
        }

        return $used->filter('Email', $email)->count();
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
