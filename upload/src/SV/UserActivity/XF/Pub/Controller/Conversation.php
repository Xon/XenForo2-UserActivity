<?php

namespace SV\UserActivity\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Conversation extends XFCP_Conversation
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $app = $this->app();
        $userActivityRepo = $app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->registerHandler('Conversation', 'thread', 'thread_id');
        return parent::preDispatchController($action, $params);
    }
}