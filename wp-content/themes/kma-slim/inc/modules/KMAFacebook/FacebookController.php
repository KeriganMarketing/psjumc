<?php

namespace Includes\Modules\KMAFacebook;

use KeriganSolutions\FacebookFeed\FacebookFeed;
use KeriganSolutions\FacebookFeed\FacebookReviews;
use KeriganSolutions\FacebookFeed\FacebookPhotoGallery;
use KeriganSolutions\FacebookFeed\FacebookEvents;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Exception\RequestException;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Storage\WordPressObjectCacheStorage;

class FacebookController
{
    protected $facebookPageID;
    protected $facebookToken;
    protected $appId;
    protected $appSecret;

    public function __construct()
    {
        $this->facebookPageID = get_option('facebook_page_id');
        $this->facebookToken = get_option('facebook_token');
        $this->appSecret = get_option('facebook_app_secret');
        $this->appId = '353903931781568';
        // $this->syncEvents();
    }

    // CACHE SYSTEM

    public function initCache()
    {
        // Create default HandlerStack
        $stack = HandlerStack::create();

        // Add this middleware to the top with `push`
        $stack->push(new CacheMiddleware(
            new PrivateCacheStrategy(
                new WordPressObjectCacheStorage()
            )
        ), 'cache');

        return $stack;
    }

    // GETTING THINGS FROM FB

    // TODO public function getReviews($num = 1)
    // {
    //     $feed = new FacebookReviews($this->facebookPageID,$this->facebookToken);
    //     return $feed->fetch($num);
    // }

    public function getEvents($limit = null, $before = null, $after = null)
    {
        $feed = new FacebookEvents($this->facebookPageID,$this->facebookToken);
        return $feed->fetch($limit, $before, $after);
    }

    public function getPhotos($albumId, $limit = null, $before = null, $after = null)
    {
        $feed =  new FacebookPhotoGallery($this->facebookPageID,$this->facebookToken);
        return $feed->albumPhotos($albumId, $limit, $before, $after);
    }

    public function getAlbums($albumId, $limit = null, $before = null, $after = null)
    {
        $feed =  new FacebookPhotoGallery($this->facebookPageID,$this->facebookToken);
        return $feed->albums($albumId, $limit, $before, $after);
    }

    public function getFeed($num = 1)
    {
        try{
            $feed = new FacebookFeed($this->facebookPageID,$this->facebookToken);
            $response = $feed->fetch($num);
        }catch(RequestException $e){
            $response = $e->getResponse()->getBody(true);
        }

        return $response;
    }

    // GETTING THINGS FROM WP

    public function getFbPosts($num = -1, $args = [])
    {
        $request = [
            'posts_per_page' => $num,
            'offset'         => 0,
            'order'          => 'DESC',
            'orderby'        => 'date_posted',
            'post_type'      => 'kma-fb-post',
            'post_status'    => 'publish',
        ];

        $request   = array_merge($request, $args);
        $postArray = get_posts($request);

        $output = [];
        foreach($postArray as $post){
            $post->post_link = get_post_meta($post->ID, 'post_link', true);
            $post->full_image_url = get_post_meta($post->ID, 'full_image_url', true);
            $post->attachments = get_post_meta($post->ID, 'attachments', false);
            $post->type = get_post_meta($post->ID, 'type', true);
            $post->status_type = get_post_meta($post->ID, 'status_type', true);
            $post->video_url = get_post_meta($post->ID, 'video_url', true);
            $post->video_image = get_post_meta($post->ID, 'video_image', true);
            $output[] = $post;
        }

        return $output;
    }

    public function getFbEvents($num = -1, $args = [])
    {
        $request = [
            'posts_per_page' => $num,
            'offset'         => 0,
            'order'          => 'DESC',
            'orderby'        => 'date_posted',
            'post_type'      => 'kma-fb-event',
            'post_status'    => 'publish',
        ];

        $request   = array_merge($request, $args);
        $postArray = get_posts($request);

        $output = [];
        foreach($postArray as $post){
            $post->event_name = get_post_meta($post->ID, 'event_name', true);
            $post->post_link = get_post_meta($post->ID, 'post_link', true);
            $post->where = get_post_meta($post->ID, 'where', false);
            $post->full_image_url = get_post_meta($post->ID, 'full_image_url', true);
            $post->start_time = get_post_meta($post->ID, 'start_time', true);
            $post->end_time = get_post_meta($post->ID, 'end_time', true);
            $output[] = $post;
        }

        return $output;
    }

