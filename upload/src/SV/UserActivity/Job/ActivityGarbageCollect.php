<?php

namespace SV\UserActivity\Job;

class ActivityGarbageCollect extends AbstractJob
{
    public function run($maxRunTime)
    {
        $app = \XF::app();

		$userActivityRepo = $app->repository('SV\UserActivity\Repository\UserActivity');
		$data = $userActivityRepo->GarbageCollectActivity($this->data, $maxRunTime);        
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