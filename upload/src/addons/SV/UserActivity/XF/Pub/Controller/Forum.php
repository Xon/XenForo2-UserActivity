<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Entity\Node;
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
            if ($response->getParam('node') === null)
            {
                $response->setParam('node', $forum->Node);
            }

            $session = \XF::session();
            $robotKey = $session->isStarted() ? $session->get('robotId') : true;
            $options = \XF::options();
            if (empty($options->svUAPopulateUsers['forum']))
            {
                return $response;
            }
            if (!$options->SV_UA_TrackRobots && $robotKey)
            {
                return $response;
            }

            $nodeTrackLimit = intval($options->svUAThreadNodeTrackLimit);
            $nodeTrackLimit = $nodeTrackLimit < 0 ? PHP_INT_MAX : $nodeTrackLimit;

            /** @var  UserActivity $repo */
            $repo = \XF::repository('SV\UserActivity:UserActivity');
            $node = $forum->Node;
            if ($nodeTrackLimit > 0)
            {
                $count = 1;
                if ($node->breadcrumb_data)
                {
                    foreach ($node->breadcrumb_data AS $crumb)
                    {
                        if ($crumb['node_type_id'] === 'Forum')
                        {
                            $repo->bufferTrackViewerUsage('node', $crumb['node_id'], 'forum');
                            $count++;
                            if ($count > $nodeTrackLimit)
                            {
                                break;
                            }
                        }
                    }
                }
            }
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

        /** @var \XF\Tree $nodeTree */
        if ($nodeTree = $response->getParam('nodeTree'))
        {
            /** @var Node[] $nodes */
            $nodes = $repo->flattenTreeToDepth($nodeTree, $action === 'list' ? 1 : 0);
        }
        else
        {
            $nodes = [];
        }

        return $repo->getFilteredNodeIds($nodes);
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
