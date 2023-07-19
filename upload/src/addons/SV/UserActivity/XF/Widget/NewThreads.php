<?php

namespace SV\UserActivity\XF\Widget;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use SV\UserActivity\WidgetUserCountActivityInjector;
use XF\Widget\WidgetRenderer;

/**
 * Extends \XF\Widget\NewThreads
 */
class NewThreads extends XFCP_NewThreads
{
    /** @noinspection PhpUnusedParameterInspection */
    protected function threadFetcher(WidgetRenderer $renderer, array $config): array
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

    protected function getUserActivityRepo(): UserActivityRepo
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('SV\UserActivity:UserActivity');
    }
}