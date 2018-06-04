<?php

namespace FLXLabs\PageSections;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\SiteTree;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;

use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;

class PageSectionsExtension extends DataExtension {

	// Generate the needed relations on the class
	public static function get_extra_config($class = null, $extensionClass = null) {
		$has_one = [];
		$owns = [];
		$cascade_deletes = [];

		// Get all the sections that should be added
		$sections = Config::inst()->get($class, "page_sections", Config::EXCLUDE_EXTRA_SOURCES);
		if (!$sections) {
			$sections = ["Main"];
		}

		foreach ($sections as $section) {
			$name = "PageSection".$section;
			$has_one[$name] = PageSection::class;

			$owns[] = $name;
			$cascade_deletes[] = $name;
		}

		// Create the relations for our sections
		return [
			"db" => ["__PageSectionCounter" => "Int"],
			"has_one" => $has_one,
			"owns" => $owns,
			"cascade_deletes" => $cascade_deletes,
		];
	}

	public static function getAllowedPageElements() {
		$classes = array_values(ClassInfo::subclassesFor(PageElement::class));
		$classes = array_diff($classes, [PageElement::class]);
		return $classes;
	}

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		if ($this->owner->ID) {
			$sections = $this->owner->config()->get("page_sections");
			if (!$sections) {
				$sections = ["Main"];
			}

			foreach ($sections as $section) {
				$name = "PageSection".$section;

				// Create a page section if we don't have one yet
				if (!$this->owner->$name()->ID) {
					$section = PageSection::create();
					$section->PageID = $this->owner->ID;
					$section->write();
					$this->owner->$name = $section;
				}
			}
		}
	}

	public function updateCMSFields(FieldList $fields) {
		$sections = $this->owner->config()->get("page_sections");
		if (!$sections) {
			$sections = ["Main"];
		}

		foreach ($sections as $section) {
			$name = "PageSection".$section;

			$fields->removeByName($name);

			if ($this->owner->ID) {
				$addNewButton = new GridFieldAddNewMultiClass();
				$addNewButton->setClasses($this->owner->getAllowedPageElements());

				$autoCompl = new GridFieldAddExistingAutocompleter('buttons-before-right');
				$autoCompl->setResultsFormat('$Name ($ID)');

				$config = GridFieldConfig::create()
					->addComponent(new GridFieldButtonRow("before"))
					->addComponent(new GridFieldToolbarHeader())
					->addComponent($dataColumns = new GridFieldDataColumns())
					->addComponent($autoCompl)
					->addComponent($addNewButton)
					->addComponent(new GridFieldPageSectionsExtension($this->owner))
					->addComponent(new GridFieldDetailForm());
				$dataColumns->setFieldCasting(["GridFieldPreview" => "HTMLText->RAW"]);

				$f = new GridField($name, $section, $this->owner->$name()->Elements(), $config);
				$fields->addFieldToTab("Root.PageSections", $f);
			}
		}
	}

	public function RenderPageSection($name = "Main") {
		$elements = $this->owner->{"PageSection" . $name}()->Elements()->Sort("SortOrder");
		return $this->owner->renderWith(
			"RenderChildren",
			["Elements" => $elements, "ParentList" => strval($this->owner->ID)]
		);
	}
}
