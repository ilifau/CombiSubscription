<?php
/**
 * Base class for combined subscription configuration GUI classes (except tables)
 */
abstract class ilCoSubMethodBaseConfigGUI
{
	/** @var  ilCombiSubscriptionPlugin */
	public ilCombiSubscriptionPlugin $plugin;

	/** @var  ilCtrl */
	public ilCtrl $ctrl;

	/** @var ilTemplate */
	public ilTemplate $tpl;

	/** @var ilLanguage */
	public ilLanguage $lng;

	/** @var ilPropertyFormGUI */
	protected ilPropertyFormGUI $form;


	/**
	 * Constructor
	 * @param ilCombiSubscriptionPlugin     $a_plugin
	 */
	public function __construct(ilCombiSubscriptionPlugin $a_plugin)
	{
		global $ilCtrl, $tpl, $lng;

		$this->plugin = $a_plugin;
		$this->ctrl = $ilCtrl;
		$this->tpl = $tpl;
		$this->lng = $lng;
	}

	/**
	 * Get the id (classname) of the assigned method
	 * @return string
	 */
	abstract public function getMethodId(): string;

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
	 * Get a localized text
	 * The language variable will be prefixed with lowercase class name, e.g. 'ilmymethod_'
	 *
	 * @param string	$a_langvar	language variable
	 * @return string
	 */
	public function txt(string $a_langvar): string
	{
		return $this->plugin->txt(strtolower($this->getMethodId()).'_'.$a_langvar);
	}

}