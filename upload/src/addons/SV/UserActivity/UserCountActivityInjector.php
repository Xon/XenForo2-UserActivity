<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\UserActivity;

use SV\UserActivity\Repository\UserActivity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;

/**
 * @property array countActivityInjector
 */
trait UserCountActivityInjector
{
    protected function postDispatchType($action, ParameterBag $params, AbstractReply &$reply)
    {
        // this updates the session, and occurs after postDispatchController
        parent::postDispatchType($action, $params, $reply);
        if ($reply instanceof ViewReply &&
            !empty($this->countActivityInjector) &&
            $reply->getResponseType() !== 'rss')
        {
            $this->_injectUserCountIntoResponse($reply, $action);
        }
    }

    protected function _injectUserCountIntoResponse(ViewReply $response, string $action)
    {
        $fetchData = [];
        $options = \XF::options();
        $actionL = \strtolower($action);
        foreach ($this->countActivityInjector as $config)
        {
            /** @var array{activeKey: string, type: string, actions: array, fetcher: string} $config */
            $key = $config['activeKey'] ?? null;
            if ($key === null || empty($options->svUADisplayCounts[$key]))
            {
                continue;
            }
            if (!\in_array($actionL, $config['actions'] ?? [], true))
            {
                continue;
            }
            $type = $config['type'] ?? null;
            if ($type === null)
            {
                continue;
            }
            $callback = $config['fetcher'] ?? null;
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

            if (!isset($fetchData[$type]))
            {
                $fetchData[$type] = [];
            }

            $fetchData[$type] = \array_merge($fetchData[$type], $output);
        }

        if ($fetchData)
        {
            /** @var UserActivity $repo */
            $repo = \XF::repository('SV\UserActivity:UserActivity');
            $repo->insertBulkUserActivityIntoViewResponse($response, $fetchData);
        }
    }
}
