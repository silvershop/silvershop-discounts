<?php

namespace SilverShop\Discounts\Form;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\Form;
use SilverShop\Model\Order;
use SilverShop\Checkout\CheckoutComponentConfig;
use SilverShop\Discounts\Checkout\CouponCheckoutComponent;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Control\Controller;
use SilverShop\Forms\CheckoutComponentValidator;

/**
 * Enter coupon codes at checkout.
 */
class CouponForm extends Form
{
    protected CheckoutComponentConfig $config;

    public function __construct(RequestHandler $requestHandler, $name, Order $order)
    {
        $this->config = new CheckoutComponentConfig($order, false);
        $this->config->addComponent($couponCheckoutComponent = new CouponCheckoutComponent());

        $checkoutComponentValidator = Injector::inst()->create(CheckoutComponentValidator::class, $this->config);

        $fieldList = $this->config->getFormFields();

        $actions = FieldList::create(FormAction::create('applyCoupon', _t('ApplyCoupon', 'Apply coupon')));

        parent::__construct($requestHandler, $name, $fieldList, $actions, $checkoutComponentValidator);

        $this->loadDataFrom($this->config->getData(), Form::MERGE_IGNORE_FALSEISH);

        $storeddata = $couponCheckoutComponent->getData($order);

        if (isset($storeddata['Code'])) {
            $actions->push(
                FormAction::create('removeCoupon', _t('RemoveCoupon', 'Remove coupon'))
            );
        }

        $order = $this->config->getOrder();

        $requestHandler->extend('updateCouponForm', $this, $order);
    }

    public function applyCoupon($data, $form): HTTPResponse
    {
        // form validation has passed by this point, so we can save data
        $this->config->setData($form->getData());

        return $this->controller->redirectBack();
    }

    public function removeCoupon($data, $form): HTTPResponse
    {
        Controller::curr()->getRequest()->getSession()->clear('cart.couponcode');

        $order = $this->config->getOrder();

        if ($order) {
            $order->removeDiscounts();
        }

        return $this->controller->redirectBack();
    }
}
