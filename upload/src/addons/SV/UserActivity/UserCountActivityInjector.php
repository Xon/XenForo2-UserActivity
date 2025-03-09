<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\UserActivity;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use function array_merge;
use function count;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use function strtolower;

/**
 * @property array countActivityInjector
 */
trait UserCountActivityInjector
{
    protected function postDispatchType($action, ParameterBag $params, AbstractReply &$reply)
    {
        // this updates the session, and occurs after postDispatchController
        parent::postDispatchType($action, $params, $reply);
        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('svUserActivity', 'viewCounters'))
        {
            return;
        }

        if ($reply instanceof ViewReply &&
            !empty($this->countActivityInjector) &&
            $reply->getResponseType() !== 'rss')
        {
            $this->_injectUserCountIntoResponse($reply, $action);
        }
    }

    protected function _injectUserCountIntoResponse(ViewReply $response, string $action): void
    {
        $fetchData = [];
        $displayOptions = \XF::options()->svUADisplayCounts ?? [];
        $actionL = strtolower($action);
        foreach (($this->countActivityInjector ?? []) as $config)
        {
            /** @var array{activeKey: string, type: string, actions: array, fetcher: string} $config */
            $key = $config['activeKey'] ?? null;
            if ($key === null || empty($displayOptions[$key]))
            {
                continue;
            }
            if (!in_array($actionL, $config['actions'] ?? [], true))
            {
                continue;
            }
            $type = $config['type'] ?? null;
            if ($type === null)
            {
                continue;
            }
            $callback = $config['fetcher'] ?? null;
            if (is_string($callback))
            {
                $callback = [$this, $callback];
            }
            if (!is_callable($callback))
            {
                continue;
            }

            $output = $callback($response, $actionL, $config);
            if (empty($output))
            {
                continue;
            }

            if (!is_array($output))
            {
                $output = [$output];
            }

            $fetchData[$type] = array_merge($fetchData[$type] ?? [], $output);
        }

        if (count($fetchData) !== 0)
        {
            UserActivityRepo::get()->insertBulkUserActivityIntoViewResponse($response, $fetchData);
        }
    }
}
