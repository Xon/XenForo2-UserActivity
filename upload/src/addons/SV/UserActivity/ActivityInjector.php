<?php

namespace SV\UserActivity;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

trait ActivityInjector
{
    /**
     * @param $action
     * @param ParameterBag $params
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        /** @var \XF\Pub\Controller\AbstractController $this */
        if (!empty($this->activityInjector['controller']) && !empty($this->activityInjector['type']))
        {
            /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
            $userActivityRepo = $this->app()->repository('SV\UserActivity:UserActivity');
            $userActivityRepo->registerHandler($this->activityInjector['controller'], $this->activityInjector['type'], $this->activityInjector['id']);
        }
        parent::preDispatchController($action, $params);
    }

    /**
     * @param $action
     * @param ParameterBag $params
     * @param AbstractReply $reply
     */
    protected function postDispatchController($action, ParameterBag $params, AbstractReply &$reply)
    {
        /** @var \XF\Pub\Controller\AbstractController $this */
        if (!empty($this->activityInjector['controller']) && !empty($this->activityInjector['actions']) && !empty($this->activityInjector['type']))
        {
            $actionL = strtolower($action);
            if (in_array($actionL, $this->activityInjector['actions'], true))
            {
                /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
                $userActivityRepo = $this->app()->repository('SV\UserActivity:UserActivity');
                $userActivityRepo->insertUserActivityIntoViewResponse($this->activityInjector['controller'], $reply);
            }
        }
        parent::postDispatchController($action, $params, $reply);
    }
}
