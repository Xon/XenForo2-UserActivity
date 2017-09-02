<?php

namespace SV\UserActivity\XF\Repository;

class SessionActivity extends XFCP_SessionActivity
{
    /**
     * @param int $userId
     * @param $ip
     * @param string $controller
     * @param string $action
     * @param array $params
     * @param string $viewState enum('valid','error')
     * @param string $robotKey
     */
    public function updateSessionActivity($userId, $ip, $controller, $action, array $params, $viewState, $robotKey)
    {
        $app = $this->app();
        /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
        $userActivityRepo = $app->repository('SV\UserActivity:UserActivity');
        $handler = $userActivityRepo->getHandler($controller);
        if (!empty($handler) && $userActivityRepo->isLogging() && $viewState == 'valid')
        {
            $requiredKey = $handler[1];
            if (!empty($params[$requiredKey]))
            {
                if (empty($robotKey) || $app->options()->SV_UA_TrackRobots)
                {
                    $visitor = \XF::visitor();
                    if($userId === $visitor->user_id)
                    {
                        $contentType = $handler[0];
                        $userActivityRepo->updateSessionActivity($contentType, $params[$requiredKey], $ip, $robotKey, $visitor);
                    }
                }
            }
        }
        return parent::updateSessionActivity($userId, $ip, $controller, $action, $params, $viewState, $robotKey);
    }
}