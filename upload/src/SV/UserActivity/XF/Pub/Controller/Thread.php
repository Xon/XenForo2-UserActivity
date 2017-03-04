<?php

namespace SV\UserActivity\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Thread extends XFCP_Thread
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $app = $this->app();
        $userActivityRepo = $app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->registerHandler('Thread', 'conversation', 'conversation_id');
        return parent::preDispatchController($action, $params);
    }
}