// skydrv-hotlink/z.js
// ------------------------------------------------------------------
//
// Browser-side logic for replacing skydrive links with direct-download
// links. This logic invokes the admin-ajax.php with the correct
// action parameter for the skydrv-hotlink plugin.
//
// created: Fri Jun 01 17:45:33 2012
// last saved: <2012-July-06 11:52:52>
// ------------------------------------------------------------------
//
// Copyright © 2012 Dino Chiesa
// All rights reserved.
//
// ------------------------------------------------------------------

/*jslint browser:true */
/*global jQuery:false, SkydrvHotPlg:false */

(function(globalScope) {
    'use strict';
    var $ = jQuery;
    function hotlink(id, cb) {
        // SkydrvHotPlg is defined in a script block, emitted
        // separately and previously by skydrv-hotlink.php  .
        var url = SkydrvHotPlg.ajaxurl +
            "?action=" + SkydrvHotPlg.action +
            "&docid=" + id +
            "&token=" + SkydrvHotPlg.token +
            "&nonce=" + SkydrvHotPlg.nonce;

        $.ajax({type: "GET",
                url: url,
                headers : { "Accept" : 'application/json' },
                dataType: "json",
                cache: false,
                error: function (xhr, textStatus, errorThrown) {
                    cb({ error: errorThrown || textStatus, source: "xhr"});
                },
                success: function (data, textStatus, xhr) {
                    cb(data);
                }
               });
    }

    function documentReady() {
        // examples of href's
        // https://skydrive.live.com/redir?resid=842434EBE9688900!1123
        // https://skydrive.live.com/?id=842434EBE9688900!1123
        // https://skydrive.live.com/?id=842434EBE9688900!1123&cid=842434ebe9688900#
        // https://skydrive.live.com/?cid=ffe36f4ad6e1759e&id=FFE36F4AD6E1759E%21150
        var re1 = new RegExp("https://[^/]+/\\?id=([^&]+)"),
            re2 = new RegExp("https://[^/]+/.*?&id=([^&]+)"),
            re3 = new RegExp("https://[^/]+/\\?cid=([^&]+)"),
            re4 = new RegExp("https://[^/]+/redir\\?resid=([^&]+)");
        $("a.skydrive-hotlink").each(function (ix) {
            var $elem = $(this),
                href = $elem.attr('href'),
                m0 = re1.exec(href) || re2.exec(href) || re3.exec(href) || re4.exec(href);
            if (m0) {
                hotlink(m0[1], function(data) {
                    if (data.link) {
                        $elem.attr('href', data.link);
                    }
                });
            }
        });
    }

    $(document).ready(documentReady);

}(this));

