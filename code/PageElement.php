<?php

namespace FlxLabs\PageSections;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;
use UncleCheese\BetterButtons\Actions\BetterButtonPrevNextAction;
use UncleCheese\BetterButtons\Buttons\BetterButton_SaveAndClose;
use UncleCheese\BetterButtons\Buttons\BetterButton_Save;

class PageElement extends DataObject {
	
	protected static $singularName = "Element";
	protected static $pluralName = "Elements";

	public static function getSingularName() {
		return static::$singularName;
	}
	public static function getPluralName() {
		return static::$pluralName;
	}

	function canView($member = null) { return true; }
	function canEdit($member = null) { return true; }
	function canDelete($member = null) { return true; }
	function canCreate($member = null, $context = array()) { return true; }

	private static $can_be_root = true;

	private static $db = array(
		"Name" => "Varchar(255)",
	);

	private static $many_many = array(
		"Children" => PageElement::class,
	);

	private static $belongs_many_many = array(
		"Parents" => PageElement::class,
		"Pages" => SiteTree::class,
	);

	private static $many_many_extraFields = array(
		"Children" => array(
			"SortOrder" => 'Int',
		),
	);
	
	private static $owns = [
    "Children",
  ];

	private static $summary_fields = array(
		"SingularName",
		"ID",
		"GridFieldPreview",
	);

	private static $searchable_fields = array(
		"ClassName",
		"Name",
		"ID",
	);

	public static function getAllowedPageElements() {
		$classes = array_values(ClassInfo::subclassesFor(PageElement::class));
		// remove
		$classes = array_diff($classes, [PageElement::class]);
		return $classes;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		$list = $this->Children()->Sort("SortOrder")->toArray();
		$count = count($list);

		for ($i = 1; $i <= $count; $i++) {
			$this->Children()->Add($list[$i - 1], array("SortOrder" => $i * 2));
		}
	}

	public function onAfterWrite() {
		$stage = Versioned::get_stage();

		foreach ($this->Parents() as $parent) {	
			$parent->copyVersionToStage($stage, $stage, true);
		}

		foreach ($this->Pages() as $page) {
			$page->copyVersionToStage($stage, $stage, true);
		}
	}

	public function getChildrenGridField() {
		$addNewButton = new GridFieldAddNewMultiClass();
		$addNewButton->setClasses($this->getAllowedPageElements());

		$autoCompl = new GridFieldAddExistingAutocompleter('buttons-before-right');
		$autoCompl->setResultsFormat('$Name ($ID)');
		$autoCompl->setSearchList(PageElement::get()->exclude("ID", $this->getParentIDs()));

		$config = GridFieldConfig::create()
			->addComponent(new GridFieldButtonRow("before"))
			->addComponent(new GridFieldToolbarHeader())
			->addComponent($dataColumns = new GridFieldDataColumns())
			->addComponent($autoCompl)
			->addComponent($addNewButton)
			->addComponent(new GridFieldPageSectionsExtension($this->owner))
			->addComponent(new GridFieldDetailForm());
		$dataColumns->setFieldCasting(array("GridFieldPreview" => "HTMLText->RAW"));

		return new GridField("Children", "Children", $this->Children(), $config);
	}

	public function getGridFieldPreview() {
		return $this->Name;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('Pages');
		$fields->removeByName('Parents');

		$fields->removeByName("Children");
		if ($this->ID && count(static::getAllowedPageElements())) {
			$fields->addFieldToTab('Root.PageSections', $this->getChildrenGridField());
		}

		return $fields;
	}

	public function getParentIDs() {
		$IDArr = array($this->ID);
		foreach($this->Parents() as $parent) {
			$IDArr = array_merge($IDArr, $parent->getParentIDs());
		}
		return $IDArr;
	}

	public function renderChildren($parents = null) {
		return $this->renderWith(
			"RenderChildren",
			array("Elements" => $this->Children(), "ParentList" => strval($this->ID) . "," . $parents)
		);
	}

	public function forTemplate($parentList = "") {
		$parents = ArrayList::create();
		$splits = explode(",", $parentList);
		$num = count($splits);
		for ($i = 0; $i < $num - 1; $i++) {
			$parents->add(PageElement::get()->byID($splits[$i]));
		}
		$page = SiteTree::get()->byID($splits[$num - 1]);

		return $this->renderWith(
			array_reverse($this->getClassAncestry()),
			array("ParentList" => $parentList, "Parents" => $parents, "Page" => $page)
		);
	}
}
