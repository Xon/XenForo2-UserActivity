<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\UserCountActivityInjector;
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
            $this->getUserActivityRepo()->pushViewUsageToParent($response, $category->Node, ['forum', 'category']);
        }

        return $response;
    }

    protected function categoryFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)
    {
        /** @var \XF\Entity\Category $category */
        if ($category = $response->getParam('category'))
        {
            return $category->node_id;
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

        /** @var int[] $nodes */
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

    protected $countActivityInjector = [
        [
            'activeKey' => 'sub-forum',
            'type'      => 'node',
            'actions'   => ['index'],
            'fetcher'   => 'forumListFetcher',
        ],
        [
            'activeKey' => 'category-view',
            'type'      => 'node',
            'actions'   => ['index'],
            'fetcher'   => 'categoryFetcher'
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
