<?php

require_once('Services/Form/classes/class.ilFormGUI.php');
require_once('Services/UIComponent/Toolbar/classes/class.ilToolbarGUI.php');

/**
 * Class ilCoSubFormGUI
 */
class ilCoSubFormGUI extends ilFormGUI
{
	protected $content;
	protected $toolbar;


	public function __construct()
	{
		$this->toolbar = new ilToolbarGUI();
		$this->toolbar->setOpenFormTag(false);
		$this->toolbar->setCloseFormTag(false);
		$this->toolbar->setPreventDoubleSubmission(true);
	}

	public function setContent($a_content)
	{
		$this->content = $a_content;
	}

	public function addSeparator()
	{
		$this->toolbar->addSeparator();
	}

	public function addCommandButton($a_cmd, $a_txt)
	{
		$button = ilSubmitButton::getInstance();
		$button->setCommand($a_cmd);
		$button->setCaption($a_txt, false);
		$this->toolbar->addButtonInstance($button);
	}

	function getContent()
	{
		return $this->toolbar->getHTML() . $this->content . $this->toolbar->getHTML();
	}
}