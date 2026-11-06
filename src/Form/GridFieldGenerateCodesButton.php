<?php

namespace SilverShop\Discounts\Form;

use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Model\OrderCoupon;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\TextField;

/**
 * A small form above a Discount's Codes grid that generates a batch of unique child codes (issue
 * silvershop/silvershop-discounts#31): how many, the per-code use limit (default 1 - single use),
 * the per-customer limit, and an optional prefix / length. One promotion, many hand-out codes.
 */
class GridFieldGenerateCodesButton implements GridField_HTMLProvider, GridField_ActionProvider
{
    use Injectable;

    protected string $targetFragment;

    public function __construct(string $targetFragment = 'before')
    {
        $this->targetFragment = $targetFragment;
    }

    /** @return array<string, string> */
    public function getHTMLFragments($gridField): array
    {
        $record = $gridField->getForm()->getRecord();

        if (!$record instanceof Discount || !$record->isInDB()) {
            return [];
        }

        $defaultLength = (int) OrderCoupon::config()->get('generated_code_length');

        // default use limit 1 = single use, the usual intent for unique bulk codes
        $fields = [
            NumericField::create('GenerateCodesCount', _t(__CLASS__ . '.NUMBER', 'Number of codes'), 10),
            NumericField::create('GenerateCodesUseLimit', _t(__CLASS__ . '.USELIMIT', 'Use limit per code'), 1),
            NumericField::create('GenerateCodesPerCustomer', _t(__CLASS__ . '.PERCUSTOMER', 'Max per customer'), 0),
            TextField::create('GenerateCodesPrefix', _t(__CLASS__ . '.PREFIX', 'Prefix')),
            NumericField::create('GenerateCodesLength', _t(__CLASS__ . '.LENGTH', 'Length'), $defaultLength)
        ];

        $inputs = '';

        foreach ($fields as $field) {
            $inputs .= sprintf(
                '<div style="display:flex;flex-direction:column;gap:4px;min-width:130px;">'
                . '<label style="font-size:1.1em;font-weight:600;">%s</label>%s</div>',
                Convert::raw2xml((string) $field->Title()),
                (string) $field->Field()
            );
        }

        $button = GridField_FormAction::create(
            $gridField,
            'generatecodes',
            _t(__CLASS__ . '.GENERATE', 'Generate'),
            'generatecodes',
            []
        )->addExtraClass('btn btn-primary font-icon-plus-circled')->setForm($gridField->getForm());

        $html = sprintf(
            '<div class="generate-codes" style="display:flex;flex-wrap:wrap;gap:1.2em;align-items:flex-end;'
            . 'padding:1.2em;margin-bottom:1.2em;background:#f6f7f8;border-radius:4px;">'
            . '<div style="flex-basis:100%%;font-weight:600;font-size:1.2em;">%s</div>%s'
            . '<div style="align-self:flex-end;">%s</div></div>',
            Convert::raw2xml(_t(__CLASS__ . '.HEADING', 'Generate codes')),
            $inputs,
            (string) $button->Field()
        );

        return [$this->targetFragment => $html];
    }

    /** @return array<int, string> */
    public function getActions($gridField): array
    {
        return ['generatecodes'];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $data
     */
    public function handleAction(GridField $gridField, $actionName, $arguments, $data): void
    {
        if ($actionName !== 'generatecodes') {
            return;
        }

        $record = $gridField->getForm()->getRecord();

        if (!$record instanceof Discount || !$record->isInDB()) {
            return;
        }

        $count = (int) ($data['GenerateCodesCount'] ?? 0);

        if ($count < 1) {
            return;
        }

        $length = (int) ($data['GenerateCodesLength'] ?? 0);

        $record->generateCodes(
            $count,
            (int) ($data['GenerateCodesUseLimit'] ?? 0),
            (int) ($data['GenerateCodesPerCustomer'] ?? 0),
            $length > 0 ? $length : null,
            trim((string) ($data['GenerateCodesPrefix'] ?? ''))
        );
    }
}
