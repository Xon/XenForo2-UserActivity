<?php

namespace SV\UserActivity\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Post extends XFCP_Post
{
    const CONTROLLER_NAME = 'XF\Pub\Controller\Post';

    protected function preDispatchController($action, ParameterBag $params)
    {
        $userActivityRepo = $this->app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->registerHandler(self::CONTROLLER_NAME, 'thread', 'thread_id');
        return parent::preDispatchController($action, $params);
    }
}