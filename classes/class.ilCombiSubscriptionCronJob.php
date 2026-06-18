<?php
// Copyright (c) 2018 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\Cron\Job\JobRepository;
use ILIAS\Cron\Job\JobResult;
use ILIAS\Cron\CronJob;
use ILIAS\Cron\Job\Schedule\JobScheduleType;

class ilCombiSubscriptionCronJob extends CronJob
{
	public const id = 'combi_subscription_cron';

    private JobRepository $repository;
    private ilObjUser $user;
    private ?ilDateTime $last_run = null;
    private bool $is_active = false;
    private $loaded = false;

	public function __construct(private ilCombiSubscriptionPlugin $plugin)
	{
        global $DIC;
        $this->user = $DIC->user();
        $this->repository = $DIC->cron()->repository();		
	}

	public function getId(): string
	{
		return self::id;
	}

	public function getTitle(): string
	{
		return $this->plugin->txt('job_title');
	}

	public function getDescription(): string
	{
		return $this->plugin->txt('job_description');
	}

	public function getDefaultScheduleType(): JobScheduleType
	{
		return JobScheduleType::IN_HOURS;
	}

	public function getDefaultScheduleValue(): ?int
	{
		return 1;
	}

	public function hasAutoActivation(): bool
	{
		return true;
	}

	public function hasFlexibleSchedule(): bool
	{
		return true;
	}

	/**
	 * Run the cron job
	 * @return JobResult
	 */
	public function run(): JobResult
	{
		$result = new JobResult();

		$number = $this->plugin->handleCronJob();
		if ($number == 0)
		{
			$result->setStatus(JobResult::STATUS_NO_ACTION);
			$result->setMessage($this->plugin->txt('no_subscription_processed'));
		}
		elseif ($number == 1)
		{
			$result->setStatus(JobResult::STATUS_OK);
			$result->setMessage($this->plugin->txt('one_subscription_processed'));

		}
		else {
			$result->setStatus(JobResult::STATUS_OK);
			$result->setMessage(sprintf($this->plugin->txt('x_subscriptions_processed'), $number));
		}
		return $result;

	}

    public function loadData(): void
    {
        if (!$this->loaded) {
            $rows = $this->repository->getCronJobData($this->getId());
            if (isset($rows[0])) {
                $data = $rows[0];

                $this->is_active = (bool) ($data['job_status'] ?? false);

                $this->setSchedule(
                    JobScheduleType::tryFrom((int) ($data['schedule_type'] ?? 0)),
                    (int) ($data['schedule_value'] ?? 0)
                );

                $ts = $data['job_result_ts'] ?? 0;
                if ($ts > 0) {
                    $this->last_run = new ilDateTime($ts, IL_CAL_UNIX, $this->user->getTimeZone());
                }
            }
            $this->loaded = true;
        }
    }	

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getLastRun(): ?ilDateTime
    {
        $this->loadData();
        return $this->last_run;
    }

    public function getScheduleType(): ?JobScheduleType
    {
        $this->loadData();
        return parent::getScheduleType();
    }

    public function getScheduleValue(): ?int
    {
        $this->loadData();
        return parent::getScheduleValue();
    }	
}