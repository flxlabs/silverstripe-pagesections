<?php

namespace FLXLabs\PageSections;

use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class PageSection extends DataObject
{
	public $__isNew = false;

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

	private static $cascade_duplicates = [
		"Elements"
	];

	private static $many_many = [
		"Elements" => [
			"through" => PageSectionPageElementRel::class,
			"from" => "PageSection",
			"to" => "Element"
		]
	];

	public function Parent()
	{
		$parent = DataObject::get_by_id($this->__ParentClass, $this->__ParentID);
		if ($parent == null) {
			$parent = Versioned::get_latest_version($this->__ParentClass, $this->__ParentID);
		}
		return $parent;
	}


	public function onBeforeDuplicate()
	{
		PageSectionChangeState::propagateWrites(false);
	}

	public function onAfterDuplicate()
	{
		PageSectionChangeState::propagateWrites(true);
	}

	public function onBeforeCopyToLocale()
	{
		PageSectionChangeState::propagateWrites(false);
	}

	public function onAfterCopyToLocale()
	{
		PageSectionChangeState::propagateWrites(true);
	}

	public function onBeforeWrite()
	{
		parent::onBeforeWrite();

		if (!$this->isInDB()) {
			return;
		}

		$elems = $this->Elements()->Sort("SortOrder")->Column("ID");
		$count = count($elems);
		for ($i = 0; $i < $count; $i++) {
			$this->Elements()->Add($elems[$i], ["SortOrder" => ($i + 1) * 2, "__NewOrder" => true]);
		}
	}

	public function onAfterWrite()
	{
		parent::onAfterWrite();

		if (!PageSectionChangeState::propagateWrites()) {
			return;
		}

		if (!$this->__isNew && Versioned::get_stage() == Versioned::DRAFT && $this->isChanged("__Counter", DataObject::CHANGE_VALUE)) {
			$this->Parent()->__PageSectionCounter++;
			// Only create a new version when the previous one is <th></th>e published one.
			if ($this->Parent()->isLiveVersion()) {
				$this->Parent()->write();
			} else {
				$this->Parent()->writeWithoutVersion();
			}
		}
	}

	/**
	 * Overwrites the parent version so that it actually clones Elements rather than rereferencing them
	 *
	 * @param DataObject $sourceObject
	 * @param DataObject $destinationObject
	 * @param string $relation
	 */
	protected function duplicateManyManyRelation($sourceObject, $destinationObject, $relation)
	{
		if ($relation === 'Elements') {
			return $this->duplicateElements($sourceObject, $destinationObject);
		}

		return parent::duplicateManyManyRelation($sourceObject, $destinationObject, $relation);
	}

	/**
	 * Custom method to clone Elements
	 *
	 * @param DataObject $sourceObject
	 * @param DataObject $destinationObject
	 */
	protected function duplicateElements($sourceObject, $destinationObject)
	{
		// Copy all components from source to destination
		$source = $sourceObject->getManyManyComponents('Elements')->sort('SortOrder');
		$dest = $destinationObject->getManyManyComponents('Elements');

		foreach ($source as $item) {
			$clonedItem = $item->duplicate(false);
			$dest->add($clonedItem);
		}
	}

	public function forTemplate()
	{
		$elements = $this->Elements();
		return $this->renderWith(
			"RenderChildren",
			array("Elements" => $elements, "ParentList" => strval($this->ID))
		);
	}

	/**
	 * Gets the name of this PageSection
	 * @return string
	 */
	public function getName()
	{
		return $this->__Name;
	}

	/**
	 * The classes of allowed child elements
	 *
	 * Gets a list of classnames which are valid child elements of this PageSection.
	 * @param string $section The section for which to get the allowed child classes.
	 * @return string[]
	 */
	public function getAllowedPageElements()
	{
		return $this->Parent()->getAllowedPageElements($this->__Name);
	}
}
