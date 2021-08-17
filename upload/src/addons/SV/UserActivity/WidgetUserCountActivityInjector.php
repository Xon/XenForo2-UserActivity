<?php

namespace SV\UserActivity;

use SV\UserActivity\Repository\UserActivity;

/**
 * @property array $options
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
        /** @noinspection PhpUndefinedClassInspection */
        $options = parent::setupOptions($options);
        return \array_replace($this->svUserActivityDefaultOptions, $options);
    }

    /**
     * @param \XF\Http\Request $request
     * @param array            $options
     * @param string|null      $error
     * @return bool
     */
    public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
    {
        /** @noinspection PhpUndefinedClassInspection */
        $result = parent::verifyOptions($request, $options, $error);

        $options['userActivity'] = $request->filter('userActivity', 'bool');

        return $result;
    }

    /**
     * @return \XF\Widget\WidgetRenderer
     */
    public function render()
    {
        /** @noinspection PhpUndefinedClassInspection */
        return $this->_injectUserCountIntoResponse(parent::render());
    }

    /**
     * @param \XF\Widget\WidgetRenderer $renderer
     * @return \XF\Widget\WidgetRenderer
     */
    protected function _injectUserCountIntoResponse(\XF\Widget\WidgetRenderer $renderer)
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
            if (!\in_array($actionL, $config['actions'], true))
            {
                continue;
            }
            */
            $callback = $config['fetcher'];
            if (\is_string($callback))
            {
                $callback = [$this, $callback];
            }
            if (!\is_callable($callback))
            {
                continue;
            }

            $output = $callback($renderer, $config);
            if (empty($output))
            {
                continue;
            }

            if (!\is_array($output))
            {
                $output = [$output];
            }

            $type = $config['type'];
            if (!isset($fetchData[$type]))
            {
                $fetchData[$type] = [];
            }

            $fetchData[$type] = \array_merge($fetchData[$type], $output);
        }

        if ($fetchData)
        {
            /** @var  UserActivity $repo */
            $repo = \XF::repository('SV\UserActivity:UserActivity');
            $repo->insertBulkUserActivityIntoViewResponse($renderer, $fetchData);
        }

        return $renderer;
    }
}
