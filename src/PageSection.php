<?php

namespace FLXLabs\PageSections;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;

class PageSection extends DataObject
{
	private static $table_name = "FLXLabs_PageSections_PageSection";

	private static $db = [
		"__Name" => "Varchar",
		"__ParentID" => "Int",
		"__ParentClass" => "Varchar",
	];

	private static $owns = [
		"Elements",
	];

	private static $cascade_deletes = [
		"Elements",
	];

	private static $many_many = [
		"Elements" => [
			"through" => PageSectionPageElementRel::class,
			"from" => "PageSection",
			"to" => "Element"
		]
	];

	public function Parent() {
		$parent = DataObject::get_by_id($this->__ParentClass, $this->__ParentID);
		if ($parent == null) {
			$parent = Versioned::get_latest_version($this->__ParentClass, $this->__ParentID);
		}
		return $parent;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		$elems = $this->Elements()->Sort("SortOrder")->Column("ID");
		$count = count($elems);
		for ($i = 0; $i < $count; $i++) {
			$this->Elements()->Add($elems[$i], [ "SortOrder" => ($i + 1) * 2, "__NewOrder" => true ]);
		}
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();
	}

	public function forTemplate()
	{
		return $this->Elements()->Count();
	}

	/**
	 * The classes of allowed child elements
	 *
	 * Gets a list of classnames which are valid child elements of this PageSection.
	 * @param string $section The section for which to get the allowed child classes.
	 * @return string[]
	 */
	public function getAllowedPageElements() {
		return $this->Parent()->getAllowedPageElements($this->__Name);
	}
}
