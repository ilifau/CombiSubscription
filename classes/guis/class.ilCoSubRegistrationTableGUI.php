<?php

require_once('Services/Table/classes/class.ilTable2GUI.php');
/**
 * Table GUI for registration items
 */
class ilCoSubRegistrationTableGUI extends ilTable2GUI
{
	/** @var  ilCtrl */
	protected $ctrl;

	/** @var array   $value => $text */
	protected $options = array();

	/** @var int maximum choices count of all item priorities */
	protected $max_choices = 0;

	/** @var bool	setting choices is disabled  */
	protected $disabled = false;

	/** @var array local_item_id => other_item_id => item */
	protected $conflicts = [];

	/**
	 * ilCoSubItemsTableGUI constructor.
	 * @param ilCoSubRegistrationGUI    $a_parent_gui
	 * @param string                    $a_parent_cmd
	 */
	function __construct($a_parent_gui, $a_parent_cmd)
	{
		global $ilCtrl;
		parent::__construct($a_parent_gui, $a_parent_cmd);

		$this->parent = $a_parent_gui;
		$this->plugin = $a_parent_gui->plugin;
		$this->object = $a_parent_gui->object;

		$this->ctrl = $ilCtrl;

		$this->setOpenFormTag(false);
		$this->setCloseFormTag(false);

		$this->setRowTemplate("tpl.il_xcos_registration_row.html", $this->plugin->getDirectory());
		$this->setEnableNumInfo(false);
		$this->setExternalSegmentation(true);

		$this->addColumn($this->plugin->txt('registration_header_item'),'','40%');

		$priorities = $this->object->getMethodObject()->getPriorities();
		$header_choices = $this->plugin->txt(count($priorities) < 3 ? 'registration_header_choices' : 'registration_header_priorities');
		if ($this->object->getShowBars())
		{
			$header_choices .= '<br /><span class="text-muted small">' .$this->plugin->txt('registration_header_bars_info') . '</span>';
		}
		$this->addColumn($header_choices,'','40%');
	}

	/**
	 * Set the disabled status
	 * @param bool $a_disabled
	 */
	public function setDisabled($a_disabled)
	{
		$this->disabled = $a_disabled;
	}

	/**
	 * Prepare the data to be displayed
	 * @param   ilCoSubItem[]   $a_items
	 * @param   array           $a_priorities   item_id => priority
	 * @param   array           $a_counts       item_id => priority => count
	 * @param   array           $a_conflicts    local_item_id => other_item_id => item
	 */
	public function prepareData($a_items, $a_priorities, $a_counts, $a_conflicts = [])
	{
		// get the available options
		$method = $this->object->getMethodObject();
		foreach ($method->getPriorities() as $value => $text)
		{
			$this->options[$value] = $text;
		}
		$this->options['not'] = $method->getNotSelected();

		// prepare the data rows
		$data = array();
		foreach ($a_items as $item)
		{
			$row = get_object_vars($item);
			$priority = $a_priorities[$item->item_id];
			if (isset($priority))
			{
				$row['priority'] = $priority;
			}
			if (isset($a_counts[$item->item_id]))
			{
				$row['counts'] = $a_counts[$item->item_id];
			}
			$row['period'] = $item->getPeriodInfo();
			$data[] = $row;

			$this->max_choices = max($this->max_choices, $item->sub_max);
		}
		$this->setData($data);

		// determine the maximum count of all priorities
		foreach ($a_counts as $item_id => $priorities)
		{
			foreach ($priorities as $priority => $count)
			{
				$this->max_choices = max($this->max_choices, $count);
			}
		}

		$this->conflicts = $a_conflicts;
	}

	/**
	 * Fill a single data row
	 */
	protected function fillRow($a_set)
	{
		/** @var ilAccessHandler $ilAccess */
		global $ilAccess;

		require_once('Services/Locator/classes/class.ilLocatorGUI.php');

		$this->tpl->setVariable('TITLE', $a_set['title']);
		$this->tpl->setVariable('DESCRIPTION', $a_set['description']);
		$this->tpl->setVariable('PERIOD', $a_set['period']);

		if (!empty($a_set['target_ref_id']) && $ilAccess->checkAccess('visible','', $a_set['target_ref_id']))
		{
			require_once('Services/Locator/classes/class.ilLocatorGUI.php');
			$locator = new ilLocatorGUI();
			$locator->addContextItems($a_set['target_ref_id']);
			$this->tpl->setVariable('PATH', $locator->getHTML());
		}

		if (isset($a_set['sub_min']) && isset($a_set['sub_max']))
		{
			$this->tpl->setVariable('LIMITS', sprintf($this->plugin->txt('sub_limit_from_to'), $a_set['sub_min'], $a_set['sub_max']));
		}
		elseif (isset($a_set['sub_min']))
		{
			$this->tpl->setVariable('LIMITS', sprintf($this->plugin->txt('sub_limit_from'), $a_set['sub_min']));
		}
		elseif (isset($a_set['sub_max']))
		{
			$this->tpl->setVariable('LIMITS', sprintf($this->plugin->txt('sub_limit_to'), $a_set['sub_max']));
		}

		if (isset($this->conflicts[$a_set['item_id']]))
		{
			$titles = [];
			/** @var  ilCoSubItem $item */
			foreach	($this->conflicts[$a_set['item_id']] as $item_id => $item)
			{
				$titles[] = $item->title;
			}
			$this->tpl->setVariable('CONFLICTS', $this->plugin->txt('conflict_with_assigned_unit') . ' '. implode(', ', $titles));
		}

		foreach ($this->options as $value => $text)
		{
			// progress bar
			if ($this->object->getShowBars() && $this->max_choices > 0 && $value !== 'not')
			{
				$count = isset($a_set['counts'][$value]) ? $a_set['counts'][$value] : 0;
				if ($count)
				{
					$this->tpl->setCurrentBlock($value == 0 ? 'progress_top' : 'progress');
					$this->tpl->setVariable('COUNT', $count);
					$this->tpl->setVariable('MAX', $this->max_choices);
					$this->tpl->setVariable('PERCENT', round(100 * $count / $this->max_choices));
					$this->tpl->parseCurrentBlock();
				}
			}

			// choices
			if ($this->object->getShowBars())
			{
				$this->tpl->setCurrentBlock('option_progress');
			}
//			elseif(count($this->options) <= 3)
//			{
//				$this->tpl->setCurrentBlock('option_vertical');
//			}
			else
			{
				$this->tpl->setCurrentBlock('option_horizontal');
			}

			$this->tpl->setVariable('ITEM_ID', $a_set['item_id']);
			$this->tpl->setVariable('PRIORITY', $value);
			$this->tpl->setVariable('TEXT', $text);

			if (isset($a_set['priority']))
			{
				if ($value == $a_set['priority'])
				{
					$this->tpl->setVariable('CHECKED','checked="checked"');
				}
			}
			elseif ($value == 'not')
			{
				$this->tpl->setVariable('CHECKED','checked="checked"');
			}
			if ($this->disabled)
			{
				$this->tpl->setVariable('DISABLED','disabled="true"');
			}
			$this->tpl->parseCurrentBlock();
		}
	}
}