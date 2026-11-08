"use strict";

window.addEventListener("load", (event) => {
    /**
     * Generate page preview
     */
    html2canvas(
        document.querySelector('body'),
        {
            'logging' : false,
            'x'       : window.scrollX,
            'y'       : window.scrollY,
            'width'   : window.innerWidth,
            'height'  : window.innerHeight,
        }
    )
    .then(canvas => {
        var data = new URLSearchParams();
        data.append('preview', canvas.toDataURL());
        data.append('page', wishthis.$_GET.page);

        /**
         * Save page preview
         *
         * This happens in the background, so a failure is not shown. During
         * an update the API answers with the maintenance page, which would
         * otherwise end up in an error dialog.
         */
        fetch('/index.php?page=api&module=save-preview', {
            method      : 'POST',
            credentials : 'include',
            body        : data,
        })
        .catch(function() {});
    })
    .catch(function() {});
});
