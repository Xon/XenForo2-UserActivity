<?php

namespace SV\UserActivity\XF\Widget;

use SV\UserActivity\Repository\UserActivity;
use SV\UserActivity\WidgetUserCountActivityInjector;

/**
 * Extends \XF\Widget\NewPosts
 */
class NewPosts extends XFCP_NewPosts
{
    /** @noinspection PhpUnusedParameterInspection */
    protected function threadFetcher(\XF\Widget\WidgetRenderer $renderer, array $config): array
    {
        return $this->getUserActivityRepo()->getFilteredThreadIds($renderer->getViewParams(), 'threads');
    }

    protected $widgetCountActivityInjector = [
        [
            'type'    => 'thread',
            'fetcher' => 'threadFetcher'
        ],
    ];
    use WidgetUserCountActivityInjector;

    /**
     * @return \XF\Mvc\Entity\Repository|UserActivity
     */
    protected function getUserActivityRepo()
    {
        return \XF::repository('SV\UserActivity:UserActivity');
    }
}