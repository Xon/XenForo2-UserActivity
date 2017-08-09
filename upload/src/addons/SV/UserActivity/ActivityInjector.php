<?php

namespace SV\UserActivity;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

trait ActivityInjector
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        if (!empty($this->activityInjector['controller']))
        {
            $userActivityRepo = $this->app->repository('SV\UserActivity\Repository\UserActivity');
            $userActivityRepo->registerHandler($this->activityInjector['controller'], $this->activityInjector['type'], $this->activityInjector['id']);
        }
        return parent::preDispatchController($action, $params);
    }

    protected function postDispatchController($action, ParameterBag $params, AbstractReply &$reply)
    {
        if (!empty($this->activityInjector['controller']) && !empty($this->activityInjector['actions']))
        {
            $actionL = strtolower($action);
            if (in_array($actionL, $this->activityInjector['actions'], true))
            {
                $userActivityRepo = $this->app->repository('SV\UserActivity\Repository\UserActivity');
                $userActivityRepo->insertUserActivityIntoViewResponse($this->activityInjector['controller'], $reply);
            }
        }
        return parent::postDispatchController($action, $params, $reply);
    }
}