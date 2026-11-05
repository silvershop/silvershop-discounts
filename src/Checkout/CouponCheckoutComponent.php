<?php

namespace SilverShop\Discounts\Checkout;

use SilverShop\Checkout\Component\CheckoutComponent;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverShop\Model\Order;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Cache\RateLimiter;

/**
 * Lets the customer enter coupon codes.
 *
 * Only one coupon can be applied at a time unless every applied coupon has
 * "Allow stacking" enabled, in which case the codes accumulate.
 */
class CouponCheckoutComponent extends CheckoutComponent
{
    /**
     * Maximum number of coupon codes that can be applied to a single order.
     * 0 means unlimited.
     */
    private static int $max_coupons_per_order = 5;

    /**
     * When a coupon that can't be stacked is entered while other coupons are
     * applied (or vice versa): true replaces the applied coupons with the new
     * one (the behaviour prior to stacking support), false rejects the new
     * coupon with a validation message.
     */
    private static bool $replace_non_stackable = true;

    /**
     * Failed coupon-code lookups allowed from one client before it is temporarily blocked, to slow
     * down code enumeration / guessing. 0 disables the limit.
     */
    private static int $apply_attempt_limit = 10;

    /**
     * How long (in minutes) the coupon-attempt block lasts once the limit is reached.
     */
    private static int $apply_attempt_decay = 5;

    /**
     * When true, a rejected coupon returns a single uniform message instead of revealing whether the
     * code was unknown or merely invalid, which removes an enumeration oracle, at the cost of less
     * helpful feedback for genuine customers. Off by default; enable it for high-value coupon runs.
     */
    private static bool $generic_coupon_errors = false;

    protected bool $validwhenblank = false;

    public function getFormFields(Order $order): FieldList
    {
        return FieldList::create(
            TextField::create(
                'Code',
                _t(
                    'CouponForm.COUPON',
                    'Enter your coupon code if you have one.'
                )
            )
        );
    }

    public function setValidWhenBlank(bool $valid): void
    {
        $this->validwhenblank = $valid;
    }

    /** @param array<string, mixed> $data */
    public function validateData(Order $order, array $data): bool
    {
        $validationResult = ValidationResult::create();
        $code = strtoupper(trim((string) ($data['Code'] ?? '')));

        if ($this->validwhenblank && !$code) {
            return $validationResult->isValid();
        }

        $rateLimiter = $this->getRateLimiter();

        if ($rateLimiter && !$rateLimiter->canAccess()) {
            $validationResult->addError(
                _t(
                    __CLASS__ . '.TOOMANYATTEMPTS',
                    'Too many coupon attempts. Please wait a moment and try again.'
                ),
                'Code'
            );

            throw ValidationException::create($validationResult);
        }

        // check the coupon exists, and can be used
        $coupon = $code !== '' ? OrderCoupon::get_by_code($code) : null;

        if (!$coupon instanceof OrderCoupon) {
            // count only unknown codes against the limit: that is the enumeration signal, and it
            // avoids locking out a customer retrying a single real-but-invalid code.
            if ($rateLimiter) {
                $rateLimiter->hit();
            }

            $validationResult->addError($this->couponErrorMessage(), 'Code');

            throw ValidationException::create($validationResult);
        }

        if (!$coupon->validateOrder($order, ['CouponCode' => $code, 'CouponCodes' => [$code]])) {
            $validationResult->addError($this->couponErrorMessage($coupon), 'Code');

            throw ValidationException::create($validationResult);
        }

        if ($message = $this->getStackingError($coupon)) {
            $validationResult->addError($message, 'Code');

            throw ValidationException::create($validationResult);
        }

        if ($rateLimiter) {
            $rateLimiter->clearAttempts();
        }

        return $validationResult->isValid();
    }

    /**
     * The validation message for a rejected coupon. With generic_coupon_errors on, both "unknown" and
     * "invalid" return the same wording so the response can't be used to tell real codes from fake
     * ones; otherwise the specific reason is kept for helpful customer feedback.
     */
    protected function couponErrorMessage(?OrderCoupon $coupon = null): string
    {
        if (static::config()->get('generic_coupon_errors')) {
            return _t(
                __CLASS__ . '.CANNOTAPPLY',
                "Sorry, that coupon code can't be applied to your order."
            );
        }

        if ($coupon instanceof OrderCoupon) {
            return (string) $coupon->getMessage();
        }

        return _t('OrderCouponModifier.NOTFOUND', 'Coupon could not be found');
    }

