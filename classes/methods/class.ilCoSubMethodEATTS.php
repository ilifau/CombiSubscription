<?php

/**
 * Assignment method using the externam EATTS system
 */
class ilCoSubMethodEATTS extends ilCoSubMethodBase
{
	# region class constants
	const STATUS_OK = 200;
	const STATUS_NO_CONTENT = 204;
	const STATUS_BAD_REQUEST = 400;
	const STATUS_UNAUTHORIZED = 401;
	const STATUS_ERROR = 500;

	# endregion


	# region class variables

	/** eatts server url */
	public string $server_url;
	/** @var string license server url */
	public string $license_url;
	public string $license;
	public string $log_level;
	public int $max_iterations;
	/** time limit in seconds */
	public int $time_limit;
	/** weight of priorities */ 
	public float $priority_weight;
	/** weight of maximum subscriptions */ 
	public float $sub_max_weight;
	/** weight of minimum subscriptions */
	public float $sub_min_weight;
    /** weight peer selections */
	public float $peers_weight;
	protected ilCoSubRun $run;

	# endregion

	public function __construct(ilObjCombiSubscription $a_object, ilCombiSubscriptionPlugin $a_plugin)
	{
		parent::__construct($a_object, $a_plugin);

		$this->server_url = self::_getSetting('server_url');
		$this->license_url = self::_getSetting('license_url');
		$this->license = self::_getSetting('license');
		$this->log_level = self::_getSetting('log_level');

		$this->max_iterations = (int) $this->getProperty('max_iterations','100000');
		$this->time_limit = (int) $this->getProperty('time_limit','10');
		$this->priority_weight = (float) $this->getProperty('priority_weight','20.0');
		$this->sub_max_weight = (float) $this->getProperty('sub_max_weight','20.0');
		$this->sub_min_weight = (float) $this->getProperty('sub_min_weight', '10.0');
		$this->peers_weight = (float) $this->getProperty('peers_weight', '0.5');
	}

	/**
	 * Save the properties
	 */
	public function saveProperties(): void
	{
		$this->setProperty('max_iterations', sprintf('%d', $this->max_iterations));
		$this->setProperty('time_limit', sprintf('%d', $this->time_limit));
		$this->setProperty('priority_weight', sprintf('%.1F', $this->priority_weight));
		$this->setProperty('sub_max_weight', sprintf('%.1F', $this->sub_max_weight));
		$this->setProperty('sub_min_weight', sprintf('%.1F', $this->sub_min_weight));
		$this->setProperty('peers_weight', sprintf('%.1F', $this->peers_weight));
	}

	/**
	 * Get the supported priorities
	 * (0 is the highest)
	 * return array number => name
	 */
	public function getPriorities(): array
	{
		return array(
			0 => $this->txt('select_preferred'),
			1 => $this->txt('select_alternative'),
		);
	}

	/**
	 * Get the text for no selection
	 */
	public function getNotSelected(): string
	{
		return $this->txt('select_not');
	}

	/**
	 * This methods allows multipe selections per oriority
	 */
	public function hasMultipleChoice(): bool
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
	 * This method is active
	 */
	public function isActive(): bool
	{
		return false;
	}

	/**
	 * Calculate the assignments
	 * - Should create the run assignments when it is finished
	 * - Should set the run_end date and save the run when it is finished
	 *
	 * return bool         true: calculation is started, false: an error occurred, see getError()
	 */
	public function calculateAssignments(ilCoSubRun $a_run): bool
	{
		$this->run = $a_run;
		$this->error = '';

		$this->run->details = $this->getParameterDetails();

		$getfields = array(
			'cmd' => 'startCalculation',
			'client_id' => CLIENT_ID,
			'object_id' => $this->object->getId(),
			'run_id' => $this->run->run_id
		);
		$postfields = array(
			'input_xml' => $this->getRegistrationsAsXML());

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            $this->server_url.'?'.http_build_query($getfields));
		curl_setopt($ch, CURLOPT_TIMEOUT, 		 5 ); //seconds
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt($ch, CURLOPT_POST,           true );
		curl_setopt($ch, CURLOPT_POSTFIELDS,     http_build_query($postfields));
		curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/x-www-form-urlencoded'));

