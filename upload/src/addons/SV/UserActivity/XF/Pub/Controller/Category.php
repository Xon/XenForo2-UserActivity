<?php

namespace SV\UserActivity\XF\Pub\Controller;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\UserCountActivityInjector;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View as ViewReply;
use XF\Tree;

/**
 * Extends \XF\Pub\Controller\Category
 */
class Category extends XFCP_Category
{
    public function actionIndex(ParameterBag $params)
    {
        $response = parent::actionIndex($params);
        // alias forum => node, limitations of activity tracking
        if ($response instanceof ViewReply &&
            $this->responseType !== 'rss')
        {
            $category = $response->getParam('category');
            if ($category instanceof \XF\Entity\Category)
            {
                $this->getUserActivityRepo()->pushViewUsageToParent($response, $category->Node, false, ['forum', 'category']);
            }
        }

        return $response;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function categoryFetcher(ViewReply $response, string $action, array $config): array
    {
        $category = $response->getParam('category');
        if ($category instanceof \XF\Entity\Category)
        {
            return [$category->node_id];
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
        $repo = $this->getUserActivityRepo();
        $depth = $action === 'list' ? 1 : 0;
        /** @var Tree $nodeTree */
        $nodeTree = $response->getParam('nodeTree');

        return $repo->getFilteredForumNodeIds($this->nodeListFetcher('Forum', $depth, $nodeTree));
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function categoryListFetcher(ViewReply $response, string $action, array $config): array
    {
        $repo = $this->getUserActivityRepo();
        $depth = $action === 'list' ? 1 : 0;
        /** @var Tree $nodeTree */
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

    protected function getUserActivityRepo(): UserActivityRepo
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('SV\UserActivity:UserActivity');
    }
}
