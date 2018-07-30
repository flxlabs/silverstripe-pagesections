<?php

namespace FLXLabs\PageSections;

use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Versioned\Versioned;

use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;

class PageSection extends DataObject {

	private static $table_name = "FLXLabs_PageSections_PageSection";

	private static $db = [
		"__Name" => "Varchar",
		"__ParentID" => "Int",
		"__ParentClass" => "Varchar",
		"__Counter" => "Int",
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
		return DataObject::get_by_id($this->__ParentClass, $this->__ParentID);
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		$elems = $this->Elements()->Sort("SortOrder")->Column("ID");
		$count = count($elems);
		for ($i = 0; $i < $count; $i++) {
			$this->Elements()->Add($elems[$i], [ "SortOrder" => ($i + 1) * 2, "__NewOrder" => true ]);
		}
	}

	public function onAfterWrite() {
		parent::onAfterWrite();

		if (!$this->__isNew && Versioned::get_stage() == Versioned::DRAFT) {
			$this->Parent()->__PageSectionCounter++;
			$this->Parent()->write();
		}
	}

	public function forTemplate() {
		return $this->Elements()->Count();
	}

	// Gets the name of this section from the parent it is on
	public function getName() {
		$parent = $this->Parent();
		// TODO: Find out why this happens
		if (!method_exists($parent, "getPageSectionNames")) {
			return null;
		}
		foreach ($parent->getPageSectionNames() as $sectionName) {
			if ($parent->{"PageSection" . $sectionName . "ID"} === $this->ID) {
				return $sectionName;
			}
		}
		return null;
	}

	public function getAllowedPageElements($section = "Main") {
		return $this->Parent()->getAllowedPageElements($section);
	}
}
