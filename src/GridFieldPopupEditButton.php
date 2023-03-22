<?php

namespace FLXLabs\PageSections;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\View\ArrayData;

class GridFieldPopupEditButton extends GridFieldEditButton
{
	public function getExtraData($gridField, $record, $columnName)
	{
		return [
			"classNames" => "font-icon-edit asdf"
		];
	}

	public function getColumnContent($gridField, $record, $columnName)
	{
		// No permission checks, handled through GridFieldDetailForm,
		// which can make the form readonly if no edit permissions are available.
		$data = new ArrayData([
			'Link' => Controller::join_links($gridField->Link('item'), $record->ID, 'edit'),
			'ExtraClass' => $this->getExtraClass()
		]);
		return $data->renderWith(['FLXLabs\\PageSections\\GridFieldPopupEditButton']);
	}
}
