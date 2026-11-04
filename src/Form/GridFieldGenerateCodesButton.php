<?php

namespace SilverShop\Discounts\Form;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;

/**
 * Toolbar button on a Discount's Codes grid that generates a batch of unique child codes (issue
 * silvershop/silvershop-discounts#31), so a campaign's many codes don't have to be entered one at a
 * time. The batch size is the `batch_size` config (default 25); click again for another batch.
 */
class GridFieldGenerateCodesButton implements GridField_HTMLProvider, GridField_ActionProvider
{
    use Configurable;
    use Injectable;

    private static int $batch_size = 25;

    protected string $targetFragment;

    public function __construct(string $targetFragment = 'buttons-before-left')
    {
        $this->targetFragment = $targetFragment;
    }

    protected function batchSize(): int
    {
        return max(1, (int) static::config()->get('batch_size'));
    }

    /** @return array<string, string> */
    public function getHTMLFragments($gridField): array
    {
        $record = $gridField->getForm()->getRecord();

        if (!$record instanceof Discount || !$record->isInDB()) {
            return [];
        }

        $action = GridField_FormAction::create(
            $gridField,
            'generatecodes',
            _t(__CLASS__ . '.GENERATE', 'Generate {count} codes', ['count' => $this->batchSize()]),
            'generatecodes',
            []
        )->addExtraClass('btn btn-outline-primary font-icon-plus-circled')->setForm($gridField->getForm());

        return [
            $this->targetFragment => (string) $action->Field()
        ];
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

        if ($record instanceof Discount && $record->isInDB()) {
            $record->generateCodes($this->batchSize());
        }
    }
}
