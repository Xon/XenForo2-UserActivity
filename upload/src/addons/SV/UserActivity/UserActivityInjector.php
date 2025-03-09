<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\UserActivity;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use function array_key_exists;
use function in_array;
use function strtolower;

/**
 * @property array{controller: string, id: string, type: string, actions: array<string>, activeKey: ?string} activityInjector
 */
trait UserActivityInjector
{
    protected function getSvActivityInjector(bool $display): array
    {
        $key = $this->activityInjector['activeKey'] ?? null;
        if ($key === null)
        {
            return [];
        }

        if ($display)
        {
            if (!(\XF::options()->svUADisplayUsers[$key] ?? false))
            {
                return [];
            }
        }
        else
        {
            if (!(\XF::options()->svUAPopulateUsers[$key] ?? false))
            {
                return [];
            }
        }

        if (!array_key_exists('controller', $this->activityInjector))
        {
            $this->activityInjector['controller'] = $this->rootClass;
        }

        return $this->activityInjector;
    }

    /**
     * @param $action
     * @param ParameterBag $params
     */
    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);
        $activityInjector = $this->getSvActivityInjector(false);
        $controller = $activityInjector['controller'] ?? null;
        if ($controller !== null)
        {
            UserActivityRepo::get()->registerHandler($controller, $activityInjector);
        }
    }

    /**
     * @param string $action
     * @param ParameterBag $params
     * @param AbstractReply $reply
     */
    public function postDispatch($action, ParameterBag $params, AbstractReply &$reply)
    {
        parent::postDispatch($action, $params, $reply);
        $activityInjector = $this->getSvActivityInjector(true);
        $controller = $activityInjector['controller'] ?? null;
        $actions = $activityInjector['actions'] ?? null;
        if ($controller !== null && $actions !== null)
        {
            $actionL = strtolower($action);
            if (in_array($actionL, $actions, true))
            {
                UserActivityRepo::get()->insertUserActivityIntoViewResponse($controller, $reply);
            }
        }
    }
}
