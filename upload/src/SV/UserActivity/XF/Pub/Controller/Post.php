<?php

namespace SV\UserActivity\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Post extends XFCP_Post
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $app = $this->app();
        $userActivityRepo = $app->repository('SV\UserActivity\Repository\UserActivity');
        $userActivityRepo->registerHandler('Post', 'thread', 'thread_id');
        return parent::preDispatchController($action, $params);
    }
}