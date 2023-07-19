<?php

namespace SV\UserActivity\Cron;

class ActivityGarbageCollect
{
    public static function run(): void
    {
		$jobManager = \XF::app()->jobManager();
		if (!$jobManager->getUniqueJob('ActivityGarbageCollect'))
		{
			try
			{
				$jobManager->enqueueUnique('ActivityGarbageCollect', 'SV\UserActivity\Job\ActivityGarbageCollect');
			}
			catch (\Exception $e)
			{
				// need to just ignore this and let it get picked up later;
				// not doing this could lose email on a deadlock
			}
		}
    }
}