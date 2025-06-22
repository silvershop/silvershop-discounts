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
        $config = new CheckoutComponentConfig(ShoppingCart::curr(), true);
        $config->addComponent($comp = new CouponCheckoutComponent());

        $comp->setValidWhenBlank(true);

        return $config;
    }

    public function discount(): array
    {
        return [
            'OrderForm' => $this->CouponForm()
        ];
    }

    public function CouponForm(): CheckoutForm
    {
        $form = CheckoutForm::create($this->owner, 'CouponForm', $this->checkoutconfig());
        $form->setActions(
            FieldList::create(FormAction::create('setcoupon', _t('SilverShop\Checkout\Step\CheckoutStep.Continue', 'Continue')))
        );
        $this->owner->extend('updateCouponForm', $form);

        return $form;
    }

    public function setcoupon($data, $form)
    {
        $this->checkoutconfig()->setData($form->getData());
        return $this->owner->redirect($this->NextStepLink());
    }
}
