<?php

namespace FLXLabs\PageSections;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class PageElementSelfRel extends DataObject {

	private static $table_name = "FLXLabs_PageSections_PageElementSelfRel";

	private static $db = array(
		"SortOrder" => "Int",
	);

	private static $has_one = array(
		"Parent" => PageElement::class,
		"Child" => PageElement::class,
	);

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		if (!$this->ID) {
			if (!$this->SortOrder) {
				// Add new elements at the end (highest SortOrder)
				$this->SortOrder = ($this->Parent()->Children()->Count() + 1) * 2;
			}
		}
	}

	public function onAfterWrite() {
		parent::onAfterWrite();

		if (!$this->__NewOrder && Versioned::get_stage() == Versioned::DRAFT) {
			$this->Parent()->__Counter++;
			$this->Parent()->write();
		}
	}

	public function onAfterDelete() {
		parent::onAfterDelete();

		if (Versioned::get_stage() == Versioned::DRAFT) {
			$this->Parent()->__Counter++;
			$this->Parent()->write();
		}
	}
}
