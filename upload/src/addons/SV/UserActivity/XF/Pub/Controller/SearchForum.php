<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Entity\SearchForum as SearchForumEntity;
use XF\Entity\Node as NodeEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Tree;

/**
 * @extends \XF\Pub\Controller\SearchForum
 */
class SearchForum extends XFCP_SearchForum
{
    public function actionView(ParameterBag $params)
    {
        $response = parent::actionView($params);
        // alias forum => node, limitations of activity tracking

        if ($response instanceof ViewReply &&
            $this->responseType !== 'rss')
        {
            $searchForum = $response->getParam('searchForum');
            if ($searchForum instanceof SearchForumEntity)
            {
                UserActivityRepo::get()->pushViewUsageToParent($response, $searchForum->Node);
            }
        }

        return $response;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function forumFetcher(ViewReply $response, string $action, array $config): array
    {
        $forum = $response->getParam('forum');
        if ($forum instanceof SearchForumEntity)
        {
            return [$forum->node_id];
        }

        return [];
    }

    /**
     * @param string    $typeFilter
     * @param int       $depth
     * @param Tree|null $nodeTree
     * @return int[]
     */
    protected function nodeListFetcher(string $typeFilter, int $depth, ?Tree $nodeTree = null): array
    {
        $nodeIds = [];
        $flattenedNodeList = $nodeTree ? $nodeTree->getFlattened() : [];
        foreach ($flattenedNodeList as $id => $node)
        {
            if ($node['depth'] <= $depth && $node['record']->node_type_id === $typeFilter)
            {
                $nodeIds[] = $id;
            }
        }

        return $nodeIds;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function forumListFetcher(ViewReply $response, string $action, array $config): array
    {
        $repo = UserActivityRepo::get();
        $depth = $action === 'list' ? 1 : 0;
        /** @var Tree $nodeTree */
        $nodeTree = $response->getParam('nodeTree');
        return $repo->getFilteredForumNodeIds($this->nodeListFetcher('Forum', $depth, $nodeTree));
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function categoryListFetcher(ViewReply $response, string $action, array $config): array
    {
        $repo = UserActivityRepo::get();
        $depth = $action === 'list' ? 1 : 0;
        /** @var Tree $nodeTree */
        $nodeTree = $response->getParam('nodeTree');
        return $repo->getFilteredCategoryNodeIds($this->nodeListFetcher('Category', $depth, $nodeTree));
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function threadFetcher(ViewReply $response, string $action, array $config): array
    {
        return UserActivityRepo::get()->getFilteredThreadIds($response->getParams(),'threads');
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function stickyThreadFetcher(ViewReply $response, string $action, array $config): array
    {
        return UserActivityRepo::get()->getFilteredThreadIds($response->getParams(),'stickyThreads');
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'index-forum',
            'type'      => 'node',
            'actions'   => ['view'],
            'fetcher'   => 'forumListFetcher',
        ],
        [
            'activeKey' => 'index-category',
            'type'      => 'node',
            'actions'   => ['view'],
            'fetcher'   => 'categoryListFetcher',
        ],
        [
            'activeKey' => 'search-forum',
            'type'      => 'node',
            'actions'   => ['view'],
            'fetcher'   => 'forumFetcher',
        ],
        [
            'activeKey' => 'sub-forum',
            'type'      => 'node',
            'actions'   => ['view'],
            'fetcher'   => 'forumListFetcher',
        ],
        [
            'activeKey' => 'thread',
            'type'      => 'thread',
            'actions'   => ['view'],
            'fetcher'   => 'threadFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected $activityInjector = [
        'type'       => 'node',
        'id'         => 'node_id',
        'actions'    => ['view'],
        'activeKey'  => 'search-forum',
    ];
    use UserActivityInjector;

    protected function updateSessionActivity($action, ParameterBag $params, AbstractReply &$reply)
    {
        if ($reply instanceof ViewReply &&
            $params->get('node_id') === null)
        {
            $node = $reply->getParam('node');
            if ($node instanceof NodeEntity)
            {
                $params['node_id'] = $node->node_id;
            }
        }

        parent::updateSessionActivity($action, $params, $reply);
    }
}