		$response = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		// calculation successfully started
		if ($status == self::STATUS_OK)
		{
			$values = array();
			parse_str($response, $values);
			$this->run->run_start = new ilDateTime($values['start'], IL_CAL_UNIX);
			$this->run->save();
			return true;
		}
		// failure
		else
		{
			$this->run->details .= $response;
			$this->run->run_end = new ilDateTime(time(), IL_CAL_UNIX);
			$this->run->save();
			$this->error = $response;
			return false;
		}
	}


	/**
	 * Check if the result for a run is available
	 * This will be called for each unfinished run when the list of runs or assignments is shown
	 * - Should create the run assignments when it is finished
	 * - Should set the run_end date and save the run when it is finished
	 *
	 * return bool  true: result is available, false: result is not available or an error occurred, see getError()
	 */
	public function checkForResult(ilCoSubRun $a_run): bool
	{
		$this->run = $a_run;
		$this->error = '';

		$getfields = array(
			'cmd' => 'checkForResult',
			'client_id' => CLIENT_ID,
			'object_id' => $this->object->getId(),
			'run_id' => $this->run->run_id
		);

		$postfields = array();

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,            $this->server_url.'?'.http_build_query($getfields));
		curl_setopt($ch, CURLOPT_TIMEOUT, 		 5 ); //seconds
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt($ch, CURLOPT_POST,           true );
		curl_setopt($ch, CURLOPT_POSTFIELDS,     http_build_query($postfields));
		curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/x-www-form-urlencoded'));

		$response = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		// calculation stopped
		if ($status == self::STATUS_OK)
		{
			$values = array();
			parse_str($response, $values);

			// successfully finished
			if (isset($values['result']))
			{
				$this->run->run_end = new ilDateTime($values['end'], IL_CAL_UNIX);
				$this->run->save();
				$this->createAssignmentsFromXML($values['result']);
				return true;
			}
			// calculation error
			elseif (isset($values['error']))
			{
				$this->run->run_end = new ilDateTime($values['end'], IL_CAL_UNIX);
				$this->run->details .= $values['error'];
				$this->run->save();

				$this->error = $values['error'];
				return false;
			}
		}
		// still running
		elseif ($status == self::STATUS_NO_CONTENT)
		{
			return false;
		}
		// request error
		else
		{
			$this->error = $response;
			return false;
		}
	}

	/**
	 * Get details of the calculation parameters
	 */
	protected function getParameterDetails(): string
	{

		$details = array();
		$details[] = $this->txt('time_limit') .': '. ilFormat::_secondsToString($this->time_limit);
		$details[] = $this->txt('max_iterations'). ': '. $this->max_iterations;
		$details[] = $this->txt('priority_weight'). ': '. sprintf('%.1F', $this->priority_weight);
		$details[] = $this->txt('sub_max_weight'). ': '. sprintf('%.1F', $this->sub_max_weight);
		if ($this->hasMinSubscription())
		{
			$details[] = $this->txt('sub_min_weight'). ': '. sprintf('%.1F', $this->sub_min_weight);
		}
		if ($this->hasPeerSelection())
		{
			$details[] = $this->txt('peers_weight'). ': '. sprintf('%.1F', $this->peers_weight);
		}

		return implode("\n", $details);
	}

	/**
	 * Get the XML code of the choices
	 * return string xml code for EATTS
	 */
	protected function getRegistrationsAsXML(): string|false
	{
		$body = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			.'<COURSEINPUT></COURSEINPUT>';

		$xml = new SimpleXMLElement($body);

		$license = $xml->addChild('LICENCE');
		$license->addAttribute('host', substr($this->license_url, 0, strpos($this->license_url,'/')));
		$license->addAttribute('path', substr($this->license_url, strpos($this->license_url,'/')));
		$license->addAttribute('licence', $this->license);

		$properties = $xml->addChild('PROPERTIES');
		$properties->addAttribute('logLevel', (int) $this->log_level);
		$properties->addAttribute('maxIterations', (int) $this->max_iterations);
		$properties->addAttribute('timeLimit', (int) $this->time_limit);

		$weights = $xml->addChild('WEIGHTS');
		$weights->addAttribute('preference', sprintf('%.1F', $this->priority_weight));
		$weights->addAttribute('maxPart', sprintf('%.1F', $this->sub_max_weight));
		$weights->addAttribute('minPart', sprintf('%.1F', $this->sub_min_weight));
		$weights->addAttribute('bestFriends', sprintf('%.1F', $this->peers_weight));

		$courses = $xml->addChild('COURSES');
		foreach ($this->object->getItems() as $item)
		{
			$course = $courses->addChild('COURSE');
			$course->addAttribute('id', (int) $item->item_id);
			$course->addAttribute('minPart', (int) $item->sub_min);
			$course->addAttribute('maxPart', (int) $item->sub_max);
		}

		$students = $xml->addChild('STUDENTS');
		foreach ($this->object->getPriorities() as $user_id => $items)
		{
			$student = $students->addChild('STUDENT');
			$student->addAttribute('id', $user_id);

			$prios = array(0 => array(), 1 => array());
			foreach ($items as $item_id => $priority)
			{
				$prios[$priority][] = $item_id;
			}
			$student->addChild('PREFERRED_COURSES', implode(' ', $prios[0]));
			$student->addChild('ALTERNATIVE_COURSES', implode(' ', $prios[1]));
			$student->addChild('FRIENDS','');
		}

		// pretty printing
		$dom = new DOMDocument('1.0');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($xml->asXML());
		return $dom->saveXML();
	}


	/**
	 * Create assignments from the xml provided by EATTS
	 * $a_xml    xml code from EATTS
	 */
	protected function createAssignmentsFromXML(string $a_xml): void
	{
		$xml = new SimpleXMLElement($a_xml);
		foreach ($xml->COURSES->COURSE as $course)
		{
			$item_id = $course['id'];
			$students = (string) $course->STUDENTS;
			$user_ids = explode(' ', $students);

			foreach ($user_ids as $user_id)
			{
				if (is_numeric($user_id))
				{
					$assign = new ilCoSubAssign;
					$assign->obj_id = $this->object->getId();
					$assign->run_id = $this->run->run_id;
					$assign->user_id = $user_id;
					$assign->item_id = $item_id;
					$assign->save();
				}
			}
		}
	}

}