    public function getFbAlbums($num = -1, $args = [])
    {
        $request = [
            'posts_per_page' => $num,
            'offset'         => 0,
            'order'          => 'DESC',
            'orderby'        => 'date_posted',
            'post_type'      => 'kma-fb-album',
            'post_status'    => 'publish',
        ];

        $request   = array_merge($request, $args);
        $postArray = get_posts($request);

        $output = [];
        foreach($postArray as $post){
            $post->album_name = get_post_meta($post->ID, 'album_name', true);
            $post->post_link = get_post_meta($post->ID, 'post_link', true);
            $output[] = $post;
        }

        return $output;
    }

    // KEEP WP UPDATED WITH FB

    public function cron()
    {
        if(! wp_next_scheduled('kma-fb-sync')){
            wp_schedule_event(strtotime('01:00:00'), 'hourly', 'kma-fb-sync');
        }

        add_action('kma-fb-sync', [$this,'updatePosts']);
    }

    public function syncPosts()
    {
        $this->updatePosts(30) ? wp_send_json_success() : wp_send_json_error();
    }

    public function syncEvents()
    {
        $this->updateEvents(30) ? wp_send_json_success() : wp_send_json_error();
    }


    public function updateEvents($n = 30)
    {
        $feed = $this->getEvents($n);

        if($feed->posts){

            foreach($feed->posts as $fbpost){
                $postArray = [
                    'ID' => 0,
                    'post_content' => $fbpost->description,
                    'post_title' => $fbpost->id,
                    'post_status' => 'publish',
                    'post_type' => 'kma-fb-event',
                    'meta_input' => [
                        'event_name' => $fbpost->name,
                        'post_link' => 'https://www.facebook.com/events/' . $fbpost->permalink_url,
                        'where' => $fbpost->place->name,
                        'full_image_url' => $fbpost->cover->source,
                        'start_time' => $fbpost->start_time,
                        'end_time' => $fbpost->end_time,
                    ]
                ];

                // echo '<pre>',print_r($postArray),'</pre>';
                $postExists = get_page_by_title($fbpost->id, OBJECT, 'kma-fb-event');

                if(isset($postExists->ID)){
                    $postArray['ID'] = $postExists->ID;
                    wp_update_post($postArray);
                }else{
                    wp_insert_post($postArray);
                }

            }
            return true;
        }
        return false;
    }

    public function updatePosts($n = 30)
    {
        $feed = $this->getFeed($n);

        if($feed->posts){

            foreach($feed->posts as $fbpost){

                $postArray = [
                    'ID' => 0,
                    'post_date' => $fbpost->created_time,
                    'post_content' => $fbpost->message,
                    'post_title' => $fbpost->id,
                    'post_status' => 'publish',
                    'post_type' => 'kma-fb-post',
                    'meta_input' => [
                        'post_link' => $fbpost->permalink_url,
                        'full_image_url' => $fbpost->full_picture,
                        'attachments' => $fbpost->attachments->data,
                        'type' => $fbpost->type,
                        'status_type' => $fbpost->status_type,
                        'video_url' => $fbpost->attachments->data[0]->media->source,
                        'video_image' => $fbpost->attachments->data[0]->media->image->src
                    ]
                ];

                $postExists = get_page_by_title($fbpost->id, OBJECT, 'kma-fb-post');

                if(isset($postExists->ID)){
                    $postArray['ID'] = $postExists->ID;
                    wp_update_post($postArray);
                    update_post_meta($postExists->ID, 'attachments', $fbpost->media);
                }else{
                    $postID = wp_insert_post($postArray);
                    update_post_meta($postID, 'attachments', $fbpost->media);
                }
            }

            return true;
        }

        return false;
        
    }

    // TOKEN RENEWAL!

