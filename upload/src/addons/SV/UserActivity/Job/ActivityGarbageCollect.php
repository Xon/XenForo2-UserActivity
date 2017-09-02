<?php

namespace SV\UserActivity\Job;

use XF\Job\AbstractJob;

class ActivityGarbageCollect extends AbstractJob
{
    public function run($maxRunTime)
    {
        $app = \XF::app();

        /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
		$userActivityRepo = $app->repository('SV\UserActivity:UserActivity');
		$data = $userActivityRepo->garbageCollectActivity($this->data, $maxRunTime);
		if (!$data)
		{
			return $this->complete();
		}
        $this->data = $data;

        return $this->resume();
    }

	public function getStatusMessage()
	{
		return '';
	}

	public function canCancel()
	{
		return false;
	}

	public function canTriggerByChoice()
	{
		return false;
	}
}