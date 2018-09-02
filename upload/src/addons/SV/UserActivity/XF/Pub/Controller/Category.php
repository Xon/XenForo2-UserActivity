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
            $this->getUserActivityRepo()->pushViewUsageToParent($response, $category->Node, false, ['forum', 'category']);
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

    /**
     * @param string   $typeFilter
     * @param int      $depth
     * @param \XF\Tree $nodeTree
     * @return int[]
     */
    protected function nodeListFetcher($typeFilter, $depth, \XF\Tree $nodeTree = null)
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

    protected function forumListFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        $repo = $this->getUserActivityRepo();
        $depth = $action === 'list' ? 1 : 0;
        /** @var \XF\Tree $nodeTree */
        $nodeTree = $response->getParam('nodeTree');
        return $repo->getFilteredForumNodeIds($this->nodeListFetcher('Forum', $depth, $nodeTree));
    }

    protected function categoryListFetcher(
        /** @noinspection PhpUnusedParameterInspection */
        View $response,
        $action,
        array $config)

    {
        $repo = $this->getUserActivityRepo();
        $depth = $action === 'list' ? 1 : 0;
        /** @var \XF\Tree $nodeTree */
        $nodeTree = $response->getParam('nodeTree');
        return $repo->getFilteredCategoryNodeIds($this->nodeListFetcher('Category', $depth, $nodeTree));
    }

    protected $countActivityInjector = [
        [
            'activeKey' => 'sub-forum',
            'type'      => 'node',
            'actions'   => ['index'],
            'fetcher'   => 'forumListFetcher',
        ],
        [
            'activeKey' => 'sub-forum',
            'type'      => 'node',
            'actions'   => ['index'],
            'fetcher'   => 'categoryListFetcher'
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
