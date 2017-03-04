<?php

namespace SV\UserActivity\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Conversation extends XFCP_Conversation
{
    const CONTROLLER_NAME = 'XF\Pub\Controller\Conversation';

    protected function preDispatchController($action, ParameterBag $params)
    {
        $userActivityRepo = $this->app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->registerHandler(self::CONTROLLER_NAME, 'conversation', 'conversation_id');
        return parent::preDispatchController($action, $params);
    }

    public function actionView(ParameterBag $params)
    {
        $response = parent::actionView($params);
        $userActivityRepo = $this->app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->insertUserActivityIntoViewResponse(self::CONTROLLER_NAME, $response);
        return $response;
    }
}