    public function exchangeToken($request)
    {
        $token = $request->get_param( 'token' );
        $client = new Client();

        try {
            $response = $client->request('GET',
            'https://graph.facebook.com/v7.0/oauth/access_token?' . 
            'grant_type=fb_exchange_token&' .
            'client_id=' . $this->appId . '&' .
            'client_secret=' . $this->appSecret . '&' .
            'fb_exchange_token=' . $token );
        } catch (RequestException $e) {
            
        }

        return rest_ensure_response(json_decode($response->getBody()));
    }

    // SETUP STUFF

    public function setupAdmin()
    {
        add_action('admin_menu', [$this,'addMenus']);
        add_action( 'rest_api_init', [$this,'addRoutes']);
        add_action( 'init', [$this,'createPostType'], 50 );
        add_action( 'init', [$this,'createPhotoPostType'], 50 );
        add_action( 'init', [$this,'createAlbumPostType'], 50 );
        add_action( 'init', [$this,'createEventPostType'], 50 );
        $this->cron();
    }

    public function addRoutes() 
    {
        register_rest_route( 'kerigansolutions/v1', '/autoblogtoken',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'exchangeToken' ]
            ]
        );

        register_rest_route( 'kerigansolutions/v1', '/forcepostsync',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'syncPosts' ]
            ]
        );

        register_rest_route( 'kerigansolutions/v1', '/forcephotosync',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'syncPhotos' ]
            ]
        );

        register_rest_route( 'kerigansolutions/v1', '/forceeventsync',
            [
                'methods'  => 'GET',
                'callback' => [ $this, 'syncEvents' ]
            ]
        );

        // register_rest_route( 'kerigansolutions/v1', '/forcereviewsync',
        //     [
        //         'methods'  => 'GET',
        //         'callback' => [ $this, 'syncReviews' ]
        //     ]
        // );
    }
    
    protected function loadCss()
    {
        wp_enqueue_style('bluma_admin_css', 'https://cdn.jsdelivr.net/npm/bulma@0.9.0/css/bulma.min.css',
            false, '1.0.0');
    }

    public function addMenus()
    {
        add_menu_page("Facebook Settings", "Facebook Settings", "administrator", 'kma-facebook', function () {
            $this->loadCss();
            include(wp_normalize_path( dirname(__FILE__) . '/templates/AdminOverview.php'));
        }, "dashicons-admin-generic");
    }

    public function createPostType()
    {
        register_post_type( 'kma-fb-post',
        array(
            'labels' => array(
                'name' => __( 'kma-fb-post' ),
                'singular_name' => __( 'kma-fb-post' )
            ),
            'supports' => ['title','editor','custom-fields'],
            'public' => true,
            'has_archive' => false,
            'rewrite' => false,
        ));
    }

    public function createPhotoPostType()
    {
        register_post_type( 'kma-fb-photo',
        array(
            'labels' => array(
                'name' => __( 'kma-fb-photo' ),
                'singular_name' => __( 'kma-fb-photo' )
            ),
            'supports' => ['title','editor','custom-fields'],
            'public' => true,
            'has_archive' => false,
            'rewrite' => false,
        ));
    }

    public function createAlbumPostType()
    {
        register_post_type( 'kma-fb-album',
        array(
            'labels' => array(
                'name' => __( 'kma-fb-album' ),
                'singular_name' => __( 'kma-fb-album' )
            ),
            'supports' => ['title','editor','custom-fields'],
            'public' => true,
            'has_archive' => false,
            'rewrite' => false,
        ));
    }

    public function createEventPostType()
    {
        register_post_type( 'kma-fb-event',
        array(
            'labels' => array(
                'name' => __( 'kma-fb-event' ),
                'singular_name' => __( 'kma-fb-event' )
            ),
            'supports' => ['title','editor','custom-fields'],
            'public' => true,
            'has_archive' => false,
            'rewrite' => false,
        ));
    }

    // public function createReviewPostType()
    // {
    //     register_post_type( 'kma-fb-review',
    //     array(
    //         'labels' => array(
    //             'name' => __( 'kma-fb-review' ),
    //             'singular_name' => __( 'kma-fb-review' )
    //         ),
    //         'supports' => ['title','editor','custom-fields'],
    //         'public' => true,
    //         'has_archive' => false,
    //         'rewrite' => false,
    //     ));
    // }
    
}