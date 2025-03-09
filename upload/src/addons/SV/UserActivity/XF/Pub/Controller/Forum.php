<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Entity\Forum as ForumEntity;
use XF\Entity\Node as NodeEntity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View as ViewReply;
use XF\Tree;

/**
 * @extends \XF\Pub\Controller\Forum
 */
class Forum extends XFCP_Forum
{
    public function actionForum(ParameterBag $params)
    {
        $response = parent::actionForum($params);
        // alias forum => node, limitations of activity tracking

        if ($response instanceof ViewReply &&
            $this->responseType !== 'rss')
        {
            $forum = $response->getParam('forum');
            if ($forum instanceof ForumEntity)
            {
                UserActivityRepo::get()->pushViewUsageToParent($response, $forum->Node);
            }
        }

        return $response;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function forumFetcher(ViewReply $response, string $action, array $config): array
    {
        $forum = $response->getParam('forum');
        if ($forum instanceof ForumEntity)
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
            'actions'   => ['list'],
            'fetcher'   => 'forumListFetcher',
        ],
        [
            'activeKey' => 'index-category',
            'type'      => 'node',
            'actions'   => ['list'],
            'fetcher'   => 'categoryListFetcher',
        ],
        [
            'activeKey' => 'forum',
            'type'      => 'node',
            'actions'   => ['forum'],
            'fetcher'   => 'forumFetcher',
        ],
        [
            'activeKey' => 'sub-forum',
            'type'      => 'node',
            'actions'   => ['forum'],
            'fetcher'   => 'forumListFetcher',
        ],
        [
            'activeKey' => 'thread',
            'type'      => 'thread',
            'actions'   => ['forum'],
            'fetcher'   => 'threadFetcher'
        ],
        [
            'activeKey' => 'sticky-thread',
            'type'      => 'thread',
            'actions'   => ['forum'],
            'fetcher'   => 'stickyThreadFetcher'
        ],
    ];
    use UserCountActivityInjector;

    protected $activityInjector = [
        'type'       => 'node',
        'id'         => 'node_id',
        'actions'    => ['forum'],
        'activeKey'  => 'forum',
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
