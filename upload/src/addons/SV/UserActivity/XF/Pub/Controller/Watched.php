<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Reply\View as ViewReply;
use function array_column;

/**
 * Extends \XF\Pub\Controller\Watched
 */
class Watched extends XFCP_Watched
{
    /** @noinspection PhpUnusedParameterInspection */
    protected function threadFetcher(ViewReply $response, string $action, array $config): array
    {
        return UserActivityRepo::get()->getFilteredThreadIds($response->getParams(),'threads');
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function forumListFetcher(ViewReply $response, string $action, array $config): array
    {
        /** @var int[] $nodeIds */
        $watchedForums = $response->getParam('watchedForums');
        if ($watchedForums instanceof AbstractCollection)
        {
            $nodeIds = array_column($watchedForums->toArray(), 'node_id');
        }
        else
        {
            $nodeIds = [];
        }

        return UserActivityRepo::get()->getFilteredForumNodeIds($nodeIds);
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
}