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
    private static array $db = [
        'GiftedTo' => 'Varchar'
    ];

    private static array $has_many = [
        'Coupons' => OrderCoupon::class
    ];

    private static array $required_fields = [
        'UnitPrice'
    ];

    private static string $table_name = 'SilverShop_GiftVoucherOrderItem';

    private static array $dependencies = [
        'Logger' => '%$' . LoggerInterface::class,
    ];

    /**
     * @var LoggerInterface
     */
    protected $logger;

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

        $orderCoupon = OrderCoupon::create(
            [
            'Title' => $this->Product()->Title,
            'Type' => 'Amount',
            'Amount' => $this->UnitPrice,
            'UseLimit' => 1,
            'MinOrderValue' => $this->UnitPrice //safeguard that means coupons must be used entirely
            ]
        );

        $this->extend('updateCreateCupon', $orderCoupon);

        $orderCoupon->write();

        $this->Coupons()->add($orderCoupon);

        return $orderCoupon;
    }

    /*
     * Send the voucher to the appropriate email
     */
    public function sendVoucher(OrderCoupon $orderCoupon): bool
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
                'Coupon' => $orderCoupon
            ]
        );

        $this->extend('updateVoucherMail', $email, $orderCoupon);

        try {
            $email->send();
        } catch (TransportExceptionInterface $transportException) {
            $this->logger->error('GiftVoucherOrderItem.sendVoucher: error sending email in ' . __FILE__ . ' line ' . __LINE__ . (': ' . $transportException->getMessage()));
            return false;
        }

        return true;
    }

    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;
        return $this;
    }
}
