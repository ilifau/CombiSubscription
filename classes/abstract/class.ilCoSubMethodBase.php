<?php

/**
 * Base class for all assignment methods
 * API methods can be overridden by child classes
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
abstract class ilCoSubMethodBase
{
	/** @var  ilObjCombiSubscription */
	protected $object;

	/** @var ilCombiSubscriptionPlugin  */
	protected $plugin;

	/** @var  string error message */
	protected $error;


	/** @var  array raw object properties  */
	private $object_properties;


	#####################
	# region API (object)
	#####################

	/**
	 * Constructor
	 * @param ilObjCombiSubscription        $a_object
	 * @param ilCombiSubscriptionPlugin     $a_plugin
	 */
	public function __construct($a_object, $a_plugin)
	{
		$this->object = $a_object;
		$this->plugin = $a_plugin;
	}

	/**
	 * Get the unique id of the method
	 * @return string
	 */
	final public static function _getId()
	{
		return get_called_class();
	}

	/**
	 * Get the unique id of the method
	 * @return string
	 */
	final public function getId()
	{
		return static::_getId();
	}

	/**
	 * Get the name of the properties GUI class
	 * (oberwrite this if no properties GUI is provided)
	 * @return string
	 */
	public function getPropertiesGuiName()
	{
		return get_class($this) . 'PropertiesGUI';
	}

	/**
	 * Get the file of the properties GUI class
	 * (overwrite this if no properties GUI is provided)
	 * @return string
	 */
	public function getPropertiesGuiPath()
	{
		if ($classname = $this->getPropertiesGuiName())
		{
			return $this->plugin->getDirectory() . '/classes/methods/class.'.$classname.'.php';
		}
	}


	/**
	 * Get the title of the method (to be used in lists or as headline)
	 * @return string
	 */
	public function getTitle()
	{
		return $this->txt('title');
	}

	/**
	 * Get a description of the method (shown as tooltip or info text)
	 * @return string
	 */
	public function getDescription()
	{
		return $this->txt('description');
	}

	/**
	 * Get an explanation that is shown to the assigning participants
	 * @return string   html codes allowed
	 */
	public function getExplanation()
	{
		return $this->txt('explanation');
	}

	/**
	 * Get the supported priorities
	 * (0 is the highest)
	 * @return array    number => name
	 */
	public function getPriorities()
	{
		return array(
			0 => $this->txt('select_prio1'),
			1 => $this->txt('select_prio2')
		);
	}


	/**
	 * Get the background colors for a priority index (0 is the highest)
	 * @return string    css color expression
	 */
	public function getPriorityBackgroundColor($a_priority)
	{

		$low = array(210, 240, 202);
		$high = array(255, 255, 204);
		$steps = count($this->getPriorities()) - 1;

		if ($a_priority == 0)
		{
			return sprintf('rgb(%d, %d, %d)', $low[0], $low[1], $low[2]);
		}
		elseif ($a_priority <= $steps)
		{
			$now = array();
			for ($i = 0; $i <= 2; $i++)
			{
				$now[$i] = round($low[$i] + $a_priority * ($high[$i] - $low[$i]) / $steps );
			}
			return sprintf('rgb(%d, %d, %d)', $now[0], $now[1], $now[2]);
		}
		else
		{
			return 'transparent';
		}
	}

	/**
	 * Get the text for no selection
	 */
	public function getNotSelected()
	{
		return $this->txt('select_not');
	}

	/**
	 * This method allows multiple assignments of items to a user
	 */
	public function hasMultipleAssignments()
	{
		return false;
	}

	/**
	 * This methods allows multiple selections per priority
	 * @return bool
	 */
	public function hasMultipleChoice()
	{
		return false;
	}

	/**
	 * This method allows a priority not being selected
	 * @return bool
	 */
	public function hasEmptyChoice()
	{
		return true;
	}

	/**
	 * This method allows a selection of peers
	 * @return bool
	 */
	public function hasPeerSelection()
	{
		return false;
	}

	/**
	 * This methods respects minimum subscriptions per assignment
	 * @return bool
	 */
	public function hasMinSubscription()
	{
		return false;
	}

	/**
	 * This methods respects maximum subscriptions per assignment
	 * @return bool
	 */
	public function hasMaxSubscription()
	{
		return true;
	}


	/**
	 * This method is active
	 * @return bool
	 */
	public function isActive()
	{
		return false;
	}

	/**
	 * Calculate the assignments
	 * - Should create the run assignments when it is finished
	 * - Should set the run_end date and save the run when it is finished
	 *
	 * @param ilCoSubRun    $a_run
	 * @return bool         true: calculation is started, false: an error occurred, see getError()
	 */
	public function calculateAssignments($a_run)
	{
		return false;
	}

	/**
	 * Check if the result for a run is available
	 * This will be called for each unfinished run when the list of runs or assignments is shown
	 * - Should create the run assignments when it is finished
	 * - Should set the run_end date and save the run when it is finished
	 *
	 * @param ilCoSubRun    $a_run
	 * @return bool         true: result is available, false: result is not availeble or an error occurred, see getError()
	 */
	public function checkForResult($a_run)
	{
		return false;
	}

	/**
	 * Get the error message from calculateAssignments() or  checkForResult()
	 * @return string
	 */
	public function getError()
	{
		return $this->error;
	}

	/**
	 * Get a localized text
	 * The language variable will be prefixed with lowercase class name, e.g. 'ilmymethod_'
	 *
	 * @param string	$a_langvar	language variable
	 * @return string
	 */
	public function txt($a_langvar)
	{
		return $this->plugin->txt(strtolower($this->getId()).'_'.$a_langvar);
	}

	# endregion

	##################################
	# region methods for child classes
	##################################

	/**
	 * Get a global setting for this method
	 * @param   string  $a_key
	 * @param   string  $a_default_value
	 * @return array	value
	 */
	public static function _getSetting($a_key, $a_default_value = '')
	{
		return ilCombiSubscriptionPlugin::_getClassSetting(static::_getId(), $a_key, $a_default_value);
	}

	/**
	 * Set a global setting for this method
	 * @param string  $a_key
	 * @param string  $a_value
	 */
	public static function _setSetting($a_key, $a_value)
	{
		ilCombiSubscriptionPlugin::_setClassSetting(static::_getId(), $a_key, $a_value);
	}

	/**
	 * Get an object property of this method
	 * @param   string  $a_key
	 * @param   string  $a_default_value
	 * @return array	value
	 */
	protected function getProperty($a_key, $a_default_value)
	{
		return $this->object->getClassProperty(get_class($this), $a_key, $a_default_value);
	}

	/**
	 * Set an object property for this method
	 * @param string  $a_key
	 * @param string  $a_value
	 */
	protected function setProperty($a_key, $a_value)
	{
		$this->object->setClassProperty(get_class($this), $a_key, $a_value);
	}

	# endregion
}