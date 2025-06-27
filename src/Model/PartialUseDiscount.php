<?php

namespace SilverShop\Discounts\Model;

use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;

/**
 * @property int $ChildID
 * @method   \SilverShop\Discounts\Model\PartialUseDiscount Child()
 * @property int $ParentID
 * @method   \SilverShop\Discounts\Model\PartialUseDiscount Parent()
 */
class PartialUseDiscount extends Discount
{
    private static array $has_one = [
        'Parent' => PartialUseDiscount::class
    ];

    private static array $belongs_to = [
        'Child' => PartialUseDiscount::class
    ];

    private static array $defaults = [
        'Type' => 'Amount',
        'ForCart' => 1,
        'ForItems' => 0,
        'ForShipping' => 0,
        'UseLimit' => 1
    ];

    private static string $singular_name = 'Partial Use Discount';

    private static string $plural_name = 'Partial Use Discounts';

    private static string $table_name = 'SilverShop_PartialUseDiscount';

    public function getCMSFields($params = null)
    {
        $fieldList = parent::getCMSFields(['forcetype' => 'Amount']);

        $fieldList->removeByName(
            [
                'ForCart',
                'ForItems',
                'ForShipping',
                'For'
            ]
        );

        $limitfield = $fieldList->dataFieldByName('UseLimit');

        $fieldList->replaceField('UseLimit', $limitfield->performReadonlyTransformation());
        return $fieldList;
    }

    /**
     * Create remainder discount object.  Return new 'remainder' discount.
     * $used the amount of this discount that was used up
     *
     * @throws ValidationException
     */
    public function createRemainder(float $used): ?static
    {
        //don't recreate or do stuff with inactive discount
        if (!$this->Active || $this->Child()->exists()) {
            return null;
        }

        $remainder = null;
        //only create remainder if used less than amount
        $amount = $this->getAmount();

        if ($used < $amount) {
            // duplicate dataobject and update accordingly
            $remainder = $this->duplicate(false);
            $remainder->write();

            // delete any relationships that might be sitting in DB for whatever
            // reason
            $remainder->deleteRelationships();

            // create proper new relationships
            $this->duplicateRelations($this, $remainder, $this->manyMany());

            //TODO: there may be some relationships that shouldn't be copied?
            $remainder->Amount = $amount - $used;
            $remainder->ParentID = $this->ID;
            //unset old code
            $remainder->Code = '';
            $remainder->write();
        }

        return $remainder;
    }

    public function validate(): ValidationResult
    {
        $validationResult = parent::validate();
        //prevent vital things from changing
        foreach ($this->config()->get('defaults') as $field => $value) {
            if ($this->isChanged($field)) {
                $validationResult->addError($field . ' should not be changed for partial use discounts.');
            }
        }

        return $validationResult;
    }

    /**
     * Delete complex relations
     */
    protected function deleteRelationships(): void
    {
        if ($this->manyMany()) {
            foreach ($this->manyMany() as $name => $type) {
                $this->{$name}()->removeAll();
            }
        }
    }
}
