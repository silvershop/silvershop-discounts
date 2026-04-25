<?php

namespace SilverShop\Discounts\Admin;

use SilverShop\Reports\ShopPeriodReport;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Reports\ShopReportQuery;
use SilverStripe\ORM\Queries\SQLSelect;

class DiscountReport extends ShopPeriodReport
{
    /** @var string */
    protected $title = 'Discounts';

    /** @var string */
    protected $dataClass = Discount::class;

    /** @var string */
    protected $periodfield = '"SilverShop_Order"."Paid"';

    /** @var string */
    protected $description = "See the total savings for discounts. Note that the 'Entered' field may not be
										accurate if old/expired carts have been deleted from the database.";

    /** @return array<string, class-string|non-empty-string> */
    public function columns(): array
    {
        return [
            'Name' => 'Title',
            'Code' => 'Code',
            'DiscountNice' => Discount::class,
            'Entered' => 'Entered',
            'UseCount' => 'Uses',
            'SavingsTotal' => 'Total Savings'
        ];
    }

    /**
     * Remove unsortable columns
     */
    /** @return list<string> */
    public function sortColumns(): array
    {
        $cols = $this->columns();
        unset($cols['DiscountNice']);
        return array_keys($cols);
    }

    /** @param array<string, mixed> $params */
    public function query(array $params): ShopReportQuery|SQLSelect
    {
        $query = parent::query($params);
        $query->addSelect('"SilverShop_Discount".*')
            ->selectField('"Title"', 'Name')
            ->selectField('COUNT(DISTINCT "SilverShop_Order"."ID")', 'Entered')
            ->addLeftJoin(
                'SilverShop_OrderItem_Discounts',
                '"SilverShop_OrderItem_Discounts"."SilverShop_DiscountID" = "SilverShop_Discount"."ID"'
            )
            ->addLeftJoin(
                'SilverShop_OrderDiscountModifier_Discounts',
                '"SilverShop_OrderDiscountModifier_Discounts"."SilverShop_DiscountID" = "SilverShop_Discount"."ID"'
            )
            ->addInnerJoin(
                'SilverShop_OrderAttribute',
                (implode(
                    ' OR ',
                    [
                        '"SilverShop_OrderItem_Discounts"."SilverShop_OrderItemID" = "SilverShop_OrderAttribute"."ID"',
                        '"SilverShop_OrderDiscountModifier_Discounts"."SilverShop_OrderDiscountModifierID" = "SilverShop_OrderAttribute"."ID"'
                    ]
                ))
            )
            ->addInnerJoin('SilverShop_Order', '"SilverShop_OrderAttribute"."OrderID" = "SilverShop_Order"."ID"');
        $query->setGroupBy('"SilverShop_Discount"."ID"');
        $query->setLimit('50');

        return $query;
    }
}
