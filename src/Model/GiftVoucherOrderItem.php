<?php

namespace SilverShop\Discounts\Model;

use SilverShop\Model\Product\OrderItem;
use SilverStripe\Control\Email\Email;

class GiftVoucherOrderItem extends OrderItem
{
    private static $db = [
        'GiftedTo' => 'Varchar'
    ];

    private static $has_many = [
        'Coupons' => OrderCoupon::class
    ];

    private static $required_fields = [
        'UnitPrice'
    ];

    private static $table_name = 'SilverShop_GiftVoucherOrderItem';

    /**
     * Don't get unit price from product
     */
    public function UnitPrice()
    {
        if ($this->Product()->VariableAmount) {
            return $this->UnitPrice;
        }

        return parent::UnitPrice();
    }

    /**
     * Create vouchers on order payment success event
     */
    public function onPayment()
    {
        parent::onPayment();

        if ($this->Coupons()->Count() < $this->Quantity) {
            $remaining = $this->Quantity - $this->Coupons()->Count();

            for ($i = 0; $i < $remaining; $i++) {
                if ($coupon = $this->createCoupon()) {
                    $this->sendVoucher($coupon);
                }
            }
        }
    }

    /**
     * Create a new coupon
     *
     * @return OrderCoupon
     */
    public function createCoupon()
    {
        if (!$this->Product()) {
            return false;
        }

        $coupon = new OrderCoupon(
            [
            'Title' => $this->Product()->Title,
            'Type' => 'Amount',
            'Amount' => $this->UnitPrice,
            'UseLimit' => 1,
            'MinOrderValue' => $this->UnitPrice //safeguard that means coupons must be used entirely
            ]
        );

        $this->extend('updateCreateCupon', $coupon);

        $coupon->write();

        $this->Coupons()->add($coupon);

        return $coupon;
    }

    /*
     * Send the voucher to the appropriate email
     */
    public function sendVoucher(OrderCoupon $coupon)
    {
        $from = Email::getAdminEmail();
        $to = $this->Order()->getLatestEmail();
        $subject = _t('Order.GIFTVOUCHERSUBJECT', 'Gift voucher');
        $email = Email::create();
        $email->setFrom($from);
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setTemplate('GiftVoucherEmail');
        $email->setData(
            [
            'Coupon' => $coupon
            ]
        );

        $this->extend('updateVoucherMail', $email, $coupon);

        return $email->send();
    }
}
