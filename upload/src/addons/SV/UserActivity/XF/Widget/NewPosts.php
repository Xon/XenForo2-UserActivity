<?php

namespace SV\UserActivity\XF\Widget;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\WidgetUserCountActivityInjector;
use XF\Widget\WidgetRenderer;

/**
 * Extends \XF\Widget\NewPosts
 */
class NewPosts extends XFCP_NewPosts
{
    /** @noinspection PhpUnusedParameterInspection */
    protected function threadFetcher(WidgetRenderer $renderer, array $config): array
    {
        return UserActivityRepo::get()->getFilteredThreadIds($renderer->getViewParams(), 'threads');
    }

    protected $widgetCountActivityInjector = [
        [
            'type'    => 'thread',
            'fetcher' => 'threadFetcher'
        ],
    ];
    use WidgetUserCountActivityInjector;
}