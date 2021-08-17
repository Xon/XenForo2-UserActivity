<?php

namespace SV\UserActivity;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

/**
 * @property array activityInjector
 */
trait UserActivityInjector
{
    /**
     * @param bool $display
     * @return array
     */
    protected function getSvActivityInjector($display)
    {
        if (empty($this->activityInjector['controller']) ||
            empty($this->activityInjector['activeKey']))
        {
            return null;
        }

        $key = $this->activityInjector['activeKey'];
        $options = \XF::options();
        if ($display)
        {
            if (empty($options->svUADisplayUsers[$key]))
            {
                return null;
            }
        }
        else
        {
            if (empty($options->svUAPopulateUsers[$key]))
            {
                return null;
            }
        }

        return $this->activityInjector;
    }

    /**
     * @param $action
     * @param ParameterBag $params
     */
    public function preDispatch($action, ParameterBag $params)
    {
        /** @noinspection PhpUndefinedClassInspection */
        parent::preDispatch($action, $params);
        if ($activityInjector = $this->getSvActivityInjector(false))
        {
            /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
            $userActivityRepo = \XF::repository('SV\UserActivity:UserActivity');
            $userActivityRepo->registerHandler($this->activityInjector['controller'], $this->activityInjector);
        }
    }

    /**
     * @param $action
     * @param ParameterBag $params
     * @param AbstractReply $reply
     */
    public function postDispatch($action, ParameterBag $params, AbstractReply &$reply)
    {
        /** @noinspection PhpUndefinedClassInspection */
        parent::postDispatch($action, $params, $reply);
        if (($activityInjector = $this->getSvActivityInjector(true)) &&
            !empty($activityInjector['actions']))
        {
            $actionL = \strtolower($action);
            if (\in_array($actionL, $this->activityInjector['actions'], true))
            {
                /** @var \SV\UserActivity\Repository\UserActivity $userActivityRepo */
                $userActivityRepo = \XF::repository('SV\UserActivity:UserActivity');
                $userActivityRepo->insertUserActivityIntoViewResponse($this->activityInjector['controller'], $reply);
            }
        }
    }
}
