<?php

namespace FLXLabs\PageSections;

use SilverStripe\ORM\DataObject;

class PageSectionPageElementRel extends DataObject
{
	private static $table_name = "FLXLabs_PageSections_PageSectionPageElementRel";

	private static $db = array(
		"SortOrder" => "Int",
	);

	private static $has_one = array(
		"PageSection" => PageSection::class,
		"Element" => PageElement::class,
	);

	public function onBeforeWrite()
	{
		parent::onBeforeWrite();

		if (!$this->ID) {
			if (!$this->SortOrder && $this->SortOrder !== 0) {
				// Add new elements at the end (highest SortOrder)
				$this->SortOrder = ($this->PageSection()->Elements()->Count() + 1) * 2;
			}
		}
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();
	}

	public function onAfterDelete()
	{
		parent::onAfterDelete();
	}
}
