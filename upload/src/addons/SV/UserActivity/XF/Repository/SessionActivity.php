<?php

namespace SV\UserActivity\XF\Repository;

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
        /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
        $userActivityRepo = \XF::repository('SV\UserActivity:UserActivity');
        $visitor = \XF::visitor();
        if ($userActivityRepo->isLogging() && $viewState === 'valid' && $userId === $visitor->user_id)
        {
            $handler = $userActivityRepo->getHandler($controller);
            if (!empty($handler))
            {
                $requiredKey = $handler['id'];
                if (!empty($params[$requiredKey]))
                {
                    $userActivityRepo->bufferTrackViewerUsage($handler['type'], $params[$requiredKey], $handler['activeKey']);
                }
            }

            $userActivityRepo->flushTrackViewerUsageBuffer($ip, $robotKey, $visitor);
        }

        parent::updateSessionActivity($userId, $ip, $controller, $action, $params, $viewState, $robotKey);
    }
}
