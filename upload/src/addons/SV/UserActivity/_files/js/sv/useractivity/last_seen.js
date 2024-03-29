// noinspection ES6ConvertVarToLetConst
var SV;

SV = window.SV || {};
(function()
{
    "use strict";

    function next(el, selector) {
        const nextEl = el.nextElementSibling;
        if (!selector || (nextEl && nextEl.matches(selector))) {
            return nextEl;
        }
        return null;
    }

    function mergeOptions(obj1,obj2){
        let obj3 = {};
        for (let attr1 in obj1) { obj3[attr1] = obj1[attr1]; }
        for (let attr2 in obj2) { obj3[attr2] = obj2[attr2]; }
        return obj3;
    }

    SV.UserActivityLastSeen = XF.Element.newHandler({
        options: mergeOptions(XF.TooltipOptions.base, {
            username: null,
            textTarget: '.uaLastSeenBlock'
        }),

        trigger: null,
        tooltip: null,

        init: function()
        {
            let target = this.target || this.$target.get(0);
            let targetForTrigger = this.$target || this.target;

            let tooltipOptions = XF.TooltipOptions.extractTooltip(this.options),
                triggerOptions = XF.TooltipOptions.extractTrigger(this.options);

            let dateElement = next(target, this.options.textTarget);
            dateElement.remove();
            this.dateHtml = dateElement.innerHTML;

            if (this.dateHtml) {
                var content = XF.phrase('ua_x_was_last_seen', {'{username}': this.options.username, '{date}': this.dateHtml});
                tooltipOptions.html = true;

                this.tooltip = new XF.TooltipElement(content, tooltipOptions);
                this.trigger = new XF.TooltipTrigger(targetForTrigger, this.tooltip, triggerOptions);

                this.trigger.init();
            }
        }
    });

    // register handlers
    XF.Element.register('user-activity-last-seen', 'SV.UserActivityLastSeen');
}());