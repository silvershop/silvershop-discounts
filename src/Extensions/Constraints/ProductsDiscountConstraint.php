<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverStripe\ORM\ManyManyList;
use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Versioned\Versioned;
use SilverShop\Model\OrderItem;
use SilverShop\Page\Product;

/**
 * @property bool $ExactProducts
 * @method ManyManyList<Product> Products()
 */
class ProductsDiscountConstraint extends ItemDiscountConstraint
{
    private static array $db = [
        'ExactProducts' => 'Boolean'
    ];

    private static array $many_many = [
        'Products' => Product::class
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        if ($this->owner->isInDB()) {
            $fieldList->addFieldsToTab(
                'Root.Constraints.ConstraintsTabs.Product',
                [
                    GridField::create(
                        'Products',
                        _t(__CLASS__ . 'SPECIFICPRODUCTS', 'Specific products'),
                        $this->owner->Products(),
                        GridFieldConfig_RelationEditor::create()
                            ->removeComponentsByType(GridFieldAddNewButton::class)
                            ->removeComponentsByType(GridFieldEditButton::class)
                    ),
                    CheckboxField::create(
                        'ExactProducts',
                        _t(__CLASS__ . '.ALLPRODUCTSINCART', 'All the selected products must be present in cart.')
                    ),
                ]
            );
        }
    }

    public function check(Discount $discount): bool
    {
        $products = $discount->Products();
        $productIds = [];

        if (!$products->exists()) {
            Versioned::withVersionedMode(
                function () use ($discount, &$productIds): void {
                    Versioned::set_stage(Versioned::DRAFT);

                    $manyManyList = $discount->Products();

                    if ($manyManyList->exists()) {
                        $productIds = $manyManyList->map('ID', 'ID')->toArray();
                    }
                }
            );
        } else {
            $productIds = $products->map('ID', 'ID')->toArray();
        }

        if (!$productIds) {
            return true;
        }

        // uses 'DiscountedProductID' so that subclasses of projects (say a custom nested set of products) can define the
        // underlying DiscountedProductID.
        $cartproductids = $this->order->Items()->map(
            'ProductID',
            'DiscountedProductID'
        )->toArray();

        $intersection = array_intersect($productIds, $cartproductids);

        $incart = $discount->ExactProducts ?
            array_values($productIds) === array_values($intersection) :
            $intersection !== [];

        if (!$incart) {
            $this->error(
                _t('ProductsDiscountConstraint.MISSINGPRODUCT', 'The required products are not in the cart.')
            );
        }

        return $incart;
    }

    public function itemMatchesCriteria(OrderItem $orderItem, Discount $discount): bool
    {
        $manyManyList = $discount->Products();
        $itemproduct = $orderItem->Product(true); // true forces the current version of product to be retrieved.

        if ($manyManyList->exists()) {
            foreach ($manyManyList as $product) {
                // uses 'DiscountedProductID' since some subclasses of buyable could be used as the item product (such as
                // a bundle) rather than the product stored.
                if ($product->ID == $itemproduct->DiscountedProductID) {
                    return true;
                }
            }

            $this->error(
                _t('ProductsDiscountConstraint.MISSINGPRODUCT', 'The required products are not in the cart.')
            );

            return false;
        }

        return true;
    }
}
