<?php

/**
 * Created by PhpStorm.
 * User: Jimmy
 * Date: 14/06/15
 * Time: 13:35
 */

use Phalcon\Cache\Backend\File as BackFile;
use Phalcon\Cache\Frontend\Data as FrontData;

class RatpService
{


    public static function GetXPathUrl($html, $regex)
    {
        $page = new DOMDocument();
        libxml_use_internal_errors(true);
        $page->loadHTML($html);
        $xpath = new DOMXPath($page);
        $query = $xpath->query($regex);
        $result = array();
        foreach ($query as $row)
            array_push($result, $row->textContent);
        return $result;
    }

    public static function GetNextMetro($line, $stationname, $destination)
    {
        $json = [];

        $url = 'http://www.ratp.fr/horaires/fr/ratp/metro/prochains_passages/PP/%s/%s/%s';
        $url_formatted = sprintf($url, urlencode($stationname), $line, $destination);
        $html = file_get_contents($url_formatted);

        $listDestination = RatpService::GetXPathUrl($html, '//*[@id=\'prochains_passages\']/fieldset/table//td');
        $json['next'] = array();
        for ($i = 0; $i < count($listDestination); $i += 2) {
            array_push($json['next'], array('terminus' => $listDestination[$i],
                                            'delay' =>  $listDestination[$i + 1]));
        }

        return $json;
    }

    public static function GetNextMetroCached($line, $stationname, $destination)
    {

        $frontCache = new FrontData(array(
            'lifetime' => '30'
        ));
        $cache = new BackFile($frontCache, array(
            'cacheDir' => '../app/cache/'
        ));

        if ($cache->get($line.$stationname.$destination) == null) {
            $json = [];

            $url = 'http://www.ratp.fr/horaires/fr/ratp/metro/prochains_passages/PP/%s/%s/%s';
            $url_formatted = sprintf($url, urlencode($stationname), $line, $destination);
            $html = file_get_contents($url_formatted);

            $listDestination = RatpService::GetXPathUrl($html, '//*[@id=\'prochains_passages\']/fieldset/table//td');
            $json['next'] = array();
            for ($i = 0; $i < count($listDestination); $i += 2) {
                    array_push($json['next'], array('terminus' => $listDestination[$i],
                                                    'delay' =>  $listDestination[$i + 1]));
            }
            if (count($json['next']) > 0)
                $cache->save($line.$stationname.$destination, $json);
        }
        else
            $json = $cache->get($line.$stationname.$destination);

        return $json;
    }

    public static function IsServiceUp()
    {
        $url = "http://www.ratp.fr/horaires/fr/ratp/metro";
        $html = file_get_contents($url);
        $error = RatpService::GetXPathUrl($html, '//*[@class="error_list"]');
        return !(count($error) > 0);
    }
}