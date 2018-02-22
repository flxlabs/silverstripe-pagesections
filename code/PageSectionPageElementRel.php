<?php

namespace FlxLabs\PageSections;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;

class PageSectionPageElementRel extends DataObject {

	private static $table_name = "FLXLabs_PageSectionPageElementRel";

	private static $db = array(
		"SortOrder" => "Int",
	);

	private static $has_one = array(
		"PageSection" => "Page",
		"Element" => PageElement::class,
	);


	public function onBeforeWrite() {
		parent::onBeforeWrite();

		if (!$this->ID && !$this->SortOrder) {
			$this->SortOrder = 1337;
		}
	}
}
