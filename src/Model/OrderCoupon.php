<?php

namespace SilverShop\Discounts\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\RandomGenerator;
use SilverStripe\ORM\ValidationResult;

/**
 * Applies a discount to current order, if applicable, when entered at checkout.
 */
class OrderCoupon extends Discount
{
    private static $db = [
        'Code' => 'Varchar(255)'
    ];

    private static $has_one = [
        'GiftVoucher' => GiftVoucherOrderItem::class
    ];

    private static $searchable_fields = [
        'Title',
        'Code'
    ];

    private static $summary_fields = [
        'Title',
        'Code',
        'DiscountNice' => 'Discount',
        'StartDate',
        'EndDate'
    ];

    private static $singular_name = 'Coupon';

    private static $plural_name = 'Coupons';

    private static $minimum_code_length = null;

    private static $generated_code_length = 10;

    private static $table_name = 'SilverShop_OrderCoupon';

    public static function get_by_code($code)
    {
        return self::get()
            ->filter('Code:nocase', $code)
            ->first();
    }

    /**
     * Generates a unique code.
     *
     * @todo   depending on the length, it may be possible that all the possible
     *       codes have been generated.
     * @return string the new code
     */
    public static function generate_code($length = null, $prefix = '')
    {
        $length = $length ? $length : self::config()->generated_code_length;
        $code = null;
        $generator = Injector::inst()->create(RandomGenerator::class);
        do {
            $code = $prefix.strtoupper(substr($generator->randomToken(), 0, $length));
        } while (self::get()->filter('Code:nocase', $code)->exists()
        );

        return $code;
    }

    public function getCMSFields($params = null)
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.Main',
            [
                $codefield = TextField::create('Code')->setMaxLength(25),
            ],
            'Active'
        );
        if ($this->owner->Code && $codefield) {
            $fields->replaceField(
                'Code',
                $codefield->performReadonlyTransformation()
            );
        }

        return $fields;
    }

    public function validate()
    {
        $result = parent::validate();
        $minLength = $this->config()->minimum_code_length;
        $code = $this->getField('Code');

        if ($minLength && $code && $this->isChanged('Code') && strlen($code) < $minLength) {
            $result->addError(
                _t(
                    'OrderCoupon.INVALIDMINLENGTH',
                    'Coupon code must be at least {length} characters in length',
                    ['length' => $this->config()->minimum_code_length]
                ),
                ValidationResult::TYPE_ERROR,
                'INVALIDMINLENGTH'
            );
        }

        return $result;
    }

    protected function onBeforeWrite()
    {
        if (empty($this->Code)) {
            $this->Code = self::generate_code();
        }

        parent::onBeforeWrite();
    }

    /**
     * Forces codes to be alpha-numeric, uppercase, and trimmed
     *
     * @param string
     *
     * @return $this
     */
    public function setCode($code)
    {
        $code = trim(preg_replace('/[^0-9a-zA-Z]+/', '', $code));
        $this->setField('Code', strtoupper($code));

        return $this;
    }

    public function canView($member = null)
    {
        return true;
    }

    public function canCreate($member = null, $context = [])
    {
        return true;
    }

    public function canDelete($member = null)
    {
        if ($this->getUseCount()) {
            return false;
        }

        return true;
    }

    public function canEdit($member = null)
    {
        if ($this->getUseCount() && !$this->Active) {
            return false;
        }

        return true;
    }
}
