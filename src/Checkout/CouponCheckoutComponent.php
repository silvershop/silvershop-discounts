<?php

namespace SilverShop\Discounts\Checkout;

use SilverShop\Checkout\Component\CheckoutComponent;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Control\Controller;
use SilverShop\Model\Order;

class CouponCheckoutComponent extends CheckoutComponent
{
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
        $code = $data['Code'];

        if ($this->validwhenblank && !$code) {
            return $validationResult->isValid();
        }

        // check the coupon exists, and can be used
        if ($coupon = OrderCoupon::get_by_code($code)) {
            if (!$coupon->validateOrder($order, ['CouponCode' => $code])) {
                $validationResult->addError($coupon->getMessage(), 'Code');

                throw ValidationException::create($validationResult);
            }
        } else {
            $validationResult->addError(
                _t('OrderCouponModifier.NOTFOUND', 'Coupon could not be found'),
                'Code'
            );

            throw ValidationException::create($validationResult);
        }

        return $validationResult->isValid();
    }

    /** @return array{Code: mixed} */
    public function getData(Order $order): array
    {
        $controller = Controller::curr();
        return [
            'Code' => $controller ? $controller->getRequest()->getSession()->get('cart.couponcode') : null
        ];
    }

    /** @param array<string, mixed> $data */
    public function setData(Order $order, array $data): Order
    {
        if (!empty($data['Code']) && ($controller = Controller::curr())) {
            $controller->getRequest()->getSession()->set('cart.couponcode', strtoupper((string) $data['Code']));
        }

        $order->getModifier(OrderDiscountModifier::class, true);
        return $order;
    }
}
