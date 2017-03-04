<?php

namespace SV\UserActivity\XF\Repository;

class SessionActivity extends XFCP_SessionActivity
{
    public function updateSessionActivity($userId, $ip, $controller, $action, array $params, $viewState, $robotKey)
    {
        $app = $this->app();
        $userActivityRepo = $app->repository('SV\UserActivity\Repository\UserActivity');
        $handler = $userActivityRepo->getHandler($controller);
        if (!empty($handler) && $userActivityRepo->isLogging() && $viewState == 'valid')
        {
            $requiredKey = $handler[1];
            if (!empty($params[$requiredKey]))
            {
                if (empty($robotKey) || $app->options()->SV_UA_TrackRobots)
                {
                    $visitor = \XF::visitor();
                    if($userId == $visitor->user_id)
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