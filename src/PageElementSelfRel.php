<?php

namespace FLXLabs\PageSections;

use SilverStripe\ORM\DataObject;

class PageElementSelfRel extends DataObject {

	private static $table_name = "FLXLabs_PageSections_PageElementSelfRel";

	private static $db = array(
		"SortOrder" => "Int",
	);

	private static $has_one = array(
		"Parent" => PageElement::class,
		"Child" => PageElement::class,
	);
}
