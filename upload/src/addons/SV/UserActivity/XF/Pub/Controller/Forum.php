<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View as ViewReply;

/**
 * Extends \XF\Pub\Controller\Forum
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
            if ($forum instanceof \XF\Entity\Forum)
            {
                $this->getUserActivityRepo()->pushViewUsageToParent($response, $forum->Node);
            }
        }

        return $response;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function forumFetcher(ViewReply $response, string $action, array $config): array
    {
        $forum = $response->getParam('forum');
        if ($forum instanceof \XF\Entity\Forum)
        {
            return [$forum->node_id];
        }

        return [];
    }

    /**
     * @param string   $typeFilter
     * @param int      $depth
     * @param \XF\Tree $nodeTree
     * @return int[]
     */
    protected function nodeListFetcher(string $typeFilter, int $depth, \XF\Tree $nodeTree = null): array
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
        $repo = $this->getUserActivityRepo();
        $depth = $action === 'list' ? 1 : 0;
        /** @var \XF\Tree $nodeTree */
        $nodeTree = $response->getParam('nodeTree');
        return $repo->getFilteredForumNodeIds($this->nodeListFetcher('Forum', $depth, $nodeTree));
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function categoryListFetcher(ViewReply $response, string $action, array $config): array
    {
        $repo = $this->getUserActivityRepo();
        $depth = $action === 'list' ? 1 : 0;
        /** @var \XF\Tree $nodeTree */
        $nodeTree = $response->getParam('nodeTree');
        return $repo->getFilteredCategoryNodeIds($this->nodeListFetcher('Category', $depth, $nodeTree));
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function threadFetcher(ViewReply $response, string $action, array $config): array
    {
        return $this->getUserActivityRepo()->getFilteredThreadIds($response->getParams(),'threads');
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function stickyThreadFetcher(ViewReply $response, string $action, array $config): array
    {
        return $this->getUserActivityRepo()->getFilteredThreadIds($response->getParams(),'stickyThreads');
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
        'controller' => 'XF\Pub\Controller\Forum',
        'type'       => 'node',
        'id'         => 'node_id',
        'actions'    => ['forum'],
        'activeKey'  => 'forum',
    ];
    use UserActivityInjector;

    /**
     * @return \XF\Mvc\Entity\Repository|UserActivity
     */
    protected function getUserActivityRepo()
    {
        return \XF::repository('SV\UserActivity:UserActivity');
    }
}
