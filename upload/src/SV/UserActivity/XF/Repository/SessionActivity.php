<?php

namespace SV\UserActivity\XF\Repository;

class SessionActivity extends XFCP_SessionActivity
{
    public function updateSessionActivity($userId, $ip, $controller, $action, array $params, $viewState, $robotKey)
    {
        $userActivityRepo = $app->repository('SV\UserActivity\Repository\UserActivity');
        $handler = $userActivityRepo->getHandler($controller);
        if (!empty($handler) && $userActivityRepo->isLogging() && $viewState == 'valid')
        {
            $requiredKey = $handler[1];
            if (!empty($inputParams[$requiredKey]))
            {
                if (empty($robotKey) || $this->app->options()->SV_UA_TrackRobots)
                {
                    $visitor = \XF::visitor();
                    if($userId == $visitor->user_id)
                    {
                        $contentType = $handler[0];
                        $userActivityRepo->updateSessionActivity($contentType, $inputParams[$requiredKey], $ip, $robotKey, $visitor);
                    }
                }
            }
        }
        return parent::updateSessionActivity($userId, $ip, $controller, $action, $params, $viewState, $robotKey);
    }
}