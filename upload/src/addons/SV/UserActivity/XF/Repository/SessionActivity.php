<?php

namespace SV\UserActivity\XF\Repository;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;

class SessionActivity extends XFCP_SessionActivity
{
    /**
     * @param int $userId
     * @param mixed $ip
     * @param string $controller
     * @param string $action
     * @param array $params
     * @param string $viewState enum('valid','error')
     * @param string $robotKey
     * @return void
     */
    public function updateSessionActivity($userId, $ip, $controller, $action, array $params, $viewState, $robotKey)
    {
        $userActivityRepo = UserActivityRepo::get();
        $visitor = \XF::visitor();
        if ($userActivityRepo->isLogging() && $viewState === 'valid' && $userId === $visitor->user_id)
        {
            $handler = $userActivityRepo->getHandler($controller);
            if ($handler)
            {
                $contentId = $params[$handler['id']] ?? null;
                if ($contentId !== null)
                {
                    $userActivityRepo->bufferTrackViewerUsage($handler['type'], $contentId, $handler['activeKey']);
                }
            }

            $userActivityRepo->flushTrackViewerUsageBuffer($ip, $robotKey, $visitor);
        }

        parent::updateSessionActivity($userId, $ip, $controller, $action, $params, $viewState, $robotKey);
    }
}
