<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace SV\UserActivity;

use SV\UserActivity\Repository\UserActivity as UserActivityRepo;
use XF\Http\Request;
use XF\Widget\WidgetRenderer;
use function array_merge;
use function array_replace;
use function is_array;
use function is_callable;
use function is_string;

/**
 * @property array{userActivity: bool} $options
 * @property array $widgetCountActivityInjector
 */
trait WidgetUserCountActivityInjector
{
    protected $svUserActivityDefaultOptions = [
        'userActivity' => true,
    ];

    /**
     * @param array $options
     * @return array
     */
    protected function setupOptions(array $options)
    {
        $options = parent::setupOptions($options);

        return array_replace($this->svUserActivityDefaultOptions, $options);
    }

    /**
     * @param Request $request
     * @param array            $options
     * @param string|null      $error
     * @return bool
     */
    public function verifyOptions(Request $request, array &$options, &$error = null)
    {
        $result = parent::verifyOptions($request, $options, $error);

        $options['userActivity'] = $request->filter('userActivity', 'bool');

        return $result;
    }

    /**
     * @return WidgetRenderer
     */
    public function render()
    {
        return $this->_injectUserCountIntoResponse(parent::render());
    }

    protected function _injectUserCountIntoResponse(WidgetRenderer $renderer): WidgetRenderer
    {
        if (empty($this->widgetCountActivityInjector) || empty($this->options['userActivity']))
        {
            return $renderer;
        }

        $fetchData = [];
        //$options = \XF::options();
        foreach ($this->widgetCountActivityInjector as $config)
        {
            /*
            if (empty($options->svUADisplayCounts[$config['activeKey']]))
            {
                continue;
            }
            if (!in_array($actionL, $config['actions'], true))
            {
                continue;
            }
            */
            /** @var array{type:string, fetcher: string|callable} $config */
            $type = $config['type'] ?? null;
            if ($type === null)
            {
                continue;
            }
            $callback = $config['fetcher'] ?? null;
            if (is_string($callback))
            {
                $callback = [$this, $callback];
            }
            if (!is_callable($callback))
            {
                continue;
            }

            $output = $callback($renderer, $config);
            if (empty($output))
            {
                continue;
            }

            if (!is_array($output))
            {
                $output = [$output];
            }

            $fetchData[$type] = array_merge($fetchData[$type] ?? [], $output);
        }

        if (count($fetchData) !== 0)
        {
            UserActivityRepo::get()->insertBulkUserActivityIntoViewResponse($renderer, $fetchData);
        }

        return $renderer;
    }
}
