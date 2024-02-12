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

	/** minimum seconds between appointments without conflict */ 
	public int $out_of_conflict_time = 3600;
	/** tolerated percentage of schedule time being in conflict with other item */ 
	public int $tolerated_conflict_percentage = 20;
	protected ilObjCombiSubscription $object;
	protected ilCombiSubscriptionPlugin $plugin;
	protected string $error;
	/** tweak: allow to fill up missing assignments of fixed users */
	public bool $fill_fixed_users = false;

	public function __construct(ilObjCombiSubscription $a_object, ilCombiSubscriptionPlugin $a_plugin)
	{
		$this->object = $a_object;
		$this->plugin = $a_plugin;
	}

	/**
	 * Get the unique id of the method
	 */
	final public static function _getId(): string
	{
		return get_called_class();
	}

	/**
	 * Get the unique id of the method
	 */
	final public function getId(): string
	{
		return static::_getId();
	}

	/**
	 * Get the name of the properties GUI class
	 * (oberwrite this if no properties GUI is provided)
	 */
	public function getPropertiesGuiName(): string
	{
		return get_class($this) . 'PropertiesGUI';
	}

	/**
	 * Get the file of the properties GUI class
	 * (overwrite this if no properties GUI is provided)
	 */
	public function getPropertiesGuiPath(): string
	{
		if ($classname = $this->getPropertiesGuiName())
		{
			return $this->plugin->getDirectory() . '/classes/methods/class.'.$classname.'.php';
		}
	}


	/**
	 * Get the title of the method (to be used in lists or as headline)
	 */
	public function getTitle(): string
	{
		return $this->txt('title');
	}

	/**
	 * Get a description of the method (shown as tooltip or info text)
	 */
	public function getDescription(): string
	{
		return $this->txt('description');
	}

	/**
	 * Get an explanation that is shown to the assigning participants
	 * html codes allowed
	 */
	public function getExplanation(): string
	{
		return $this->txt('explanation');
	}

	/**
	 * Get the supported priorities
	 * (0 is the highest)
	 * number => name
	 */
	public function getPriorities(): array
	{
		return array(
			0 => $this->txt('select_prio1'),
			1 => $this->txt('select_prio2')
		);
	}


    /**
     * Check if a priority is in the range of selected priorities
     */
    public function isSelectedPriority(int $a_priority): bool
    {
        return $a_priority >= 0 && $a_priority < count($this->getPriorities());
    }

	/**
	 * Get the background colors for a priority index (0 is the highest)
	 * return css color expression
	 */
	public function getPriorityBackgroundColor(string $a_priority): string
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
	public function getNotSelected(): string
	{
		return $this->txt('select_not');
	}


	/**
	 * Get the minimum seconds between appointments without conflict
	 */
	public function getOutOfConflictTime(): int
	{
		return $this->out_of_conflict_time;
	}

	/**
	 * Get the tolerated percentage of schedule time being in conflict with other item
	 */
	public function getToleratedConflictPercentage(): int
	{
		return $this->tolerated_conflict_percentage;
	}

	/**
	 * Get the number of assignments that are done by this method
	 */
	public function getNumberAssignments(): int
	{
		return 1;
	}

	/**
	 * This method allows multiple assignments of items to a user
	 */
	public function hasMultipleAssignments(): bool
	{
		return false;
	}

	/**
	 * This methods allows multiple selections per priority
	 */
	public function hasMultipleChoice(): bool
	{
		return false;
	}

	/**
	 * This method allows a priority not being selected
	 */
	public function hasEmptyChoice(): bool
	{
		return true;
	}

	/**
	 * This method allows a selection of peers
	 */
	public function hasPeerSelection(): bool
	{
		return false;
	}

	/**
	 * This methods respects minimum subscriptions per assignment
	 */
	public function hasMinSubscription(): bool
	{
		return false;
	}

	/**
	 * This methods respects maximum subscriptions per assignment
	 */
	public function hasMaxSubscription(): bool
	{
		return true;
	}

	/**
	 * This method provides the result of calculateAssignments instantly
	 */
	public function hasInstantResult(): bool
	{
		return false;
	}

	/**
	 * This method is active
	 */
	public function isActive(): bool
	{
		return false;
	}

	/**
	 * Calculate multiple assignment runs and get the best one
	 * $a_tries: number of tries
     * $a_cleanup: delete other runs except the best
	 */
	public function getBestCalculationRun(int $a_tries, bool $a_cleanup = false): ?ilCoSubRun
	{
		if (!$this->hasInstantResult())
		{
			return null;
		}

        //$a_tries = 1;
        //$a_cleanup = false;
        
		$runs = array();
		for ($try = 1; $try <= $a_tries; $try++)
		{
			$run = new ilCoSubRun;
			$run->obj_id = $this->object->getId();
			$run->method = $this->getId();
			$run->save();
			$runs[$run->run_id] = $run;

			$this->calculateAssignments($run);
		}

		// read all assignments of the newly calculated runs (needed for comparing)
		$this->object->getAssignments(true);

		$best_run_id = null;
		$max_full_satisfied = 0;
		$max_satisfied = 0;
        $max_assignments = 0;
		foreach (array_keys($runs) as $run_id)
		{
			$satisfied = 0;
			$full_satisfied = 0;
            $assignments = 0;
            
			foreach (array_keys($this->object->getUsers()) as $user_id)
			{
                $assignments += count($this->object->getAssignmentsOfUser($user_id, $run_id));
                
				switch ($this->object->getUserSatisfaction($user_id, $run_id))
				{
					case ilObjCombiSubscription::SATISFIED_FULL:
						$satisfied++;
						$full_satisfied++;
						break;

					case ilObjCombiSubscription::SATISFIED_MEDIUM:
						$satisfied++;
						break;
				}
			}
			if ($satisfied > $max_satisfied)
			{
				$best_run_id = $run_id;
				$max_satisfied = $satisfied;
			}
			elseif ($satisfied == $max_satisfied 
                && $full_satisfied > $max_full_satisfied)
			{
				$best_run_id = $run_id;
				$max_full_satisfied = $full_satisfied;
			}
            elseif ($satisfied == $max_satisfied 
                && $full_satisfied == $max_full_satisfied
                && $assignments > $max_assignments
            )
            {
                $best_run_id = $run_id;
                $max_assignments = $assignments;
            }
		}

        // cleanup all runs which are not the best
        if ($a_cleanup)
        {
            foreach ($runs as $run_id => $run)
            {
                if ($run_id != (int) $best_run_id) {
                    ilCoSubAssign::_deleteForObject($this->object->getId(), $run_id);
                    ilCoSubRun::_deleteById($run_id);
                }
            }
        }

		if (isset($best_run_id))
		{
			return $runs[$best_run_id];
		}
        else
        {
            $this->error = sprintf($this->plugin->txt('no_best_run_found'), $a_tries);
        }

		return null;
	}


	/**
	 * Calculate the assignments
	 * - Should create the run assignments when it is finished
	 * - Should set the run_end date and save the run when it is finished
	 * return: true: calculation is started, false: an error occurred, see getError()
	 */
	public function calculateAssignments(ilCoSubRun $a_run): bool
	{
		return false;
	}

	/**
	 * Check if the result for a run is available
	 * This will be called for each unfinished run when the list of runs or assignments is shown
	 * - Should create the run assignments when it is finished
	 * - Should set the run_end date and save the run when it is finished
	 * return: true: result is available, false: result is not availeble or an error occurred, see getError()
	 */
	public function checkForResult(ilCoSubRun $a_run): bool
	{
		return false;
	}

	/**
	 * Get the error message from calculateAssignments() or  checkForResult()
	 */
	public function getError(): string
	{
		return $this->error;
	}

	/**
	 * Get a localized text
	 * The language variable will be prefixed with lowercase class name, e.g. 'ilmymethod_'
	 */
	public function txt(string $a_langvar): string
	{
		return $this->plugin->txt(strtolower($this->getId()).'_'.$a_langvar);
	}


	/**
	 * Get a global setting for this method
	 */
	public static function _getSetting(string $a_key, string $a_default_value = ''): string
	{
		return ilCombiSubscriptionPlugin::_getClassSetting(static::_getId(), $a_key, $a_default_value);
	}

	/**
	 * Set a global setting for this method
	 */
	public static function _setSetting(string $a_key, string $a_value): void
	{
		ilCombiSubscriptionPlugin::_setClassSetting(static::_getId(), $a_key, $a_value);
	}

	/**
	 * Get an object property of this method
	 */
	protected function getProperty(string $a_key, string $a_default_value): string
	{
		return $this->object->getClassProperty(get_class($this), $a_key, $a_default_value);
	}

	/**
	 * Set an object property for this method
	 */
	protected function setProperty(string $a_key, string $a_value): void
	{
		$this->object->setClassProperty(get_class($this), $a_key, $a_value);
	}

}