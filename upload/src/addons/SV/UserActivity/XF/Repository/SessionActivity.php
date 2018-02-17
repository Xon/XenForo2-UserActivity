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
            $requiredKey = $handler['id'];
            if (!empty($params[$requiredKey]))
            {
                $visitor = \XF::visitor();
                if($userId === $visitor->user_id)
                {
                    $userActivityRepo->trackViewerUsage($handler['type'], $params[$requiredKey], $handler['activeKey'], $ip, $robotKey, $visitor);
                }
            }
        }
        return parent::updateSessionActivity($userId, $ip, $controller, $action, $params, $viewState, $robotKey);
    }
}
