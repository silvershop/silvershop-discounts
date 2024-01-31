<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\ORM\DataList;

class DatetimeDiscountConstraint extends DiscountConstraint
{
    private static $db = [
        'StartDate' => 'Datetime',
        'EndDate' => 'Datetime'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab(
            'Root.Constraints.ConstraintsTabs.General',
            FieldGroup::create(
                _t(__CLASS__.'.VALIDDATERANGE', 'Valid date range:'),
                DatetimeField::create(
                    'StartDate',
                    _t(__CLASS__.'.RANGESTART', 'Start date/time')
                ),
                DatetimeField::create(
                    'EndDate',
                    _t(__CLASS__.'.RANGEEND', 'End date/time')
                )
            )->setDescription(
                _t(__CLASS__.'.ENDTIMEDAYNOTE',
                    'You should set the end time to 23:59:59, if you want to include the entire end day.')
            )
        );
    }

    public function filter(DataList $list)
    {
        // Check whether we are looking at a historic order or a current one
        $datetime = $this->order->Placed ? $this->order->Created : date('Y-m-d H:i:s');

        //to bad ORM filtering for NULL doesn't work...so we need to use where
        return $list->where(
            "(\"SilverShop_Discount\".\"StartDate\" IS NULL) OR (\"SilverShop_Discount\".\"StartDate\" < '$datetime')"
        )
            ->where(
                "(\"SilverShop_Discount\".\"EndDate\" IS NULL) OR (\"SilverShop_Discount\".\"EndDate\" > '$datetime')"
            );
    }

    public function check(Discount $discount)
    {
        $startDate = null;
        $endDate = null;

        if($discount->StartDate != null)
        {
            $startDate = strtotime($discount->StartDate);
        }
        
        if($discount->EndDate != null)
        {
            $endDate = strtotime($discount->EndDate);
        }

        // Adjust the time to the when the order was placed or the current time non completed orders
        $now = $this->order->Placed ? strtotime($this->order->Created) : time();

        if ($discount->EndDate) {
            $endDate = strtotime($discount->EndDate);
            if ($endDate && $endDate < $now) {
                $this->error(_t('OrderCoupon.EXPIRED', 'This coupon has already expired.'));
                return false;
            }
        }

        if ($discount->StartDate) {
            $startDate = strtotime($discount->StartDate);
            if ($startDate && $startDate > $now) {
                $this->error(_t('OrderCoupon.TOOEARLY', 'It is too early to use this coupon.'));
                return false;
            }
        }

        return true;
    }
}
