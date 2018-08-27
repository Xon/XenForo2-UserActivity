<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserActivityInjector;
use SV\UserActivity\UserCountActivityInjector;
use XF\Entity\Node;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

/**
 * Extends \XF\Pub\Controller\Category
 */
class Category extends XFCP_Category
{
    public function actionIndex(ParameterBag $params)
    {
        $response = parent::actionIndex($params);
        // alias forum => node, limitations of activity tracking
        if ($response instanceof View &&
            $this->responseType !== 'rss' &&
            ($category = $response->getParam('category')))
        {
            /** @var \XF\Entity\Category $category */
            if ($response->getParam('node') === null)
            {
                $response->setParam('node', $category->Node);
            }

            $session = \XF::session();
            $robotKey = $session->isStarted() ? $session->get('robotId') : true;
            $options = \XF::options();
            if (empty($options->svUAPopulateUsers['sub-forum']))
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
            $node = $category->Node;
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

    protected $countActivityInjector = [
        [
            'activeKey' => 'sub-forum',
            'type'      => 'node',
            'actions'   => ['index'],
            'fetcher'   => 'forumListFetcher',
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
