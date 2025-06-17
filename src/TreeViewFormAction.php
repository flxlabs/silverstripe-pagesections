<?php

namespace FLXLabs\PageSections;

use SilverStripe\Forms\FormAction;

class TreeViewFormAction extends FormAction
{
	protected $treeView;


	public function __construct(TreeView $treeView, $name, $title)
	{
		$this->treeView = $treeView;

		parent::__construct($name, $title);
	}
}
