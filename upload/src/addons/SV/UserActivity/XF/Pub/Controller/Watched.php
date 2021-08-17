<?php

namespace SV\UserActivity\XF\Pub\Controller;


use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\View as ViewReply;

/**
 * Extends \XF\Pub\Controller\Watched
 */
class Watched extends XFCP_Watched
{
    /** @noinspection PhpUnusedParameterInspection */
    protected function threadFetcher(ViewReply $response, string $action, array $config): array
    {
        return $this->getUserActivityRepo()->getFilteredThreadIds($response->getParams(),'threads');
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function forumListFetcher(ViewReply $response, string $action, array $config): array
    {
        /** @var int[] $nodeIds */
        $watchedForums = $response->getParam('watchedForums');
        if ($watchedForums instanceof AbstractCollection)
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