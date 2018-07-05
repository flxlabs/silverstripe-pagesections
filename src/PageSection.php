<?php

namespace FLXLabs\PageSections;

use SilverStripe\CMS\Model\SiteTree;
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
		"__Counter" => "Int",
	];

	private static $has_one = [
		"Page" => SiteTree::class,
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
			$this->Page()->__PageSectionCounter++;
			$this->Page()->write();
		}
	}

	public function forTemplate() {
		return $this->Elements()->Count();

		$actions = FieldList::create();
		$fields = FieldList::create();
		$form = Form::create(null, "Form", $fields, $actions);

		$config = GridFieldConfig::create()
			->addComponent(new GridFieldToolbarHeader())
			->addComponent($dataColumns = new GridFieldDataColumns())
			->addComponent(new GridFieldPageSectionsExtension($this->owner));
		$dataColumns->setFieldCasting(["GridFieldPreview" => "HTMLText->RAW"]);

		$grid = GridField::create("Elements", "Elements", $this->Elements(), $config);
		$grid->setForm($form);
		$fields->add($grid);

		$form->setFields($fields);

		return $form->forTemplate();
	}

	// Gets the name of this section from the page it is on
	public function getName() {
		$page = $this->Page();
		// TODO: Find out why this happens
		if (!method_exists($page, "getPageSectionNames")) {
			return null;
		}
		foreach ($page->getPageSectionNames() as $sectionName) {
			if ($page->{"PageSection" . $sectionName . "ID"} === $this->ID) {
				return $sectionName;
			}
		}
		return null;
	}
}
