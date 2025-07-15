<?php

namespace SilverShop\Discounts\Checkout\Step;

use SilverShop\Checkout\Step\CheckoutStep;
use SilverShop\Checkout\CheckoutComponentConfig;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Discounts\Checkout\CouponCheckoutComponent;
use SilverShop\Forms\CheckoutForm;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;

class CheckoutStepDiscount extends CheckoutStep
{
    private static array $allowed_actions = [
        'discount',
        'CouponForm',
        'setcoupon'
    ];

    protected function checkoutconfig(): CheckoutComponentConfig
    {
        $checkoutComponentConfig = CheckoutComponentConfig::create(ShoppingCart::curr(), true);
        $checkoutComponentConfig->addComponent($couponCheckoutComponent = CouponCheckoutComponent::create());

        $couponCheckoutComponent->setValidWhenBlank(true);

        return $checkoutComponentConfig;
    }

    public function discount(): array
    {
        return [
            'OrderForm' => $this->CouponForm()
        ];
    }

    public function CouponForm(): CheckoutForm
    {
        $checkoutForm = CheckoutForm::create($this->owner, 'CouponForm', $this->checkoutconfig());
        $checkoutForm->setActions(
            FieldList::create(FormAction::create('setcoupon', _t('SilverShop\Checkout\Step\CheckoutStep.Continue', 'Continue')))
        );
        $this->owner->extend('updateCouponForm', $checkoutForm);

        return $checkoutForm;
    }

    public function setcoupon($data, $form)
    {
        $this->checkoutconfig()->setData($form->getData());
        return $this->owner->redirect($this->NextStepLink());
    }
}
