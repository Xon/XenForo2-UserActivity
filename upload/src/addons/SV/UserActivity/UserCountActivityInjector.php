<?php

namespace SV\UserActivity;

use SV\UserActivity\Repository\UserActivity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View;

/**
 * @property array countActivityInjector
 */
trait UserCountActivityInjector
{
    protected function postDispatchType($action, ParameterBag $params, AbstractReply &$reply)
    {
        // this updates the session, and occurs after postDispatchController
        /** @noinspection PhpUndefinedClassInspection */
        parent::postDispatchType($action, $params, $reply);
        if ($reply instanceof View &&
            !empty($this->countActivityInjector) &&
            $reply->getResponseType() !== 'rss')
        {
            $this->_injectUserCountIntoResponse($reply, $action);
        }
    }

    /**
     * @param View   $response
     * @param string $action
     */
    protected function _injectUserCountIntoResponse($response, $action)
    {
        $fetchData = [];
        $options = \XF::options();
        $actionL = \strtolower($action);
        foreach ($this->countActivityInjector as $config)
        {
            if (empty($options->svUADisplayCounts[$config['activeKey']]))
            {
                continue;
            }
            if (!\in_array($actionL, $config['actions'], true))
            {
                continue;
            }
            $callback = $config['fetcher'];
            if (\is_string($callback))
            {
                $callback = [$this, $callback];
            }
            if (!\is_callable($callback))
            {
                continue;
            }

            $output = $callback($response, $actionL, $config);
            if (empty($output))
            {
                continue;
            }

            if (!\is_array($output))
            {
                $output = [$output];
            }

            $type = $config['type'];
            if (!isset($fetchData[$type]))
            {
                $fetchData[$type] = [];
            }

            $fetchData[$type] = \array_merge($fetchData[$type], $output);
        }

        if ($fetchData)
        {
            /** @var  UserActivity $repo */
            $repo = \XF::repository('SV\UserActivity:UserActivity');
            $repo->insertBulkUserActivityIntoViewResponse($response, $fetchData);
        }
    }
}
