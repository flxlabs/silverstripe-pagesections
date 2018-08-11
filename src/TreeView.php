<?php

namespace FLXLabs\PageSections;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\SS_List;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\View\ArrayData;
use SilverStripe\View\HTML;
use SilverStripe\View\Requirements;
use SilverStripe\View\ViewableData;

/**
 * Class TreeView
 */
class TreeView extends FormField
{
	/**
	 * @var string
	 */
	protected $sortField = 'SortOrder';
	protected $parent = null;
	protected $context = null;
	protected $opens = null;

	private static $allowed_actions = array(
		'index',
		'tree',
		'move',
		'add',
		'remove',
		'delete',
		'search',
		'detail',
	);


	public function __construct($name, $title = null, $section = null)
	{
		parent::__construct($name, $title, null);

		$this->section = $section;
		$this->context = singleton(PageElement::class)->getDefaultSearchContext();

		// Open default elements
		$this->opens = new \stdClass();
		foreach ($this->getItems() as $item) {
			$this->openRecursive($item);
		}
	}

	/**
	 * Saves this TreeView into the specified record
	 *
	 * We do nothing here, because the TreeView saves all changes while editing, 
	 * so there are no additional actions we have to perform here. We overwrite 
	 * this because the default behavior would write a NULL value into the relation.
	 */
	public function saveInto(DataObjectInterface $record)
	{
	}

	/**
	 * Recursively opens an item
	 *
	 * Recursively opens items if they have children and ->isOpenByDefault() returns true
	 */
	private function openRecursive($item, $parents = [])
	{
		if ($item->isOpenByDefault() && $item->Children()->Count()) {
			$this->openItem(
				array_merge(
					array_map(function ($e) {
						return $e->ID;
					}, $parents),
					[$item->ID]
				)
			);
			foreach ($item->Children() as $child) {
				$this->openRecursive($child, array_merge($parents, [$item]));
			}
		}
	}

	/**
	 * Extracts info from an incoming request
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return array
	 */
	private function pre($request)
	{
		$data = $request->requestVars();

		// Protection against CSRF attacks
		$token = $this->getForm()->getSecurityToken();
		if (!$token->checkRequest($request)) {
			$this->httpError(400, _t(
				"SilverStripe\\Forms\\Form.CSRF_FAILED_MESSAGE",
				"There seems to have been a technical problem. Please click the back button, " .
					"refresh your browser, and try again."
			));
			return;
		}

		// Restore state from session
		$session = $request->getSession();
		if (isset($data["state"])) {
			$this->opens = $session->get($data["state"]);
		}

		return $data;
	}

	public function index($request)
	{
		$this->pre($request);
		return $this->FieldHolder();
	}

