<?php

namespace SV\UserActivity\Job;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use XF\Job\AbstractJob;
use XF\Job\JobResult;

class ActivityGarbageCollect extends AbstractJob
{
    /**
     * @param float $maxRunTime
     * @return JobResult
     */
    public function run($maxRunTime): JobResult
    {
        $app = \XF::app();

        /** @var UserActivityRepo $userActivityRepo */
		$userActivityRepo = $app->repository('SV\UserActivity:UserActivity');
		$data = $userActivityRepo->garbageCollectActivity($this->data, $maxRunTime);
		if (!$data)
		{
			return $this->complete();
		}
        $this->data = $data;

        return $this->resume();
    }

	public function getStatusMessage(): string
    {
		return '';
	}

	public function canCancel(): bool
    {
		return false;
	}

	public function canTriggerByChoice(): bool
    {
		return false;
	}
}