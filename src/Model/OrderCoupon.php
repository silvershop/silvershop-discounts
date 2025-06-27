<?php

namespace SilverShop\Discounts\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\RandomGenerator;

/**
 * Applies a discount to current order, if applicable, when entered at checkout.
 *
 * @property ?string $Code
 * @property int $GiftVoucherID
 * @method   GiftVoucherOrderItem GiftVoucher()
 */
class OrderCoupon extends Discount
{
    private static array $db = [
        'Code' => 'Varchar(255)'
    ];

    private static array $has_one = [
        'GiftVoucher' => GiftVoucherOrderItem::class
    ];

    private static array $searchable_fields = [
        'Title',
        'Code'
    ];

    private static array $summary_fields = [
        'Title',
        'Code',
        'DiscountNice' => 'Discount',
        'StartDate',
        'EndDate'
    ];

    private static string $singular_name = 'Coupon';

    private static string $plural_name = 'Coupons';

    private static $minimum_code_length;

    private static int $generated_code_length = 10;

    private static string $table_name = 'SilverShop_OrderCoupon';

    public static function get_by_code($code)
    {
        return self::get()
            ->filter('Code:nocase', $code)
            ->first();
    }

    /**
     * Generates a unique code.
     *
     * @todo depending on the length, it may be possible that all the possible
     *       codes have been generated.
     */
    public static function generate_code(?int $length = null, string $prefix = ''): string
    {
        $length = $length !== null && $length !== 0 ? $length : self::config()->generated_code_length;
        $code = null;
        $randomGenerator = Injector::inst()->create(RandomGenerator::class);
        do {
            $code = $prefix . strtoupper(substr($randomGenerator->randomToken(), 0, $length));
        } while (self::get()->filter('Code:nocase', $code)->exists()
        );

        return $code;
    }

    public function getCMSFields($params = null)
    {
        $fieldList = parent::getCMSFields();
        $fieldList->addFieldsToTab(
            'Root.Main',
            [
                $codefield = TextField::create('Code')->setMaxLength(25),
            ],
            'Active'
        );
        if ($this->owner->Code && $codefield->exists()) {
            $fieldList->replaceField(
                'Code',
                $codefield->performReadonlyTransformation()
            );
        }

        return $fieldList;
    }

    public function validate(): ValidationResult
    {
        $validationResult = parent::validate();
        $minLength = self::config()->minimum_code_length;
        $code = $this->getField('Code');

        if ($minLength && $code && $this->isChanged('Code') && strlen((string) $code) < $minLength) {
            $validationResult->addError(
                _t(
                    'OrderCoupon.INVALIDMINLENGTH',
                    'Coupon code must be at least {length} characters in length',
                    ['length' => self::config()->minimum_code_length]
                ),
                ValidationResult::TYPE_ERROR,
                'INVALIDMINLENGTH'
            );
        }

        return $validationResult;
    }

    protected function onBeforeWrite(): void
    {
        if (empty($this->Code)) {
            $this->Code = self::generate_code();
        }

        parent::onBeforeWrite();
    }

    /**
     * Forces codes to be alpha-numeric, uppercase, and trimmed
     */
    public function setCode(string $code): static
    {
        if ($code !== '' && $code !== '0') {
            $code = trim((string) preg_replace('/[^0-9a-zA-Z]+/', '', $code));
            $this->setField('Code', strtoupper($code));
        }

        return $this;
    }

    public function canView($member = null): bool
    {
        return true;
    }

    public function canCreate($member = null, $context = []): bool
    {
        return true;
    }

    public function canDelete($member = null): bool
    {
        return $this->getUseCount() === 0;
    }

    public function canEdit($member = null): bool
    {
        return !($this->getUseCount() && !$this->Active);
    }
}
