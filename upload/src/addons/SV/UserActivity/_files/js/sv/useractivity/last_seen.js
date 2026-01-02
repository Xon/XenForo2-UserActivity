// noinspection ES6ConvertVarToLetConst
var SV = window.SV || {};
// XF22 compat shim
SV.$ = SV.$ || window.jQuery || null;
SV.extendObject = SV.extendObject || XF.extendObject || jQuery.extend;

(function() {
    "use strict";
    const $ = SV.$,
        xf22 = typeof XF.on !== 'function';

    /**
     *
     * @param {HTMLElement} el
     * @param {string} selector
     * @return {Element|null}
     */
    function next(el, selector) {
        const nextEl = el.nextElementSibling;
        if (!selector || (nextEl && nextEl.matches(selector))) {
            return nextEl;
        }
        return null;
    }

    /**
     * @return {HTMLElement}
     */
    function getTarget(handler) {
        // noinspection JSUnresolvedReference
        return handler.target || handler.$target.get(0);
    }

    SV.UserActivityLastSeen = XF.Element.newHandler({
        options: SV.extendObject({}, XF.TooltipOptions.base, {
            username: null,
            textTarget: '.uaLastSeenBlock'
        }),

        dateHtml: null,
        trigger: null,
        tooltip: null,

        init: function()
        {
            const target = getTarget(this),
                dateElement = next(target, this.options.textTarget);
            if (!dateElement) {
                return
            }
            dateElement.remove();
            this.dateHtml = dateElement.innerHTML;

            if (this.dateHtml) {
                const content = XF.phrase('ua_x_was_last_seen', {'{username}': this.options.username, '{date}': this.dateHtml}),
                    tooltipOptions = XF.TooltipOptions.extractTooltip(this.options),
                    triggerOptions = XF.TooltipOptions.extractTrigger(this.options);
                tooltipOptions.html = true;

                this.tooltip = new XF.TooltipElement(content, tooltipOptions);
                this.trigger = new XF.TooltipTrigger(xf22 ? $(target) : target, this.tooltip, triggerOptions);

                this.trigger.init();
            }
        }
    });

    // register handlers
    XF.Element.register('user-activity-last-seen', 'SV.UserActivityLastSeen');
}());