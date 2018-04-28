<?php

namespace SilverShop\Discounts\Checkout;

use SilverShop\Checkout\Component\CheckoutComponent;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Control\Controller;
use SilverShop\Model\Order;

class CouponCheckoutComponent extends CheckoutComponent
{
    protected $validwhenblank = false;

    public function getFormFields(Order $order)
    {
        $fields = FieldList::create(
            TextField::create('Code', _t("CouponForm.COUPON",
                'Enter your coupon code if you have one.'
            ))
        );

        return $fields;
    }

    public function setValidWhenBlank($valid)
    {
        $this->validwhenblank = $valid;
    }

    public function validateData(Order $order, array $data)
    {
        $result = new ValidationResult();
        $code = $data['Code'];

        if ($this->validwhenblank && !$code) {
            return $result;
        }

        // check the coupon exists, and can be used
        if ($coupon = OrderCoupon::get_by_code($code)) {
            if (!$coupon->validateOrder($order, ["CouponCode" => $code])) {
                $result->addError($coupon->getMessage(), "Code");

                throw new ValidationException($result);
            }
        } else {
            $result->addError(
                _t("OrderCouponModifier.NOTFOUND", "Coupon could not be found"),
                "Code"
            );

            throw new ValidationException($result);
        }


        return $result;
    }

    public function getData(Order $order)
    {
        return [
            'Code' => Controller::curr()->getRequest()->getSession()->get("cart.couponcode")
        ];
    }

    public function setData(Order $order, array $data)
    {
        Controller::curr()->getRequest()->getSession()->set("cart.couponcode", strtoupper($data['Code']));

        $order->getModifier(OrderDiscountModifier::class, true);
    }
}
