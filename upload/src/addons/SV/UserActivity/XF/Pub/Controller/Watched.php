<?php

namespace SV\UserActivity\XF\Pub\Controller;


use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\View;

/**
 * Extends \XF\Pub\Controller\Watched
 */
class Watched extends XFCP_Watched
{
    protected function threadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        return $this->getUserActivityRepo()->getFilteredThreadIds($response->getParams(),'threads');
    }

    protected function forumListFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        /** @var int[] $nodeIds */
        /** @var AbstractCollection $watchedForums */
        if ($watchedForums = $response->getParam('watchedForums'))
        {
            $nodeIds = \array_column($watchedForums->toArray(), 'node_id');
        }
        else
        {
            $nodeIds = [];
        }

        return $this->getUserActivityRepo()->getFilteredForumNodeIds($nodeIds);
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'watched-threads',
            'type'      => 'thread',
            'actions'   => ['threads'],
            'fetcher'   => 'threadFetcher'
        ],
        [
            'activeKey' => 'watched-forums',
            'type'      => 'node',
            'actions'   => ['forums'],
            'fetcher'   => 'forumListFetcher'
        ],
    ];
    use UserCountActivityInjector;

    /**
     * @return \XF\Mvc\Entity\Repository|UserActivity
     */
    protected function getUserActivityRepo()
    {
        return \XF::repository('SV\UserActivity:UserActivity');
    }
}