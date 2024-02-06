<?php

use ILIAS\DI\Container;

/**
 * Base class for combined subscription GUI classes (except tables)
 */
abstract class ilCoSubBaseGUI
{
    public Container $dic;
	public ilObjCombiSubscriptionGUI $parent;
	public ilObjCombiSubscription $object;
	public ilCombiSubscriptionPlugin $plugin;
	public ilCtrl $ctrl;
	public ilTabsGUI $tabs;
	public ilGlobalTemplateInterface $tpl;
	public ilLanguage $lng;
	protected ilPropertyFormGUI $form;
	protected ilToolbarGUI $toolbar;

	public function __construct(ilObjCombiSubscriptionGUI $a_parent_gui)
	{
        global $DIC;

        $this->dic = $DIC;
		$this->parent = $a_parent_gui;
		$this->object = $this->parent->object;
		$this->plugin = $this->parent->plugin;
		$this->ctrl = $this->dic->ctrl();
		$this->tabs = $this->dic->tabs();
		$this->toolbar = $this->dic->toolbar();
		$this->tpl = $this->dic->ui()->mainTemplate();
		$this->lng = $this->dic->language();
	}

	/**
	 * Execute a command
	 * This should be overridden in the child classes
	 * note: permissions are already checked in parent gui
	 *
	 */
	public function executeCommand(): void
	{
		$cmd = $this->ctrl->getCmd('xxx');
		switch ($cmd)
		{
			case 'yyy':
			case 'zzz':
				$this->$cmd();
				return;

			default:
				// show unknown command
				$this->tpl->setContent($cmd);
				return;
		}
	}

	/**
	 * Design a text as page info below toolbar
	 * @param $text
	 * @return string
	 */
	public function pageInfo(string $text): string
	{
		return '<p class="small">'.$text.'</p><br />';
	}

	/**
	 * render a text as messageDetails
	 * @param $text
	 * @return string
	 */
	public function messageDetails(string $text): string
	{
		return '<p class="small">'.$text.'</p>';
	}




}