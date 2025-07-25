<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverShop\Model\OrderItem;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\ListboxField;

/**
 * @property ?string $ProductTypes
 */
class ProductTypeDiscountConstraint extends ItemDiscountConstraint
{
    private static array $db = [
        'ProductTypes' => 'Text'
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        //multiselect subtypes of orderitem
        if ($this->owner->isInDB() && $this->owner->ForItems) {
            $fieldList->addFieldsToTab(
                'Root.Constraints.ConstraintsTabs.Product',
                [
                    HeaderField::create(
                        'ProductTypesHeading',
                        _t(__CLASS__ . '.PRODUCTTYPES', 'Product types'),
                        2
                    )->addExtraClass('grid-field__title'),
                    ListBoxField::create(
                        'ProductTypes',
                        '',
                        $this->getTypes(false, $this->owner) ?? []
                    )
                ]
            );
        }
    }

    public function check(Discount $discount): bool
    {
        $types = $this->getTypes(true, $discount);
        //valid if no categories defined
        if ($types === null || $types === []) {
            return true;
        }

        $incart = $this->itemsInCart($discount);
        if (!$incart) {
            $this->error(_t(__CLASS__ . '.PRODUCTTYPESNOTINCART', 'The required product type(s), are not in the cart.'));
        }

        return $incart;
    }

    /**
     * This function is used by ItemDiscountAction, and the check function above.
     */
    public function itemMatchesCriteria(OrderItem $orderItem, Discount $discount): bool
    {
        $types = $this->getTypes(true, $discount);
        if ($types === null || $types === []) {
            return true;
        }

        $buyable = $orderItem->Buyable();
        return isset($types[$buyable->getClassName()]);
    }

    protected function getTypes($selected, Discount $discount): ?array
    {
        $types = $selected ? array_filter(explode(',', $discount->ProductTypes ?? '')) : $this->BuyableClasses();
        if ($types && $types !== []) {
            $types = array_combine($types, $types);
            foreach (array_keys($types) as $type) {
                $types[$type] = singleton($type)->i18n_singular_name();
            }

            return $types;
        }

        return null;
    }

    protected function BuyableClasses(): array
    {
        $implementors = ClassInfo::implementorsOf('Buyable');
        $classes = [];
        foreach ($implementors as $implementor) {
            $classes = array_merge($classes, array_values(ClassInfo::subclassesFor($implementor)));
        }

        $classes = array_combine($classes, $classes);

        if (array_key_exists('ProductVariation', $classes)) {
            unset($classes['ProductVariation']);
        }

        return $classes;
    }
}
