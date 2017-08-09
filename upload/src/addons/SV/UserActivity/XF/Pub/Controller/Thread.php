<?php

namespace SV\UserActivity\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
    const CONTROLLER_NAME = 'XF\Pub\Controller\Thread';

    protected function preDispatchController($action, ParameterBag $params)
    {
        $userActivityRepo = $this->app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->registerHandler(self::CONTROLLER_NAME, 'thread', 'thread_id');
        return parent::preDispatchController($action, $params);
    }

    public function actionIndex(ParameterBag $params)
    {
        $response = parent::actionIndex($params);
        $userActivityRepo = $this->app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->insertUserActivityIntoViewResponse(self::CONTROLLER_NAME, $response);
        return $response;
    }
}