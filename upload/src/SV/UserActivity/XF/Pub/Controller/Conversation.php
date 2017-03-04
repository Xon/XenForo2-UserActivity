<?php

namespace SV\UserActivity\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Conversation extends XFCP_Conversation
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $app = $this->app();
        $userActivityRepo = $app->repository('SV\UserActivity\Repository\UserActivity');
        $controller = $app->extension()->resolveExtendedClassToRoot($this);
        $userActivityRepo->registerHandler($controller, 'conversation', 'conversation_id');
        return parent::preDispatchController($action, $params);
    }
}