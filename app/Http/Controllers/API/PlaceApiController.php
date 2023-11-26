<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class PlaceApiController extends Controller
{
    public function __construct() {
        $this->apiKey = 'AIzaSyB0NjOojsd5vBIHLXhI0TTp3Tec95rmAds'; // google api key
        $this->url = 'https://maps.googleapis.com/maps/api/place/';
        $this->type = 'restaurant'; // type search
    }
    
    // this function for search geometry by name place
    public function search($searchPlace = 'bangsue') {

        if($searchPlace == "null") {
            $searchPlace = 'bangsue';
        }

        //set cache key
        $cacheKey = $searchPlace;

        // check cache kay return cache if have it
        if(Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $endpoint = 'textsearch/json?query='.$searchPlace;
        $response = $this->call($endpoint);
        $place = $this->status($response['status']) ? collect($response['results']) : false;

        $restaurants = $place ? $this->nearbysearch($place->first()) : false;
        $data = $restaurants ? $this->buildData($restaurants) : false;

        // put data to cache
        Cache::put($cacheKey, $data, now()->addDays(1));

        return response()->json($data);
    }

    // this function for get data next page
    public function nextPage($pageToken) {

        //set cache key
        $cacheKey = $pageToken;

        // check cache kay return cache if have it
        if(Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $endpoint = 'nearbysearch/json?pagetoken='.$pageToken;

        $response = $this->call($endpoint);
        $data = $this->buildData($response);

        //put data to cache
        Cache::put($cacheKey, $data, now()->addDays(1));

        return response()->json($data);
    }

    // this function for search radius of place by lat & lng
    private function nearbysearch($place) {
        
        $lat = $place['geometry']['location']['lat'];
        $lng = $place['geometry']['location']['lng'];
        $radius = 500;
        
        $endpoint = 'nearbysearch/json?location='.$lat.'%2C'.$lng.'&radius='.$radius.'&type='.$this->type;

        $response = $this->call($endpoint);

        return $this->status($response['status']) ? collect($response) : null;

    }

    // this function for get detail place
    private function detail($placeId) {
        $fields = [
            'formatted_address',
            'formatted_phone_number',

        ];
        $fieldsImplode = implode('%2C', $fields);

        $endpoint = 'details/json?place_id='.$placeId.'&fields='.$fieldsImplode;
        $response = $this->call($endpoint);
        return $this->status($response['status']) ? $response['result'] : null;
    }

    // this function for call endpoit api
    private function call($endpoint) {

        $url = $this->url.$endpoint.'&key='.$this->apiKey;

        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
        );
    
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    private function status($status) {
        if($status === 'OK') { // status OK is found result
            return true;
        } elseif ($status === 'ZERO_RESULTS') { // status ZERO_RESULTS is result not found
            return false;
        }
    }

    // this function is build data for format response 
    private function buildData($restaurants) {
        $data = collect([
            'restaurants' => collect(),
            'next_page_token' => $restaurants['next_page_token'] ?? null,
            'status' => 'OK'
        ]);

        foreach($restaurants['results'] as $key => $restaurant) {
            $data['restaurants']->push([
                'place_id' => $restaurant['place_id'],
                'restaurant_name' => $restaurant['name'],
                'detail' => $this->detail($restaurant['place_id']),
            ]);
        }

        return $data;
    }
    
}
