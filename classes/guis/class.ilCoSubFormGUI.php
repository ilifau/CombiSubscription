<?php

/**
 * Class ilCoSubFormGUI
 */
class ilCoSubFormGUI extends ilFormGUI
{
	protected string $content;
	protected ilToolbarGUI $toolbar;


	public function __construct()
	{
		$this->toolbar = new ilToolbarGUI();
		$this->toolbar->setOpenFormTag(false);
		$this->toolbar->setCloseFormTag(false);
		$this->toolbar->setPreventDoubleSubmission(true);
	}

	public function setContent(string $a_content): void
	{
		$this->content = $a_content;
	}

	public function addSeparator(): void
	{
		$this->toolbar->addSeparator();
	}

	public function addCommandButton(string $a_cmd, string$a_txt): void
	{
		$button = ilSubmitButton::getInstance();
		$button->setCommand($a_cmd);
		$button->setCaption($a_txt, false);
		$this->toolbar->addButtonInstance($button);
	}

	function getContent(): string
	{
		return $this->toolbar->getHTML() . $this->content . $this->toolbar->getHTML();
	}
}