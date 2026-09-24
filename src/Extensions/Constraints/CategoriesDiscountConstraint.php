<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverStripe\ORM\ManyManyList;
use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverShop\Model\OrderItem;
use SilverShop\Page\ProductCategory;

/**
 * @method ManyManyList<ProductCategory> Categories()
 */
class CategoriesDiscountConstraint extends ItemDiscountConstraint
{
    private static array $many_many = [
        'Categories' => ProductCategory::class
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        $owner = $this->getOwner();
        if (!$owner->isInDB()) {
            return;
        }

        $fieldList->addFieldToTab(
            'Root.Constraints.ConstraintsTabs.Product',
            GridField::create(
                'Categories',
                _t(__CLASS__ . '.PRODUCTCATEGORIES', 'Product categories'),
                $owner->Categories(),
                GridFieldConfig_RelationEditor::create()
                    ->removeComponentsByType(GridFieldAddNewButton::class)
                    ->removeComponentsByType(GridFieldEditButton::class)
            )
        );
    }

    public function check(Discount $discount): bool
    {
        if (!$this->getRelationIDs($discount, 'Categories')) {
            return true;
        }

        $incart = $this->itemsInCart($discount);

        if (!$incart) {
            $this->error(_t(__CLASS__ . '.CATEGORIESNOTINCART', 'The required products (categories) are not in the cart.'));
        }

        return $incart;
    }

    public function itemMatchesCriteria(OrderItem $orderItem, Discount $discount): bool
    {
        $discountcategoryids = $this->getRelationIDs($discount, 'Categories');
        if (empty($discountcategoryids)) {
            return true;
        }

        //get category ids from buyable
        $buyable = $orderItem->Buyable();
        if (!is_object($buyable) || !method_exists($buyable, 'getCategoryIDs')) {
            return false;
        }

        $ids = array_intersect(
            $buyable->getCategoryIDs(),
            $discountcategoryids
        );

        return $ids !== [];
    }
}
