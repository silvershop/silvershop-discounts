<?php

namespace SilverShop\Discounts\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
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
        'TimesUsed' => 'Used',
        'StartDate',
        'EndDate'
    ];

    private static string $singular_name = 'Coupon';

    private static string $plural_name = 'Coupons';

    private static ?int $minimum_code_length = null;

    private static int $generated_code_length = 10;

    private static string $table_name = 'SilverShop_OrderCoupon';

    public static function get_by_code(string $code): ?self
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        // a child code (issue #31) takes precedence
        $discountCode = DiscountCode::get_by_code($code);

        if ($discountCode instanceof DiscountCode && ($owner = $discountCode->Discount()) instanceof self) {
            return $owner;
        }

        // a coupon's own (legacy) single code - only when that coupon has no child codes, which
        // otherwise supersede and hide it
        $coupon = self::get()->filter('Code:nocase', $code)->first();

        if ($coupon instanceof self && !$coupon->Codes()->exists()) {
            return $coupon;
        }

        return null;
    }

    /**
     * Generates a unique code.
     *
     * @todo depending on the length, it may be possible that all the possible
     *       codes have been generated.
     */
    public static function generate_code(?int $length = null, string $prefix = ''): string
    {
        $length = $length !== null && $length !== 0 ? $length : (int) self::config()->get('generated_code_length');
        $code = null;
        $randomGenerator = Injector::inst()->create(RandomGenerator::class);
        do {
            $code = $prefix . strtoupper(substr($randomGenerator->randomToken(), 0, $length));
        } while (self::get()->filter('Code:nocase', $code)->exists()
        );

        return $code;
    }

    /** @param array<string, mixed>|null $params */
    public function getCMSFields($params = null): FieldList
    {
        $fieldList = parent::getCMSFields();

        // When the coupon has its own codes (issue #31) the single legacy Code is ignored, so hide it
        // and point to the Codes tab rather than showing a confusing extra code.
        if ($this->hasChildCodes()) {
            $fieldList->addFieldToTab(
                'Root.Main',
                LiteralField::create(
                    'CodeHidden',
                    '<p class="message notice" style="display:flex;align-items:flex-start;gap:.5em">'
                    . '<span class="font-icon-info-circled" aria-hidden="true"></span><span>'
                    . _t(__CLASS__ . '.CODEHIDDENNOTE', 'This coupon uses the codes on the Codes tab.')
                    . '</span></p>'
                )
            );

            return $fieldList;
        }

        $fieldList->addFieldsToTab(
            'Root.Main',
            [
                $codefield = TextField::create('Code', _t(__CLASS__ . '.CODE', 'Code'))->setMaxLength(25),
            ],
            'Active'
        );
        if ($this->Code && $codefield->exists()) {
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
        $minLength = self::config()->get('minimum_code_length');
        $code = $this->getField('Code');

        if ($minLength && $code && $this->isChanged('Code') && strlen((string) $code) < $minLength) {
            $validationResult->addError(
                _t(
                    'OrderCoupon.INVALIDMINLENGTH',
                    'Coupon code must be at least {length} characters in length',
                    ['length' => self::config()->get('minimum_code_length')]
                ),
                ValidationResult::TYPE_ERROR,
                'INVALIDMINLENGTH'
            );
        }

        return $validationResult;
    }

    protected function onBeforeWrite(): void
    {
        // Auto-generate a code only when the coupon has no codes at all. A coupon with child codes
        // (issue #31) is gated by those, so it needs no - and ignores - a legacy single Code.
        if (empty($this->Code) && !$this->hasChildCodes()) {
            $this->Code = self::generate_code();
        }

        parent::onBeforeWrite();
    }

    protected function hasChildCodes(): bool
    {
        return $this->isInDB() && $this->Codes()->exists();
    }

    /**
     * Code shown in the admin grid. For a single-code coupon that is the legacy Code; for a
     * multi-code coupon (issue #31) the single Code is empty, so show the first couple of child
     * codes with an ellipsis when there are more.
     */
    public function getCodeSummary(): string
    {
        if (!$this->hasChildCodes()) {
            return (string) $this->Code;
        }

        $codes = $this->Codes()->sort('Code ASC')->limit(3)->column('Code');
        $shown = implode(', ', array_slice($codes, 0, 2));

        return count($codes) > 2 ? $shown . ', …' : $shown;
    }

    /**
     * Forces codes to be alpha-numeric, uppercase, and trimmed
     */
    public function setCode(?string $code): static
    {
        if ($code !== null && $code !== '' && $code !== '0') {
            // uppercase alphanumeric, plus the hyphen (a common coupon separator, e.g. SUMMER-2026)
            $code = trim((string) preg_replace('/[^0-9a-zA-Z-]+/', '', $code));
            $this->setField('Code', strtoupper($code));
        }

        return $this;
    }

    public function canView($member = null): bool
    {
        if (parent::canView($member)) {
            return true;
        }

        $memberArg = $member instanceof Member ? $member : 0;

        return (bool) Permission::checkMember($memberArg, 'MANAGE_DISCOUNTS');
    }

    /** @param array<string, mixed> $context */
    public function canCreate($member = null, $context = []): bool
    {
        if (parent::canCreate($member, $context)) {
            return true;
        }

        $memberArg = $member instanceof Member ? $member : 0;

        return (bool) Permission::checkMember($memberArg, 'MANAGE_DISCOUNTS');
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
