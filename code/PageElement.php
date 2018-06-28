<?php

class PageElement extends DataObject {
	public static $singular_name = 'Element';
	public static $plural_name = 'Elements';
	public static $default_is_open = true;
	public function getSingularName() { return static::$singular_name; }
	public function getPluralName() { return static::$plural_name; }
	public function isOpenByDefault() { return static::$default_is_open; }

	function canView($member = null) { return true; }
	function canEdit($member = null) { return true; }
	function canDelete($member = null) { return true; }
	function canCreate($member = null) { return true; }

	private static $can_be_root = true;

	private static $db = array(
		'Title' => 'Varchar(255)',
	);

	private static $versioned_many_many = array(
		'Children' => 'PageElement',
	);

	private static $versioned_belongs_many_many = array(
		'Parents' => 'PageElement',
	);

	private static $many_many_extraFields = array(
		'Children' => array(
			'SortOrder' => 'Int',
		),
	);

	public static $summary_fields = array(
		'GridFieldPreview',
	);

	public static $searchable_fields = array(
		'ClassName',
		'Title',
		'ID'
	);

	private static $better_buttons_actions = array (
		'publishOnAllPages',
	);

	public static function getAllowedPageElements() {
		$classes = array_values(ClassInfo::subclassesFor('PageElement'));
		// remove
		$classes = array_diff($classes, ["PageElement"]);
		return $classes;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		$list = $this->Children()->Sort("SortOrder")->toArray();
		$count = count($list);
		$min = -2 - ($count * 2);

		for ($i = 1; $i <= $count; $i++) {
			$this->Children()->Add($list[$i - 1], array("SortOrder" => $min + $i * 2));
		}
	}

	public function getChildrenGridField() {
		$allowedElements = $this->getAllowedPageElements();
		$addNewButton = new GridFieldAddNewMultiClass();
		$addNewButton->setClasses($allowedElements);

		$autoCompl = new GridFieldAddExistingAutocompleter('buttons-before-right');
		$autoCompl->setResultsFormat('$Title ($ID)');
		$autoCompl->setSearchList(PageElement::get()->exclude("ID", $this->getParentIDs()));

		$config = GridFieldConfig::create()
			->addComponent(new GridFieldButtonRow("before"))
			->addComponent(new GridFieldToolbarHeader())
			->addComponent($dataColumns = new GridFieldDataColumns())
			->addComponent($autoCompl)
			->addComponent($addNewButton)
			->addComponent(new GridFieldPageSectionsExtension($this->owner))
			->addComponent(new GridFieldDetailForm())
			->addComponent(new GridFieldFooter());
		$dataColumns->setFieldCasting(array('GridFieldPreview' => 'HTMLText->RAW'));

		return new GridField("Children", "Children", $this->Children(), $config);
	}

	public function getGridFieldPreview() {
		return $this->Title;
	}

	// Get all the versioned_belongs_many_many that might have been added by
	// additional page sections from various pages. (Also contains "Parent" relation)
	public function getVersionedBelongsManyMany() {
		return Config::inst()->get($this->getClassName(), "versioned_belongs_many_many");
	}

	// Gets all the pages that this page element is on, plus
	// adds an __PageSectionName attribute to the page object so we
	// know which section this element is in.
	public function getAllPages() {
		$pages = ArrayList::create();
		foreach ($this->getVersionedBelongsManyMany() as $name => $relation) {
			// Skip any relations that probably aren't from page sections
			$splits = explode("_", $name);
			if (count($splits) < 2 || mb_substr($splits[1], 0, 11) !== "PageSection") {
				continue;
			}

			// Add all pages (and the page section that this element is in)
			foreach ($this->$name() as $page) {
				$stage = Versioned::current_stage();
				Versioned::reading_stage(Versioned::get_live_stage());

				$oldPage = DataObject::get_by_id($page->ClassName, $page->ID);

				$page->__PageSectionName = mb_substr($splits[1], 11);
				$page->__PageElementVersion = $page->$splits[1]()->filter("ID", $this->ID)->First()->Version;
				$page->__PageElementPublishedVersion = $oldPage->$splits[1]()->filter("ID", $this->ID)->First()->Version;
				$pages->add($page);

				Versioned::reading_stage($stage);
			}
		}
		return $pages;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('Parents');

		$fields->removeByName("Children");
		if ($this->ID && count(static::getAllowedPageElements())) {
			$fields->addFieldToTab('Root.Children', $this->getChildrenGridField());
		}

		// Add our newest version as a readonly field
		$fields->addFieldsToTab(
			"Root.Main", 
			ReadonlyField::create("Version", "Version", $this->Version),
			"Title"
		);

		// Create an array of all the pages this element is on
		$pages = $this->getAllPages();

		// Remove default fields
		foreach ($this->getVersionedBelongsManyMany() as $name => $rel) {
			$fields->removeByName($name);
		}

		$config = GridFieldConfig_Base::create()
			->removeComponentsByType(GridFieldDataColumns::class)
			->addComponent($dataColumns = new GridFieldDataColumns());
		$dataColumns->setDisplayFields([
			"ID" => "ID",
			"ClassName" => "Type",
			"Title" => "Title",
			"__PageSectionName" => "PageSection",
			"__PageElementVersion" => "Element version",
			"__PageElementPublishedVersion" => "Published element version",
			"getPublishState" => "Page state",
		]);
		$gridField = GridField::create("Pages", "Pages", $pages, $config);
		$fields->addFieldToTab("Root.Pages", $gridField);
		
		return $fields;
	}

	public function getParentIDs() {
		$IDArr = array($this->ID);
		foreach($this->Parents() as $parent) {
			$IDArr = array_merge($IDArr, $parent->getParentIDs());
		}
		return $IDArr;
	}

	public function renderChildren($parents) {
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

	public function getBetterButtonsUtils() {
		$fieldList = FieldList::create(array(
			BetterButtonPrevNextAction::create(),
		));
		return $fieldList;
	}

	public function getBetterButtonsActions() {
		$fieldList = FieldList::create(array(
			BetterButton_SaveAndClose::create(),
			BetterButton_Save::create(),
			BetterButtonCustomAction::create('publishOnAllPages', 'Publish on all pages')
				->setRedirectType(BetterButtonCustomAction::REFRESH)
		));
		return $fieldList;
	}

	public function publishOnAllPages() {
		foreach ($this->getAllPages() as $page) {
			$page->publish(Versioned::current_stage(), Versioned::get_live_stage());
		}
		return 'Published on all pages';
	}
}
