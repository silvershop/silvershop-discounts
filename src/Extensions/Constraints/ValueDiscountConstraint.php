<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\ORM\DataList;

/**
 * @property float $MinOrderValue
 * @property bool $MinOrderValueTaxInclusive
 */
class ValueDiscountConstraint extends DiscountConstraint
{
    private static array $db = [
        'MinOrderValue' => 'Currency',
        'MinOrderValueTaxInclusive' => 'Boolean',
    ];

    private static array $defaults = [
        'MinOrderValueTaxInclusive' => false,
    ];

    private static array $field_labels = [
        'MinOrderValue' => 'Minimum subtotal of order',
        'MinOrderValueTaxInclusive' => 'Tax-inclusive minimum',
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        $owner = $this->getOwner();
        $label = $owner->fieldLabel('MinOrderValue');
        $fieldList->addFieldToTab(
            'Root.Constraints.ConstraintsTabs.General',
            CurrencyField::create(
                'MinOrderValue',
                _t(__CLASS__ . '.MINORDERVALUE', $label)
            )
        );
        $fieldList->addFieldToTab(
            'Root.Constraints.ConstraintsTabs.General',
            CheckboxField::create(
                'MinOrderValueTaxInclusive',
                _t(__CLASS__ . '.MINORDERVALUE_TAX_INCLUSIVE', 'Apply minimum to tax-inclusive subtotal')
            )->setDescription(
                _t(
                    __CLASS__ . '.MINORDERVALUE_TAX_INCLUSIVE_HELP',
                    'When enabled, the minimum is compared to the cart line subtotal plus any amount added by ' .
                    'an Order extension hook (updateMinimumOrderValueComparisonAmount). Use that hook to add tax ' .
                    'or other components so the threshold matches how you price products.'
                )
            )
        );
    }

    public function filter(DataList $dataList): DataList
    {
        $exclusive = (float) $this->order->SubTotal();
        $inclusive = $this->buildTaxInclusiveComparisonAmount($exclusive);

        $ids = array_values(array_unique(array_merge(
            $dataList->filter('MinOrderValue', 0)->column('ID'),
            $dataList->filter([
                'MinOrderValue:GreaterThan' => 0,
                'MinOrderValue:LessThanOrEqual' => $exclusive,
                'MinOrderValueTaxInclusive' => false,
            ])->column('ID'),
            $dataList->filter([
                'MinOrderValue:GreaterThan' => 0,
                'MinOrderValue:LessThanOrEqual' => $inclusive,
                'MinOrderValueTaxInclusive' => true,
            ])->column('ID'),
        )));

        if ($ids === []) {
            return $dataList->where('0 = 1');
        }

        return $dataList->filter('ID', $ids);
    }

    public function check(Discount $discount): bool
    {
        $exclusive = (float) $this->order->SubTotal();
        $compare = $discount->MinOrderValueTaxInclusive
            ? $this->buildTaxInclusiveComparisonAmount($exclusive)
            : $exclusive;

        if ($discount->MinOrderValue > 0 && $compare < $discount->MinOrderValue) {
            $this->error(
                sprintf(
                    $discount->MinOrderValueTaxInclusive
                        ? _t(
                            __CLASS__ . '.MINORDERVALUE_INCLUSIVE',
                            'Your cart subtotal (including tax) must be at least %s to use this discount'
                        )
                        : _t(
                            'Discount.MINORDERVALUE',
                            'Your cart subtotal must be at least %s to use this discount'
                        ),
                    $discount->dbObject('MinOrderValue')->Nice()
                )
            );
            return false;
        }

        return true;
    }

    /**
     * Starts from the line-item subtotal and lets {@link Order} extensions add tax (or other) amounts
     * when evaluating tax-inclusive minimum order rules.
     */
    protected function buildTaxInclusiveComparisonAmount(float $lineSubtotal): float
    {
        $context = new \stdClass();
        $context->amount = $lineSubtotal;
        $this->order->extend('updateMinimumOrderValueComparisonAmount', $context);

        return (float) $context->amount;
    }
}
