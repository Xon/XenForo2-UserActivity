<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

/**
 * Extends \XF\Pub\Controller\Forum
 */
class Forum extends XFCP_Forum
{
    public function actionForum(ParameterBag $params)
    {
        $response = parent::actionForum($params);
        // alias forum => node, limitations of activity tracking

        if ($response instanceof View &&
            $this->responseType !== 'rss' &&
            ($forum = $response->getParam('forum')))
        {
            /** @var \XF\Entity\Forum $forum */
            $this->getUserActivityRepo()->pushViewUsageToParent($response, $forum->Node);
        }

        return $response;
    }

    protected function forumFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)
    {
        /** @var \XF\Entity\Forum $forum */
        if ($forum = $response->getParam('forum'))
        {
            return $forum->node_id;
        }

        return null;
    }

    protected function forumListFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        $repo = $this->getUserActivityRepo();

        /** @var int[] $nodeIds */
        /** @var \XF\Tree $nodeTree */
        if ($nodeTree = $response->getParam('nodeTree'))
        {
            $nodeIds = $repo->flattenTreeToDepth($nodeTree, $action === 'list' ? 1 : 0);
        }
        else
        {
            $nodeIds = [];
        }

        return $repo->getFilteredNodeIds($nodeIds);
    }

    protected function threadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        return $this->getUserActivityRepo()->getFilteredThreadIds($response->getParams(),'threads');
    }

    protected function stickyThreadFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

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
