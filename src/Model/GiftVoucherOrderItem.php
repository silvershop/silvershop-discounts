<?php

namespace SilverShop\Discounts\Model;

use SilverStripe\ORM\HasManyList;
use Psr\Log\LoggerInterface;
use SilverStripe\ORM\ValidationException;
use SilverShop\Model\Product\OrderItem;
use SilverStripe\Control\Email\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * @property ?string $GiftedTo
 * @method   HasManyList<OrderCoupon> Coupons()
 */
class GiftVoucherOrderItem extends OrderItem
{
    /**
     * @var LoggerInterface
     */
    public $Logger;
    private static array $db = [
        'GiftedTo' => 'Varchar'
    ];

    private static array $has_many = [
        'Coupons' => OrderCoupon::class
    ];

    private static array $required_fields = [
        'UnitPrice'
    ];

    private static array $dependencies = [
        'Logger' => '%$' . LoggerInterface::class,
    ];

    private static string $table_name = 'SilverShop_GiftVoucherOrderItem';

    protected LoggerInterface $logger;

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
    public function onPayment(): void
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
     * @throws ValidationException
     */
    public function createCoupon(): OrderCoupon|bool
    {
        if (!$this->Product()) {
            return false;
        }

        $coupon = OrderCoupon::create(
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
    public function sendVoucher(OrderCoupon $coupon): bool
    {
        $from = Email::config()->admin_email;
        $to = $this->Order()->getLatestEmail();
        $subject = _t('Order.GIFTVOUCHERSUBJECT', 'Gift voucher');
        $email = Email::create();
        $email->setFrom($from);
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setHTMLTemplate('GiftVoucherEmail');
        $email->setData(
            [
                'Coupon' => $coupon
            ]
        );

        $this->extend('updateVoucherMail', $email, $coupon);

        try {
            $email->send();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('GiftVoucherOrderItem.sendVoucher: error sending email in ' . __FILE__ . ' line ' . __LINE__ . ": {$e->getMessage()}");
            return false;
        }

        return true;
    }
}
