/*
 * This file is part of a XenForo add-on.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

var SV = window.SV || {};

(function($, window, document, _undefined)
{
    "use strict";

    SV.UserActivityLastSeen = XF.Element.newHandler({
        options: $.extend(true, {}, XF.TooltipOptions.base, {
            username: null,
            textTarget: '.uaLastSeenBlock'
        }),

        trigger: null,
        tooltip: null,

        init: function()
        {
            var tooltipOptions = XF.TooltipOptions.extractTooltip(this.options),
                triggerOptions = XF.TooltipOptions.extractTrigger(this.options);

            var $dateElement = this.$target.next(this.options.textTarget);
            $dateElement.remove();
            this.dateHtml = $dateElement.html();

            if (this.dateHtml) {
                var content = XF.phrase('ua_x_was_last_seen', {'{username}': this.options.username, '{date}': this.dateHtml});
                tooltipOptions.html = true;

                this.tooltip = new XF.TooltipElement(content, tooltipOptions);
                this.trigger = new XF.TooltipTrigger(this.$target, this.tooltip, triggerOptions);

                this.trigger.init();
            }
        }
    });

    // register handlers
    XF.Element.register('user-activity-last-seen', 'SV.UserActivityLastSeen');
}(jQuery, window, document));