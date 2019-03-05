<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverShop\Model\OrderItem;
use SilverShop\Page\ProductCategory;

class CategoriesDiscountConstraint extends ItemDiscountConstraint
{
    private static $many_many = [
        'Categories' => ProductCategory::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner->isInDB()) {
            $fields->addFieldToTab(
                'Root.Constraints.ConstraintsTabs.Product',
                GridField::create(
                    'Categories',
                    _t(__CLASS__.'.PRODUCTCATEGORIES', 'Product categories'),
                    $this->owner->Categories(),
                    GridFieldConfig_RelationEditor::create()
                        ->removeComponentsByType(GridFieldAddNewButton::class)
                        ->removeComponentsByType(GridFieldEditButton::class)
                )
            );
        }
    }

    public function check(Discount $discount)
    {
        $categories = $discount->Categories();

        if (!$categories->exists()) {
            return true;
        }

        $incart = $this->itemsInCart($discount);

        if (!$incart) {
            $this->error(_t(__CLASS__.'.CATEGORIESNOTINCART', 'The required products (categories) are not in the cart.'));
        }

        return $incart;
    }

    public function itemMatchesCriteria(OrderItem $item, Discount $discount)
    {
        $discountcategoryids = $discount->Categories()->getIDList();
        if (empty($discountcategoryids)) {
            return true;
        }
        //get category ids from buyable
        $buyable = $item->Buyable();
        if (!method_exists($buyable, 'getCategoryIDs')) {
            return false;
        }
        $ids = array_intersect(
            $buyable->getCategoryIDs(),
            $discountcategoryids
        );

        return !empty($ids);
    }
}
