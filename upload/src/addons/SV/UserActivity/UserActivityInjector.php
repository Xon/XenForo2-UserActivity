<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\UserActivity;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use function array_key_exists;
use function count;
use function in_array;
use function strtolower;

/**
 * @property array{controller: string, id: string, type: string, actions: array<string>, activeKey: ?string} activityInjector
 */
trait UserActivityInjector
{
    protected function getSvActivityInjector(bool $display): array
    {
        if (empty($this->activityInjector['activeKey']))
        {
            return [];
        }

        $key = $this->activityInjector['activeKey'];
        if ($display)
        {
            if (empty(\XF::options()->svUADisplayUsers[$key]))
            {
                return [];
            }
        }
        else
        {
            if (empty(\XF::options()->svUAPopulateUsers[$key]))
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
        if (count($activityInjector) !== 0)
        {
            UserActivityRepo::get()->registerHandler($this->activityInjector['controller'], $this->activityInjector);
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
        if (count($activityInjector) !== 0 &&
            !empty($activityInjector['actions']))
        {
            $actionL = strtolower($action);
            if (in_array($actionL, $this->activityInjector['actions'], true))
            {
                UserActivityRepo::get()->insertUserActivityIntoViewResponse($this->activityInjector['controller'], $reply);
            }
        }
    }
}
