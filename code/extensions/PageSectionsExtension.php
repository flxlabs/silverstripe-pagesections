<?php
class PageSectionsExtension extends DataExtension {

	// Generate the needed relations on the class
	public function extraStatics($class = null, $extensionClass = null) {
		$versioned_many_many = array();
		$many_many_extraFields = array();

		// Check if the VersionedRelationsExtension is already loaded
		if (Config::inst()->get($class, "__versioned", Config::EXCLUDE_EXTRA_SOURCES)) {
			user_error("VersionedRelationsExtension was loaded before PageSectionsExtension on class '$class'. ".
				"Please correct the order in your config file to ensure that PageSectionsExtension is loaded first.", E_USER_ERROR);
		}

		// Get all the sections that should be added
		$sections = Config::inst()->get($class, "page_sections", Config::EXCLUDE_EXTRA_SOURCES);
		if (!$sections) $sections = array("Main");

		foreach ($sections as $section) {
			$name = "PageSection".$section;
			$versioned_many_many[$name] = "PageElement";
			$many_many_extraFields[$name] = array("SortOrder" => "Int");
		}

		// Create the relations for our sections
		Config::inst()->update($class, "versioned_many_many", $versioned_many_many);
		Config::inst()->update($class, "many_many_extraFields", $many_many_extraFields);
		return array();
	}
	
	public static function getAllowedPageElements() {
		$classes = array_values(ClassInfo::subclassesFor("PageElement"));
		$classes = array_diff($classes, ["PageElement"]);
		return $classes;
	}

	public function onBeforeWrite() {
		$sections = $this->owner->config()->get("page_sections");
		if (!$sections) $sections = array("Main");

		foreach ($sections as $section) {
			$name = "PageSection".$section;

			$list = $this->owner->$name()->Sort("SortOrder")->toArray();
			$count = count($list);

			for ($i = 1; $i <= $count; $i++) { 
					$this->owner->$name()->Add($list[$i - 1], array("SortOrder" => $i * 2));
			}
		}
	}
	
	public function updateCMSFields(FieldList $fields) {
		$sections = $this->owner->config()->get("page_sections");
		if (!$sections) $sections = array("Main");

		foreach ($sections as $section) {
			$name = "PageSection".$section;

			$fields->removeByName($name);
			
			if ($this->owner->ID) {
				$addNewButton = new GridFieldAddNewMultiClass();
				$addNewButton->setClasses($this->getAllowedPageElements());

				$autoCompl = new GridFieldAddExistingAutocompleter('buttons-before-right');
				$autoCompl->setResultsFormat('$Title ($ID)');

				$config = GridFieldConfig::create()
					->addComponent(new GridFieldButtonRow("before"))
					->addComponent(new GridFieldToolbarHeader())
					->addComponent($dataColumns = new GridFieldDataColumns())
					->addComponent($autoCompl)
					->addComponent($addNewButton)
					->addComponent(new GridFieldPageSectionsExtension($this->owner))
					->addComponent(new GridFieldDetailForm());

				$f = new GridField($name, $section, $this->owner->$name(), $config);
				$fields->addFieldToTab("Root.PageSections", $f);
			}
		}
	}

	public function PageSection($name = "Main") {
		$elements = $this->owner->getVersionedRelation("PageSection" . $name);
		if (!$elements) return;
		return $this->owner->renderWith("PageSection", array("Elements" => $elements->sort("SortOrder")));
	}
}
