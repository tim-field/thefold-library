<?php

namespace TheFold\WordPress;

trait GeoPost {

    protected $geocode_field = 'location';
    protected $geohash_field = 'geohash';
    protected $geocoded_address_field = 'address';
    protected $geohash_level = 7;

    protected $geotools;
    protected $geocoder;
    protected $formatter;
    
    function get_latlng($post_id){

        $location = $this->get_location($post_id);

        $value = null;

        if(!empty($location['lat']) && !empty($location['lng'])){
            $value = $location['lat'].','.$location['lng'];
        }

        return $value;
    }

    function get_location($post_id)
    {
        return get_post_meta($post_id,$this->geocode_field,true) ?: [];
    }

    function get_geohash($post_id, $build=false, $force=false)
    {
        $geohash = get_post_meta($post_id,$this->geohash_field,true);
            
        if($force || (!$geohash && $build)){

            if($latlng = $this->get_latlng($post_id)){

                $geohash = $this->geohash($latlng);
                update_post_meta($post_id, $this->geohash_field, $geohash); 
            };
        }

        return $geohash;
    }

    function parse_location($post_id, $raw_address, $force=false)
    {
        if($force || !$latlng = $this->get_latlng($post_id)){

            $address = $this->geocode($raw_address);
            $location = [];

            if($address){

                if($message = $address->getExceptionMessage()){
                    throw new \Exception($message);
                }

                $location['address'] = $raw_address;//this aint right
                $location['lat'] = $address->getLatitude();
                $location['lng'] = $address->getLongitude();
            }

            if($location = array_filter($location)){

                update_post_meta($post_id, $this->geocode_field, $location); 
                update_post_meta($post_id, $this->geocoded_address_field, $location['address']); 
            }
            else{
                print_r($geocode); exit();
            }
        }

        $this->get_geohash($post_id, true, $force);
    }

    function solr_geo_mapping($mapping)
    {

        $mapping['location_p'] = function($post){

            $value = null;

            if($post->post_type === self::TYPE) {

                $value = $this->get_latlng($post->ID);
            }

            return $value;
        };


        foreach(['lat','lng'] as $l){

            $mapping[$l.'_d'] = function($post) use ($l){

                $value = null;

                if($post->post_type === self::TYPE) {

                    $value = @$this->get_location($post->ID)[$l] ?: null;
                }

                return $value;
            };
        }

        for($level=1; $level <= $this->geohash_level; $level++) {

            $mapping['geohash_'.$level.'_s'] = function($post) use ($level){

                $value = null;

                if($post->post_type === self::TYPE) {

                    if($geohash = $this->get_geohash($post->ID, true)){
                        $value = substr($geohash, 0, $level);                    
                    }
                }

                return $value;
            };
        }

        return $mapping;

    }
    
    protected function geocode($address, $locale=null)
    {
        return current($this->get_geotools()->batch($this->get_geocoder($locale))->geocode($address)->serie());
    }

    protected function geohash($latlng)
    {
        $coordToGeohash = new \League\Geotools\Coordinate\Coordinate($latlng);
        return $this->get_geotools()->geohash()->encode($coordToGeohash,$this->geohash_level)->getGeohash();
    }
  
    protected function get_geotools()
    {
        if(!$this->geotools){
            $this->geotools = new \League\Geotools\Geotools();
        }

        return $this->geotools;
    }

    protected function get_formater()
    {
        if(!$this->formatter){
            $this->formatter = new \Geocoder\Formatter\Formatter();
        }

        return $this->formatter;
    }

    protected function get_geocoder($locale=null)
    {
        if(!$this->geocoder[$locale]){

            $this->geocoder[$locale] = new \Geocoder\Geocoder();

            $adapter  = new \Geocoder\HttpAdapter\CurlHttpAdapter();

            $this->geocoder[$locale]->registerProviders([
                //new \Geocoder\Provider\GoogleMapsProvider($adapter,$locale)
                //new \Geocoder\Provider\OpenStreetMapProvider($adapter,$locale)
                new \Geocoder\Provider\BingMapsProvider($adapter,'Au2mF_L2VinZkCtg2qo-5gz03auLyAdBsmr1MAakcOnTH7M9uQVpo_7WXu8ukOYs')
            ]);
        }

        return $this->geocoder[$locale];
    }

}
