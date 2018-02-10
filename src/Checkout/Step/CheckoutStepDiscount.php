<?php

namespace SilverShop\Discounts\Checkout\Step;

use SilverShop\Checkout\Step\CheckoutStep;
use CheckoutComponentConfig;
use SilverShop\Cart\ShoppingCart;
use CouponCheckoutComponent;
use CheckoutForm;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverShop\Discounts\Form\CouponForm;



class CheckoutStepDiscount extends CheckoutStep
{
    private static $allowed_actions = [
        'discount',
        'CouponForm',
        'setcoupon'
    ];

    protected function checkoutconfig()
    {
        $config = new CheckoutComponentConfig(ShoppingCart::curr(), true);
        $config->addComponent($comp = new CouponCheckoutComponent());
        $comp->setValidWhenBlank(true);

        return $config;
    }

    public function discount()
    {
        return [
            'OrderForm' => $this->CouponForm()
        ];
    }

    public function CouponForm()
    {
        $form = new CheckoutForm($this->owner, CouponForm::class, $this->checkoutconfig());
        $form->setActions(new FieldList(
            FormAction::create("setcoupon", "Continue")
        ));
        $this->owner->extend('updateCouponForm', $form);

        return $form;
    }

    public function setcoupon($data, $form)
    {
        $this->checkoutconfig()->setData($form->getData());
        return $this->owner->redirect($this->NextStepLink());
    }
}
