<?php

namespace SilverShop\Discounts\Form;

use SilverStripe\Control\RequestHandler;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\Form;
use SilverShop\Model\Order;
use SilverShop\Checkout\CheckoutComponentConfig;
use SilverShop\Discounts\Checkout\AppliedCouponCodes;
use SilverShop\Discounts\Checkout\CouponCheckoutComponent;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverShop\Forms\CheckoutComponentValidator;

/**
 * Enter coupon codes at checkout.
 */
class CouponForm extends Form
{
    protected CheckoutComponentConfig $config;

    public function __construct(RequestHandler $requestHandler, $name, Order $order)
    {
        $this->config = CheckoutComponentConfig::create($order, false);
        $this->config->addComponent($couponCheckoutComponent = CouponCheckoutComponent::create());

        $checkoutComponentValidator = Injector::inst()->create(CheckoutComponentValidator::class, $this->config);

        $fieldList = $this->config->getFormFields();

        $actions = FieldList::create(FormAction::create('applyCoupon', _t('ApplyCoupon', 'Apply coupon')));

        parent::__construct($requestHandler, $name, $fieldList, $actions, $checkoutComponentValidator);

        $this->loadDataFrom($this->config->getData(), Form::MERGE_IGNORE_FALSEISH);

        $storeddata = $couponCheckoutComponent->getData($order);

        if (!empty($storeddata['Codes'])) {
            if (count($storeddata['Codes']) > 1) {
                // let the customer pick which of the stacked coupons to remove
                $fieldList->push(
                    CheckboxSetField::create(
                        'RemoveCodes',
                        _t(__CLASS__ . '.AppliedCoupons', 'Applied coupons'),
                        array_combine($storeddata['Codes'], $storeddata['Codes'])
                    )->setDescription(
                        _t(
                            __CLASS__ . '.RemoveCodesDescription',
                            'Select coupons to remove, or leave blank to remove all.'
                        )
                    )
                );
            }

            $actions->push(
                FormAction::create('removeCoupon', _t('RemoveCoupon', 'Remove coupon'))
            );

            $this->setValidationExemptActions(['removeCoupon']);
        }

        $order = $this->config->getOrder();

        $requestHandler->extend('updateCouponForm', $this, $order);
    }

    /** @param array<string, mixed> $data */
    public function applyCoupon(array $data, Form $form): HTTPResponse
    {
        // form validation has passed by this point, so we can save data
        $this->config->setData($form->getData());

        return $this->controller->redirectBack();
    }

    /**
     * Remove the coupons listed in `RemoveCodes`, or all coupons when none
     * are listed.
     *
     * @param array<string, mixed> $data
     */
    public function removeCoupon(array $data, Form $form): HTTPResponse
    {
        $remove = $data['RemoveCodes'] ?? [];
        $remove = is_array($remove) ? AppliedCouponCodes::normalise($remove) : [];

        if ($remove === []) {
            AppliedCouponCodes::clear();
        } else {
            foreach ($remove as $code) {
                AppliedCouponCodes::remove($code);
            }
        }

        $order = $this->config->getOrder();

        if ($order->exists()) {
            $order->removeDiscounts();
        }

        return $this->controller->redirectBack();
    }

    /**
     * Coupons currently applied to the cart, for use in templates.
     *
     * @return ArrayList<ArrayData>
     */
    public function AppliedCoupons(): ArrayList
    {
        $list = ArrayList::create();

        foreach (AppliedCouponCodes::get() as $code) {
            $coupon = OrderCoupon::get_by_code($code);

            $list->push(ArrayData::create([
                'Code' => $code,
                'Title' => $coupon ? $coupon->Title : $code,
                'Coupon' => $coupon
            ]));
        }

        return $list;
    }
}
