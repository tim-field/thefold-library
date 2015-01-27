<?php

namespace TheFold\Page\Component;

class SpatialClusterFacet extends Facet{

    protected $stats;
    protected $markers;
    protected $field = 'location_p';
    protected $config = []; 
    protected $version = 2;

    function __construct($config=[]){
       $this->config = $config; 
    }
  
    function get_js_path()
    {
        return trim($this->plugin_url,'/').'/js/components/'.$this->get_name().'.js';
    }

    function get_js_handle()
    {
        return 'SpatialClusterFacet'; 
    }
    
    function get_js_deps(){

        $deps = [
            'jquery',
            'underscore',
            'map-location',
            'acf-map',
        ];
        
        if(wp_script_is('mapstyle', 'registered')){
            $deps[] = 'mapstyle'; 
        }

        return $deps;
    }

    function get_js_config(){

        $config = [
            'selector' => '.acf-map',
            'name' => $this->get_js_handle()
        ];

        if(isset($this->config['singleMarkerIcon'])){
            $config['singleMarkerIcon'] = $this->config['singleMarkerIcon'];
        }

        if(isset($this->config['markerCountUrl'])){
            $config['markerCountUrl'] = $this->config['markerCountUrl'];
        }

        if(wp_script_is('mapstyle', 'registered')){

            $config['styles']='MapStyle';
        }
        
        return $config;
    }
    
    function get_facet(){

        if(!$this->facet){

            $level = 1;

            if(isset($_GET[$this->get_name()]['geohash'])) {
                $level = strlen($_GET[$this->get_name()]['geohash']);
            }
            elseif(isset($_GET['ResultList']['geohash'])){
                $level = 6; // Hack here.  So when clicking on result list this'll be pased a full geohash ( 7 chars )
                // we need to display facets at 6 chars so this marker continues to show. 
            }
            elseif(isset($_GET[$this->get_name()]['zoom'])) { //could use bounds for this instead
                $level = $this->zoom_to_geohash_length((int) $_GET[$this->get_name()]['zoom']);  
            }

            // only makes sense to facet to at most one level below gehash max - I think
            $facet_field = 'geohash_'.min($level + 1, 6).'_s';

            $this->facet = new \TheFold\FastPress\Solr\Facet\Geohash($facet_field,'Geohash');
        }

        return $this->facet;
    }

    /**
     * Sets the solr query parameter
     */
    protected function set_query_value($query){

        $set_stats = false;
        
        if(isset($_GET[$this->get_name()]['geohash'])){

            $query['facets'][$this->facet->get_filter_name()] = $_GET[$this->get_name()]['geohash'];
            $set_stats = true;
        }
        elseif(isset($_GET[$this->get_name()]['bounds'])){

            $query['bounds'][$this->field] = urldecode($_GET[$this->get_name()]['bounds']);
            $set_stats = true;
        }
        
        if($set_stats){

            $query['stats'] = [
                'fields' => ['lat_d','lng_d'],
                'facets' => [ $this->facet->get_name() ],
            ];
        }

        return $query;
    }

    function render($view_params=[], $partial='partials/map')
    {
        \TheFold\Locations\map([], $partial, $view_params);
    }

    protected function zoom_to_geohash_length($zoom)
    {
        /*if ($zoom <= 5)  return 1;
        elseif ($zoom <= 7) return 2;
        elseif ($zoom <= 9) return 3;
        elseif ($zoom <= 10) return 4;
        elseif ($zoom <= 15) return 5;
        else return 6;*/
        $length = 6;

        if ($zoom <= 4) $length = 0;
        else if ($zoom <= 5) $length = 1;
        else if ($zoom <= 8) $length = 2;
        else if ($zoom <= 10) $length = 3;
        else if ($zoom <= 12) $length = 4;
        else if ($zoom <= 13) $length = 6;//was 5
        
        return $length;
    }
    
    function json()
    {
        $markers = [];

        if($this->facet_values)
        {

            if($statsResult = \FastPress\get_stats())
            {
                //Aggregates average lat and lng fields for each marker, see set_query_value function
                //above which initaites the stats component on the geohash facet
                foreach(['lat_d'=>'lat','lng_d'=>'lng'] as $solr_field => $field) {

                    $facetStats = $statsResult->getResult($solr_field)->getFacets();

                    foreach($facetStats as $geohash_field => $facetValue){

                        foreach($facetValue as $geohash => $facetValue){

                            if(empty($geohash)) continue;

                            $markers[$geohash][$field] = $facetValue->getMean();
                        }
                    }
                }
            }


            foreach($this->facet_values as $geohash => $count){

                $markers[$geohash]['post_id'] = $geohash.'-'.$count;
                $markers[$geohash]['level'] = strlen($geohash);
                $markers[$geohash]['count'] = $count;
                $markers[$geohash]['geohash'] = $geohash;
            }
        }

        return array_values($markers);        
    }
    
    protected function get_query_value()
    {
        if(isset($_GET[$this->get_name()]['geohash'])){

            return urldecode($_GET[$this->get_name()]['geohash']);
        }
    }
    
    /*protected function set_facet_values($facet_values)
    {
        $values = $this->preg_grep_keys('/geohash_[0-9+]_s/',$facet_values);
        return $this->facet_values = current($values) ?: [];
    }
    
    protected function preg_grep_keys($pattern, $input, $flags = 0) {
        return array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags)));
    }*/

    static function create_marker($count, $params=[])
    {
        header('Pragma: public');
        header('Cache-Control: max-age=86400');
        header('Expires: '. gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
        header("Content-type: image/png");
        
        $count = intval($count);

        // get a value between 1 and 5 for the different marker sizes
        $image_number = min(max(round($count / 30),1),5);

        $dir = isset($params['imagedir']) ? $params['imagedir'] : get_stylesheet_directory().'/images/cluster/';
        $cachedir = isset($params['cachedir']) ? $params['cachedir'] : $dir.'cache/';
        $cache = $count.'.png';
        $imagecache = $cachedir.$cache;
        
        if(file_exists($imagecache) && !isset($params['nocache'])) {
            readfile($imagecache);
        }
        else{
            
            $image = isset($params['imagename']) ? sprintf($params['imagename'],$image_number) : 'm'.$image_number.'.png';
            $imagepath = $dir.$image;
            
            $font = isset($params['font']) ? $params['font'] : 'OpenSans-Semibold.ttf';
            $fontsize = (isset($params['fontsize']) ? $params['fontsize'] : 12);// + $image_number;
            $text = $count;

            $fontpath = $dir.$font;

            $im = imagecreatefrompng( $imagepath );

            if(!$im){
                throw new \Exception('Unable to read path '.$imagepath);
            }

            imagesavealpha($im, true);

            // find the size of the image
            $xi = imagesx($im);
            $yi = imagesy($im);

            // find the size of the text
            $box = imagettfbbox($fontsize, 0, $fontpath, $text);
            $xr = abs(max($box[2], $box[4]));
            $yr = abs(max($box[5], $box[7]));

            // compute centering
            $x = round(($xi - $xr) / 2);
            $y = round(($yi + $yr) / 2);

            $color = imagecolorallocate($im, 255, 255, 255);

            imagettftext($im,$fontsize,0,$x,$y,$color,$fontpath, $text);

            //output
            imagepng($im);
            //create cache
            imagepng($im, $imagecache);
            imagedestroy($im);
        }
    }

}