	/**
	 * This action is called when opening or closing an element in the tree
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function tree($request)
	{
		$data = $this->pre($request);
		if (!$data) {
			return $this->FieldHolder();
		}

		if (!isset($data["itemId"]) || !isset($data["parents"])) {
			Controller::curr()->getResponse()->setStatusCode(
				400,
				"Missing required data!"
			);
			return $this->FieldHolder();
		}

		$itemId = intval($data["itemId"]);
		$parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));
		$path = array_merge($parents, [$itemId]);

		if ($this->isOpen($path)) {
			$this->closeItem($path);
		} else {
			$this->openItem($path);
		}

		return $this->FieldHolder();
	}

	/**
	 * This action is called when an element in the tree is moved to another spot
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function move($request)
	{
		$data = $this->pre($request);
		if (!$data) {
			return $this->FieldHolder();
		}

		if (!isset($data["itemId"]) || !isset($data["parents"]) ||
				!isset($data["newParent"]) || !isset($data["sort"])) {
			Controller::curr()->getResponse()->setStatusCode(
				400,
				"Missing required data!"
			);
			return $this->FieldHolder();
		}

		$itemId = intval($data["itemId"]);
		$parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));
		$path = array_merge($parents, [$itemId]);

		$item = PageElement::get()->byID($itemId);

		// Get the new parent
		$newParentId = $data["newParent"];
		if ($newParentId) {
			$newParent = PageElement::get()->byID($newParentId);
		} else {
			$newParent = $this->section;
		}
		
		// Check if this element is allowed as a child on the new element
		$allowed = in_array($item->ClassName, $newParent->getAllowedPageElements());
		if (!$allowed) {
			Controller::curr()->getResponse()->setStatusCode(
				400,
				"The type " . $item->ClassName . " is not allowed as a child of " . $newParent->ClassName
			);
			return $gridField->FieldHolder();
		}

		// Remove the element from the current parent
		if (count($parents) == 0) {
			$this->getItems()->removeByID($itemId);
		} else {
			$parent = PageElement::get()->byID($parents[count($parents) - 1]);
			$parent->Children()->removeByID($itemId);
		}

		$sort = intval($data["sort"]);
		$sortBy = $this->getSortField();
		$sortArr = [$sortBy => $sort];

		// Add the element to the new parent
		if ($newParent->ClassName == PageSection::class) {
			$newParent->Elements()->Add($item, $sortArr);
		} else {
			$newParent->Children()->Add($item, $sortArr);
		}

		return $this->FieldHolder();
	}

	/**
	 * This action is called when adding a new or an existing item
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function add($request)
	{
		$data = $this->pre($request);
		if (!$data) {
			return $this->FieldHolder();
		}

		if (!isset($data["type"]) && !isset($data["id"])) {
			Controller::curr()->getResponse()->setStatusCode(
				400,
				"Missing required data!"
			);
			return $this->FieldHolder();
		}

		// If we have an id then add an existing item...
		if (isset($data["id"])) {
			$element = PageElement::get()->byID($data["id"]);
			if (!$element) {
				Controller::curr()->getResponse()->setStatusCode(
					400,
					"Could not find PageElement with id " . $data['id']
				);
				return $this->FieldHolder();
			}
	
			$this->getItems()->Add($element);
		} else {
			// ...otherwise add a completely new item
			$itemId = isset($data["itemId"]) ? intval($data["itemId"]) : null;
			$type = $data["type"];
	
			$child = $type::create();
			$child->Name = "New " . $type;

			$sort = intval($data["sort"]);
			$sortBy = $this->getSortField();
			$sortArr = [$sortBy => $sort];

			// If we have an itemId then we're adding to another element
			// otherwise we're adding to the root
			if ($itemId) {
				$parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));
				$path = array_merge($parents, [$itemId]);

				$item = PageElement::get()->byID($itemId);
				if (!in_array($type, $item->getAllowedPageElements())) {
					Controller::curr()->getResponse()->setStatusCode(
						400,
						"The type " . $type . " is not allowed as a child of " . $item->ClassName
					);
					return $this->FieldHolder();
				}
	
				$child->write();
				$item->Children()->Add($child, $sortArr);
	
				// Make sure we can see the child
				$this->openItem(array_merge($path, [$item->ID]));
			} else {
				$child->write();
				$this->getItems()->Add($child, $sortArr);
			}
		}

		return $this->FieldHolder();
	}

	/**
	 * Action called when an item is removed from the TreeView
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function remove($request)
	{
		$data = $this->pre($request);
		if (!$data) {
			return $this->FieldHolder();
		}

		if (!isset($data["itemId"]) || !isset($data["parents"])) {
			Controller::curr()->getResponse()->setStatusCode(
				400,
				"Missing required data!"
			);
			return $this->FieldHolder();
		}

		$itemId = intval($data["itemId"]);
		$parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));

		// We only need the parent directly above the child, all parents further up don't matter,
		// because the relations are duplicated if the item is duplicated.
		// If we have no parents then we're removing it from the root
		if (count($parents) == 0) {
			$this->getItems()->removeByID($itemId);
		} else {
			$parent = PageElement::get()->byID($parents[count($parents) - 1]);
			$parent->Children()->removeByID($itemId);
		}

		return $this->FieldHolder();
	}

	/**
	 * This action is called when an element is deleted
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function delete($request)
	{
		$data = $this->pre($request);
		if (!$data) {
			return $this->FieldHolder();
		}

		if (!isset($data["itemId"]) || !isset($data["parents"])) {
			Controller::curr()->getResponse()->setStatusCode(
				400,
				"Missing required data!"
			);
			return $this->FieldHolder();
		}

		$itemId = intval($data["itemId"]);
		$parents = array_values(array_filter(explode(",", $data["parents"]), 'strlen'));
		$path = array_merge($parents, [$itemId]);

		$item = PageElement::get()->byID($itemId);

		// Close the element in case it's open to avoid errors
		$this->closeItem($path);

		// Delete the element
		$item->delete();

		return $this->FieldHolder();
	}

	/**
	 * This action is called when the find existing dialog is shown.
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function search($request)
	{
		$form = Form::create(
			$this,
			'search',
			$this->context->getFields(),
			FieldList::create(
				FormAction::create('doSearch', _t('GridFieldExtensions.SEARCH', 'Search'))
					->setUseButtonTag(true)
					->addExtraClass('btn btn-primary font-icon-search')
			)
		);
		$form->addExtraClass('stacked add-existing-search-form form--no-dividers');
		$form->setFormMethod('GET');

		// Check if we're requesting the form for the first time (we return the template)
		// or if this is a submission (we return the form, so it calls the submitted action)
		if (count($request->requestVars()) === 0) {
			return $form->forAjaxTemplate();
		}
		return $form;
	}

	/**
	 * This action is called when a search is performed in the find existing dialog
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function doSearch($data, $form)
	{
		$list = $this->context->getQuery($data, false, false);
		$allowed = $this->section->getAllowedPageElements();
		// Remove all disallowed classes
		$list = $list->filter("ClassName", $allowed);
		// If we're viewing the search list on a PageElement,
		// then we have to remove all parents as possible elements
		/*if ($this->parent->ClassName === PageElement::class) {
			$list = $list->subtract($this->parent->getAllParents());
		}*/
		$list = new PaginatedList($list, $data);
		$data = $this->customise([
				'SearchForm' => $form,
				'Items'      => $list
		]);
		return $data->renderWith("FLXLabs\PageSections\TreeViewFindExistingForm");
	}

	/**
	 * Creates a detail edit form for the specified item
	 * @param \FLXLabs\PageSections\PageElement $item
	 * @param bool $loadData True if the data from $item should be loaded into the form, false otherwise.
	 * @return \SilverStripe\Forms\Form
	 */
	public function DetailForm(PageElement $item, bool $loadData = true)
	{
		$canEdit = $item->canEdit();
		$canDelete = $item->canDelete();

		$actions = new FieldList();
		if ($canEdit) {
			$actions->push(FormAction::create('doSave', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Save', 'Save'))
				->setUseButtonTag(true)
				->addExtraClass('btn-primary font-icon-save'));
		}
		if ($canDelete) {
			$actions->push(FormAction::create('doDelete', _t('SilverStripe\\Forms\\GridField\\GridFieldDetailForm.Delete', 'Delete'))
				->setUseButtonTag(true)
				->addExtraClass('btn-outline-danger btn-hide-outline font-icon-trash-bin action-delete'));
		}

		$fields = $item->getCMSFields();
		$fields->addFieldToTab("Root.Main", HiddenField::create("ID", "ID", $item->ID));

		$form = Form::create(
			$this,
			'detail',
			$fields,
			$actions
		);
		if ($loadData) {
			$form->loadDataFrom($item, Form::MERGE_DEFAULT);
		}

		$form->setTemplate([
			'type' => 'Includes',
			'SilverStripe\\Admin\\LeftAndMain_EditForm',
		]);
		$form->addExtraClass('view-detail-form cms-content cms-edit-form center fill-height flexbox-area-grow');
		if ($form->Fields()->hasTabSet()) {
			$form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe\\Forms\\CMSTabSet');
			$form->addExtraClass('cms-tabset');
		}

		return $form;
	}

	/**
	 * This action is called when the detail form for an item is opened.
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function detail($request) {
		$id = intval($request->requestVar("ID"));

		// This is a request to show the form so we return it as a template so 
		// that SilverStripe doesn't think this is already a submission 
		// (it would call the first action on the form)
		if ($request->isGET()) {
			$item = PageElement::get()->byID($id);
			if (!$item) {
				return $this->httpError(404);
			}
			$form = $this->DetailForm($item);
			// Save the id of the page element on this form's security token
			$request->getSession()->set("_tv_df_" . $form->getSecurityToken()->getValue(), $id);
			return $form->forAjaxTemplate();
		}

		// If it's a POST request then it's a submission and we have to get the ID
		// from the session using the form's security token.
		$id = $request->getSession()->get("_tv_df_" . $request->requestVar("SecurityID"));
		$item = PageElement::get()->byID($id);
		return $this->DetailForm($item, false);
	}

	/**
	 * This action is called when the detail form is submitted (saved/deleted)
	 * @param \SilverStripe\Control\HTTPRequest $request
	 * @return string
	 */
	public function doSave($data, $form) {
		$id = intval($data["ID"]);
		$item = PageElement::get()->byID($id);

		$form->saveInto($item);
		$item->write();
	}

	/**
	 * Get base items
	 *
	 * Gets all the top level items of this TreeView.
	 * @return \SilverStripe\ORM\ArrayList
	 */
	public function getItems()
	{
		return $this->section->Elements();
		/*return $this->parent->ClassName == PageSection::class ?
			$this->parent->Elements() : $this->parent->Children();*/
	}

	/**
	 * Gets the sort field
	 *
	 * Gets the name of the field by which items are sorted.
	 * @return string
	 */
	public function getSortField()
	{
		return $this->sortField;
	}

	/**
	 * Gets the directory name of this module
	 *
	 * @return string
	 */
	public static function getModuleDir()
	{
		return basename(dirname(__DIR__));
	}

	/**
	 * Renders this TreeView as an HTML tag
	 * @param array $properties The additional properties for the TreeView
	 * @return string
	 */
	public function FieldHolder($properties = array())
	{
		$moduleDir = self::getModuleDir();
		Requirements::css($moduleDir . "/css/TreeView.css");
		Requirements::javascript($moduleDir . "/javascript/TreeView.js");
		Requirements::add_i18n_javascript($moduleDir . '/javascript/lang', false, true);

		// Ensure $id doesn't contain only numeric characters
		$sessionId = 'ps_tv_' . substr(md5(serialize($this->opens)), 0, 8);
		$session = Controller::curr()->getRequest()->getSession();
		$session->set($sessionId, $this->opens);

		$content = '';

		$classes = $this->section->getAllowedPageElements();
		$elems = [];
		foreach ($classes as $class) {
			$elems[$class] = $class::getSingularName();
		}

		// Create the add new multi class button
		$addNew = DropdownField::create('AddNewClass', '', $elems);
		$content .= ArrayData::create([
			"Title" => "Add new",
			"ClassField" => $addNew
		])->renderWith("\FLXLabs\PageSections\TreeViewAddNewButton");

		// Create the find existing button
		$findExisting = TreeViewFormAction::create($this, 'FindExisting', 'Find existing');
		$findExisting->addExtraClass("tree-actions-findexisting");
		$content .= $findExisting->forTemplate();

		$list = $this->getItems()->sort($this->sortField)->toArray();

		$first = true;
		foreach ($list as $item) {
			$content .= $this->renderTree($item, [], $this->opens, $first);
			$first = false;
		}

		return HTML::createTag(
			'fieldset',
			[
				'class' => 'treeview-pagesections pagesection-' . $this->getName(),
				'data-name' => $this->getName(),
				'data-url' => $this->Link(),
				'data-state-id' => $sessionId,
			],
			$content
		);
	}

	public function Field($properties = array())
	{
		return $this->FieldHolder($properties);
	}

	/**
	 * Renders an item tree
	 *
	 * Renders the specified item and it's children
	 * @param PageElement $item The item to render
	 * @param string[] $parents The hierarchy of parents this item is a child of
	 * @param \Stdclass $opens The local open state for the item
	 * @param boolean $isFirst True if this is the first item of the direct parent, false otherwise
	 */
	private function renderTree($item, $parents, $opens, $isFirst)
	{
		$childContent = null;
		$level = count($parents) + 1;
		$isOpen = isset($opens->{$item->ID}) && $item->Children()->Count() > 0;

		// Render children if we are open
		if ($isOpen) {
			$children = $item->Children()->Sort($this->sortField);
			$first = true;
			foreach ($children as $child) {
				$childContent .= $this->renderTree(
					$child,
					array_merge($parents, [$item]),
					$opens->{$item->ID},
					$first
				);
				$first = false;
			}
		}

		// Construct the array of all allowed child elements
		$classes = $item->getAllowedPageElements();
		$elems = [];
		foreach ($classes as $class) {
			$elems[$class] = $class::getSingularName();
		}

		// Find out if this item is allowed as a root item
		// There are two cases, either this GridField is on a page,
		// or it is on a PageElement and we're looking that the children
		$parentClasses = $this->section->getAllowedPageElements();
		$isAllowedRoot = in_array($item->ClassName, $parentClasses);

		// Get the list of parents of this element as an array of ids
		// (already converted to json/a string)
		$tree = "[" .
			implode(
				',',
				array_map(function ($item) {
					return $item->ID;
				},
				$parents)
			) .
			"]";

		// Create the tree icon
		$icon = '';
		if ($item->Children() && $item->Children()->Count() > 0) {
			$icon = ($isOpen === true ? 'font-icon-down-open' : 'font-icon-right-open');
		}

		// Create a button to add a new child element
		// and save the allowed child classes on the button
		$classes = $item->getAllowedPageElements();
		$elems = [];
		foreach ($classes as $class) {
			$elems[$class] = singleton($class)->singular_name();
		}
		$addButton = TreeViewFormAction::create(
			$this,
			"AddAction".$item->ID,
			null,
			null,
			null
		);
		$addButton->setAttribute("data-allowed-elements", json_encode($elems, JSON_UNESCAPED_UNICODE));
		$addButton->addExtraClass("add-button font-icon-plus");
		if (!count($elems)) {
			$addButton->setDisabled(true);
		}
		$addButton->setButtonContent('Add');

		// Create a button to add an element after
		// and save the allowed child classes on the button
		$classes = count($parents) > 0 
			? $parents[count($parents) - 1]->getAllowedPageElements() 
			: $this->section->getAllowedPageElements();
		$elems = [];
		foreach ($classes as $class) {
			$elems[$class] = singleton($class)->singular_name();
		}
		$addAfterButton = TreeViewFormAction::create(
			$this,
			"AddAfterAction".$item->ID,
			null,
			null,
			null
		);
		$addAfterButton->setAttribute("data-allowed-elements", 
			json_encode($elems, JSON_UNESCAPED_UNICODE)
		);
		$addAfterButton->addExtraClass("add-after-button font-icon-plus");
		if (!count($elems)) {
			$addAfterButton->setDisabled(true);
		}
		$addAfterButton->setButtonContent('Add after');

		// Create a button to delete and/or remove the element from the parent
		$deleteButton = TreeViewFormAction::create(
			$this,
			"DeleteAction".$item->ID,
			null,
			null,
			null
		);
		$deleteButton->setAttribute(
			"data-used-count",
			$item->Parents()->Count() + $item->getAllSectionParents()->Count()
		);
		$deleteButton->addExtraClass("delete-button font-icon-trash-bin");
		$deleteButton->setButtonContent('Delete');

		// Create a button to edit the record
		$editButton = TreeViewFormAction::create(
			$this,
			"EditAction".$item->ID,
			null,
			null,
			null
		);
		$editButton->addExtraClass("edit-button font-icon-edit");
		$editButton->setButtonContent('Edit');

		// Create the tree icon
		$icon = '';
		if ($item->Children() && $item->Children()->Count() > 0) {
			$icon = ($isOpen === true ? 'font-icon-down-open' : 'font-icon-right-open');
		}

		// Create the tree field
		$treeButton = TreeViewFormAction::create(
			$this,
			"TreeNavAction".$item->ID,
			null,
			"dotreenav",
			["element" => $item]
		);
		$treeButton->addExtraClass("tree-button " . ($isOpen ? "is-open" : "is-closed"));
		if (!$item->Children()->Count()) {
			$treeButton->addExtraClass(" is-end");
			$treeButton->setDisabled(true);
		}
		$treeButton->addExtraClass($icon);
		$treeButton->setButtonContent(' ');

		return ArrayData::create([
			"Item"            => $item,
			"Tree"            => $tree,
			"IsOpen"          => $isOpen,
			"IsFirst"         => $isFirst,
			"Children"        => $childContent,
			"AllowedRoot"     => $isAllowedRoot,
			"AllowedElements" => json_encode($elems, JSON_UNESCAPED_UNICODE),
			"TreeButton"      => $treeButton,
			"AddButton"       => $addButton,
			"AddAfterButton"  => $addAfterButton,
			"EditButton"      => $editButton,
			"DeleteButton"    => $deleteButton,
			"UsedCount"       => $item->Parents()->Count() + $item->getAllSectionParents()->Count(),
		])->renderWith("\FLXLabs\PageSections\TreeViewPageElement");
	}

	/**
	 * Checks if the specified item is open
	 *
	 * @param string[] $path The hierarchy of item ids, the last being the item to check.
	 * @return boolean
	 */
	private function isOpen($path)
	{
		$opens = $this->opens;
		foreach ($path as $itemId) {
			if (!isset($opens->{$itemId})) {
				return false;
			}

			$opens = $opens->{$itemId};
		}

		return true;
	}

	/**
	 * Opens an item
	 *
	 * Opens the item at the specified path
	 * @param string[] $path The hierarchy of item ids, the last being the item to open.
	 */
	private function openItem($path)
	{
		$opens = $this->opens;
		foreach ($path as $itemId) {
			if (!isset($opens->{$itemId})) {
				$opens->{$itemId} = new \stdClass();
			}

			$opens = $opens->{$itemId};
		}
	}

	/**
	 * Closes an item
	 *
	 * Closes the item at the specified path
	 * @param string[] $path The hierarchy of item ids, the last being the item to close.
	 */
	private function closeItem($path)
	{
		$opens = $this->opens;
		for ($i = 0; $i < count($path) - 1; $i++) {
			if (!isset($opens->{$path[$i]})) {
				return;
			}

			$opens = $opens->{$path[$i]};
		}

		unset($opens->{$path[count($path) - 1]});
	}
}