    /**
     * A per-client rate limiter for coupon attempts, keyed by client IP, or null when limiting is
     * disabled or there is no HTTP context (CLI, unit tests).
     */
    protected function getRateLimiter(): ?RateLimiter
    {
        $limit = (int) static::config()->get('apply_attempt_limit');
        $controller = Controller::curr();

        if ($limit <= 0 || $controller === null) {
            return null;
        }

        $ip = $controller->getRequest()->getIP() ?: 'unknown';
        $decay = max(1, (int) static::config()->get('apply_attempt_decay'));

        return RateLimiter::create('shop-coupon-apply-' . md5($ip), $limit, $decay);
    }

    /**
     * Check whether the given coupon can be added alongside the coupons that
     * are already applied. Returns an error message, or null if it can.
     */
    public function getStackingError(OrderCoupon $orderCoupon): ?string
    {
        $applied = $this->getAppliedCoupons();
        unset($applied[strtoupper((string) $orderCoupon->Code)]);

        if ($applied === []) {
            return null;
        }

        if (!$this->canStackWith($orderCoupon, $applied)) {
            if (static::config()->get('replace_non_stackable')) {
                // setData() will replace the applied coupons
                return null;
            }

            if (!$orderCoupon->AllowStacking) {
                return _t(
                    __CLASS__ . '.NOTSTACKABLE',
                    'This coupon can\'t be combined with other coupons. Remove {codes} to use it.',
                    ['codes' => implode(', ', array_keys($applied))]
                );
            }

            return _t(
                __CLASS__ . '.APPLIEDNOTSTACKABLE',
                'The coupon {codes} can\'t be combined with other coupons.',
                ['codes' => implode(', ', array_keys($this->getNonStackable($applied)))]
            );
        }

        $max = (int) static::config()->get('max_coupons_per_order');

        if ($max > 0 && count($applied) + 1 > $max) {
            return _t(
                __CLASS__ . '.MAXCOUPONS',
                'A maximum of {count} coupons can be used per order.',
                ['count' => $max]
            );
        }

        return null;
    }

    /** @return array{Code: ?string, Codes: array<int, string>} */
    public function getData(Order $order): array
    {
        $codes = AppliedCouponCodes::get();

        return [
            'Code' => $codes === [] ? null : end($codes),
            'Codes' => $codes
        ];
    }

    /** @param array<string, mixed> $data */
    public function setData(Order $order, array $data): Order
    {
        if (!empty($data['Code'])) {
            $code = strtoupper(trim((string) $data['Code']));
            $coupon = OrderCoupon::get_by_code($code);
            $applied = $this->getAppliedCoupons();
            unset($applied[$code]);

            if ($coupon instanceof OrderCoupon && $this->canStackWith($coupon, $applied)) {
                AppliedCouponCodes::add($code);
            } else {
                AppliedCouponCodes::set([$code]);
            }
        }

        $order->getModifier(OrderDiscountModifier::class, true);
        return $order;
    }

    /**
     * Coupons currently applied to the cart, keyed by code. Codes that no
     * longer resolve to a coupon are dropped.
     *
     * @return array<string, OrderCoupon>
     */
    protected function getAppliedCoupons(): array
    {
        $coupons = [];

        foreach (AppliedCouponCodes::get() as $code) {
            if (($coupon = OrderCoupon::get_by_code($code)) instanceof OrderCoupon) {
                $coupons[$code] = $coupon;
            }
        }

        return $coupons;
    }

    /**
     * @param array<string, OrderCoupon> $applied
     */
    protected function canStackWith(OrderCoupon $orderCoupon, array $applied): bool
    {
        if ($applied === []) {
            return true;
        }

        return $orderCoupon->AllowStacking && $this->getNonStackable($applied) === [];
    }

    /**
     * @param array<string, OrderCoupon> $coupons
     * @return array<string, OrderCoupon>
     */
    protected function getNonStackable(array $coupons): array
    {
        return array_filter($coupons, fn (OrderCoupon $orderCoupon): bool => !$orderCoupon->AllowStacking);
    }
}
