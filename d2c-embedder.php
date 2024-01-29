<?php
/**
 * D2C Embedder
 *
 * @package       d2c-embedder
 * @author        D2C Media
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   D2C Embedder 
 * Plugin URI:    https://www.d2cmedia.ca
 * Description:   D2C Media SRP/VDP Embedder plugin. 
 * Version:       1.0.0
 * Author:        D2C Media
 * Author URI:    https://www.d2cmedia.ca
 */
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;



class D2C_Embedder {
    private $pagetitle;
    private $metas;
    private $js_inline;
    private $js_rl;
    private $html;
    private $url="https://oem-gmc-demo.d2cmedia.ca";
    private $defaultpage="/new/new.html";
    private $data_fetched = false;
    private $add_tags = false;
    private $change_title = false;
    private $pageslug = array('d2c-vdp-test','vehicules-neufs','new','neufs');
    private $path;
    private $ajaxsetupcode = "
$.ajaxSetup({
    beforeSend: function(jqXHR, settings) {
        console.log('AJAX beforeSend OPTINS: ',jqXHR,settings);
        if (settings.url.match(/^\/\w/i) != null){
            settings.url = 'https://oem-gmc-demo.d2cmedia.ca' + settings.url;
        }
    }
});
setTimeout(() => {
    docReady();
}, 1000);";
    public function __construct() {
        add_action('wp', array($this, 'fetch_data'));

        //add_shortcode( 'd2cembedder', array( $this, 'd2c_embedder_shortcode' ) );
        add_filter('body_class',array($this,'add_custom_body_classes'));
        add_action('wp_head', array($this,'add_custom_meta_tags'));
        add_action('wp_head', array($this,'add_custom_js_inline_tags'));
        add_action('wp_footer', array($this,'add_custom_js_rl_tags'));
        //add_filter('wp_title', array($this, 'modify_title'), 10, 2);
        add_filter('document_title_parts', array($this, 'modify_page_title'), 10);
        add_shortcode('d2cembedder', array($this, 'handle_shortcode'));
}

    public function handle_shortcode() {
        $this->fetch_data();
        if($this->data_fetched){
            return $this->html;

        }else{
            return '<p>Error fetching data.</p>';
        }
    }
    public function fetch_data() {
        if (!is_page($this->pageslug)) return; // TODO may change this login
       
        if($this->data_fetched) return;//already fetched data 
        $current_language = pll_current_language();
        $processUrl = $this->url . '/embeder/process';
        if ($current_language == 'fr'){
            $this->defaultpage = '/neufs/nouveau.html';
            $processUrl = $this->url . '/embeder/process/fr';

        } 

        $this->path = isset($_GET['path']) ? $_GET['path'] : $this->defaultpage;
    
        // Prepare POST fields
        /*$postFields = array(
            'parentUrl' => 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'path' => $path
        );*/
        $postFields = $this->processUrl('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        // Define the arguments for the POST request
        $args = array(
            'body'      => $postFields,
            'headers'   => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout'   => 45, // Timeout for the request
        );
        //echo '<pre>';
        //print_r($args);
        //echo '</pre>';
    
        // Execute the POST request
        $response = wp_remote_post($processUrl, $args);
    
        // Check for success and handle the response
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Handle error appropriately TODO
            return false;
        }
    
        // Retrieve and decode the response body
        $body = wp_remote_retrieve_body($response);
        $responseData = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle JSON decode  TODO
            return false;
        }
    
        // Decode base64 encoded data and set properties
        $this->metas = base64_decode($responseData['metas']);
        echo '<pre>hello---';
        print_r($responseData['metas']);
        echo ' -----</pre>';

        $this->split_title_metas(base64_decode($responseData['title']));
        $this->js_inline = base64_decode($responseData['js_inline']);
        $this->js_rl = base64_decode($responseData['js_rl']);
        //$this->js_rl = str_replace('addDealerInsideScript();', '//addDealerInsideScript();', $this->js_rl);
        $this->html = base64_decode($responseData['html']);
        $this->add_tags = true;
        $this->change_title = true;    
        $this->data_fetched = true;
        return true;
    }
    
    private function split_title_metas($html){
        if (preg_match('/<title>(.*?)<\/title>/', $html, $matches)) {
            $this->pagetitle = $matches[1];  // Capture the content inside the title tag
        }
    }
    private function processUrl($url) {
        // Parse the URL to get its components
        $urlComponents = parse_url($url);
    
        // Parse the query string into an array
        $queryParams = array();
        if (isset($urlComponents['query'])) {
            parse_str($urlComponents['query'], $queryParams);
        }
    
        
        // Check if 'path' parameter exists in the query and update accordingly
        if (array_key_exists('path', $queryParams)) {
            $this->path = $queryParams['path'];
            unset($queryParams['path']); // Remove 'path' from query parameters
        }
    
        // Rebuild the query string without 'path'
        $queryString = http_build_query($queryParams);
    
        // Rebuild the URL without 'path'
        $parentUrl = $urlComponents['scheme'] . '://' . $urlComponents['host'] . $urlComponents['path'];
        if (!empty($queryString)) {
            $parentUrl .= '?' . $queryString;
        }
    
        return array('parentUrl' => $parentUrl, 'path' => $this->path);
    }
    
    
    public function add_custom_body_classes($classes) {
        if (is_page($this->pageslug)){
            $classes[] = 'isPageFullWidthEnabled';
            $classes[] = 'template1';
            $classes[] = 'isFullWidthPage';
        }
        return $classes;
    }
    
    public function add_custom_meta_tags() {
        if (is_page($this->pageslug) && $this->data_fetched){
            echo $this->metas;
        }
    }

    public function add_custom_js_inline_tags() {
        $this->fetch_data();
        if (is_page($this->pageslug) && $this->data_fetched){
            echo '<script id="d2c_js_inline" type="text/javascript">' . $this->js_inline . '</script>';
            //echo '<script id="d2c_js_inline" type="text/javascript"></script>';
        }
    }

    public function add_custom_js_rl_tags() {
        $this->fetch_data();
        if (is_page($this->pageslug) && $this->data_fetched){
            echo '<script id="d2c_js_rl" type="text/javascript">' . $this->js_rl . $this->ajaxsetupcode . '</script>';
            //echo '<script id="d2c_js_rl" type="text/javascript"></script>';
        }
    }
    public function modify_page_title($title){
        $this->fetch_data();
        if (is_page($this->pageslug) && $this->data_fetched){
            $title['title'] = $this->pagetitle;
            $title['site'] = '';
        }
        return $title;
    }

}

$d2c_embedder_plugin = new D2C_Embedder();
