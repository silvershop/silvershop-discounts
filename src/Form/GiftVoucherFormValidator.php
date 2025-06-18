<?php

namespace SilverShop\Discounts\Form;

use SilverStripe\Forms\RequiredFields;

class GiftVoucherFormValidator extends RequiredFields
{
    public function php($data): bool
    {
        $valid =  parent::php($data);

        if ($valid) {
            $controller = $this->form->getController();

            if ($controller->VariableAmount) {
                $giftvalue = $data['UnitPrice'];

                if ($controller->MinimumAmount > 0 && $giftvalue < $controller->MinimumAmount) {
                    $this->validationError(
                        'UnitPrice',
                        _t(
                            'GiftVoucherProduct.MinimumAmountError',
                            'Gift value must be at least {MinimumAmount}',
                            ['MinimumAmount' => $controller->MinimumAmount]
                        )
                    );
                    return false;
                }
                if ($giftvalue <= 0) {
                    $this->validationError(
                        'UnitPrice',
                        'Gift value must be greater than 0'
                    );
                    return false;
                }
            }
        }

        return $valid;
    }
}
