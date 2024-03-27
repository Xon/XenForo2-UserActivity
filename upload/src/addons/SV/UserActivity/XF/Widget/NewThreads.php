<?php

namespace SV\UserActivity\XF\Widget;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\WidgetUserCountActivityInjector;
use XF\Widget\WidgetRenderer;

/**
 * @extends \XF\Widget\NewThreads
 */
class NewThreads extends XFCP_NewThreads
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