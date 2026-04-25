<?php

namespace SilverShop\Discounts\Checkout\Step;

use SilverShop\Checkout\Step\CheckoutStep;
use SilverShop\Checkout\CheckoutComponentConfig;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Discounts\Checkout\CouponCheckoutComponent;
use SilverShop\Forms\CheckoutForm;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
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

    /** @return array{OrderForm: CheckoutForm} */
    public function discount(): array
    {
        return [
            'OrderForm' => $this->CouponForm()
        ];
    }

    public function CouponForm(): CheckoutForm
    {
        $owner = $this->getOwner();
        $checkoutForm = CheckoutForm::create($owner, 'CouponForm', $this->checkoutconfig());
        $checkoutForm->setActions(
            FieldList::create(FormAction::create('setcoupon', _t('SilverShop\Checkout\Step\CheckoutStep.Continue', 'Continue')))
        );
        if (method_exists($owner, 'extend')) {
            $owner->extend('updateCouponForm', $checkoutForm);
        }

        return $checkoutForm;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setcoupon(array $data, Form $form): HTTPResponse
    {
        $this->checkoutconfig()->setData($form->getData());
        $owner = $this->getOwner();
        if (method_exists($owner, 'redirect')) {
            return $owner->redirect($this->NextStepLink());
        }

        return HTTPResponse::create();
    }
}
