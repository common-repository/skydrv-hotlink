<?php
/**
 * skydrv-hotlink/scraper.php
 *
 * This PHP module is part of a wordpress plugin.
 *
 * The main purpose of the plugin is to provide an AJAX server that
 * produces hot-download links for documents hosted on Skydrive. It
 * works by retrieving the regular download page, then scraping the
 * result for a download link.
 *
 * Right now Skydrive dynamically generates those download links.
 * Therefore, there is no known, stable download link for any file
 * hosted on Skydrive. To get a direct link, it is necessary to request
 * the download page and scrape out the generated link. That's what this
 * module does.
 *
 * Links scraped this way have a limited lifetime, but that is often "good
 * enough."
 *
 * This module provides the class that does the screen-scraping.
 *
 * --------------------------------------------
 *
 * Fri, 01 Jun 2012  17:07
 *
 ***/

// prevent direct access
if ( !function_exists('skydrv_hl_safeRedirect') ) {
    function skydrv_hl_safeRedirect($location, $replace = 1, $Int_HRC = NULL) {
        if(!headers_sent()) {
            header('location: ' . urldecode($location), $replace, $Int_HRC);
            exit;
        }
        exit('<meta http-equiv="refresh" content="4; url=' .
             urldecode($location) . '"/>');
        return;
    }
}
if(!defined('WPINC')){
    skydrv_hl_safeRedirect("http://" . $_SERVER["HTTP_HOST"]);
}

class SkydrvHotlinkScraper {

    static $defaultCacheLifetimeInMinutes = 10;

    static function getCacheDir() {
        $temp = WP_CONTENT_DIR . '/cache/';

        if ( file_exists( $temp )) {
            if (@is_dir( $temp )) {
                return $temp;
            }
            else {
                return null;
            }
        }

        if ( @mkdir( $temp ) ) {
            $stat = @stat( dirname( $temp ) );
            $dir_perms = $stat['mode'] & 0007777;
            @chmod( $temp, $dir_perms );
            return $temp;
        }

        return null;
    }

    static function getCacheLifetime() {
        $skydrv_hl_options = 'skydrv_hl_options';
        $options = get_option( $skydrv_hl_options);
        if ($option) {
            $cacheLife = intval($options['txt_cache_life']);
            return $cacheLife;
        }

        return self::$defaultCacheLifetimeInMinutes;
    }

    static function curl_get_url_contents($url) {
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_URL, $url);
        $contents = curl_exec($c);

        $resp = Array();
        $resp['status'] = curl_getinfo($c,CURLINFO_HTTP_CODE);
        curl_close($c);
        if ($contents) { $resp['contents']= $contents;}
        return $resp;
    }


    public static function linkForDocument( $docId ) {
        $f = preg_replace("/!/", "%21", $docId);
        $cacheFile = self::getCacheDir() . 'skydrv-' . $f;

        if (file_exists($cacheFile)) {
            if (filemtime($cacheFile) > (time() - 60 * self::getCacheLifetime())) {
                // The cache file is fresh.
                $fresh = file_get_contents($cacheFile);
                $resp = array( "link" => $fresh );
                return $resp;
            }
            else {
                unlink($cacheFile);
            }
        }

        // No cache or not exist or out-of-date.
        // Load the data from our remote server,
        // and also maybe cache it for next time.

        $cid = preg_replace("/!.+/i", "", $docId);
        $url = 'https://skydrive.live.com/?cid=' . $cid  . '&id=' . $docId;

        $r = self::curl_get_url_contents($url);
        if (!isset($r['status']) || ($r['status'] != '200')) {
            $resp = array( "error" =>
                           "could not retrieve skydrive document. Status - " .
                           $r['status'] );
            return $resp;
        }
        $contents = $r['contents'];

        if (preg_match("/(?<=\"download\":\")https:[^:\\?]+/i", $contents, $matches)) {
            $hotlink = preg_replace("/\\\\/i", "", $matches[0]);
            file_put_contents($cacheFile, $hotlink, LOCK_EX);
            $resp = array( "link" => $hotlink );
            return $resp;
        }

        $resp = array( "error" =>  "no match.");
        return $resp;
    }
}

?>
