<?php
/**
 * D2C Embedder
 *
 * @package       d2c-embedder
 * @author        D2C Media
 *
 * @wordpress-plugin
 * Plugin Name:   D2C Embedder 
 * Plugin URI:    https://www.d2cmedia.ca
 * Description:   D2C Media SRP/VDP Embedder plugin. 
 * Version:       1.0.1
 * Author:        D2C Media
 * Author URI:    https://www.d2cmedia.ca
 */
// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	echo 'This file should not be accessed directly!';
	exit; // Exit if accessed directly.
}

/**
 * D2C_Embedder Class
 * 
 * Handles the functionality to fetch data from the D2C API.
 * This class is responsible for creating pages based on URL parameters provided.
 */
class D2C_Embedder {
    private $pagetitle;
    private $metas;
    private $js_inline;
    private $js_rl;
    private $html;
    private $url="https://oem-gmc-demo.d2cmedia.ca";
    private $defaultpage="/new/new.html";
    private $data_fetched = false;
    
    // The plugin works only on these pages 
    private $pageslug = array('d2c-showroom-test','d2c-vehicules-neufs','d2c-new','d2c-neufs');
    //TODO may move this to settings page 

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

    /**
     * Constructor for the D2C_Embedder class.
     * All actions, filters and short_code are registered 
     */
    public function __construct() {
        add_action('wp', array($this, 'fetch_data'));

        add_filter('body_class',array($this,'add_custom_body_classes'));
        add_action('wp_head', array($this,'add_custom_meta_tags'));
        add_action('wp_head', array($this,'add_custom_js_inline_tags'));
        add_action('wp_footer', array($this,'add_custom_js_rl_tags'));
        add_filter('document_title_parts', array($this, 'modify_page_title'), 10);
        add_shortcode('d2cembedder', array($this, 'handle_shortcode'));
}


    /**
     * Fetches data from a specified API endpoint based on the current page and language.
     *
     * This method checks if the current page matches the expected page slug (`$this->pageslug`)
     * and if the data has not already been fetched. 
     * It prepares and sends a POST request to the API and processes the response.
     *
     * If the response is valid, it decodes the data (assumed to be base64 encoded) and sets
     * various properties of the class like `metas`, `js_inline`, `js_rl`, and `html`.
     * It then marks that the data has been fetched successfully.
     */
    public function fetch_data() {
        if (!is_page($this->pageslug)) return; // should match the expected page slug
       
        if($this->data_fetched) return; //already fetched data 
        $current_language = $this->get_current_page_language();
        $processUrl = $this->url . '/embeder/process';
        if ($current_language == 'fr'){
            $this->defaultpage = '/neufs/nouveau.html';
            $processUrl = $this->url . '/embeder/process/fr';

        } 

        $this->path = isset($_GET['path']) ? $_GET['path'] : $this->defaultpage;
    
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
        $this->get_title_value(base64_decode($responseData['title']));
        $this->js_inline = base64_decode($responseData['js_inline']);
        $this->js_rl = base64_decode($responseData['js_rl']);
        $this->html = base64_decode($responseData['html']);
        $this->data_fetched = true;
        return true;
    }

    /**
     * get the current page language
     * @return string en or fr
     */
    private function get_current_page_language(){
        $current_language = 'en';
        if (function_exists('pll_current_language')) {
            // Polylang is active
            $current_language = pll_current_language();
        } elseif (defined('ICL_LANGUAGE_CODE')) {
            // WPML is active
            $current_language = ICL_LANGUAGE_CODE;
        } else {
            // Fallback if neither Polylang nor WPML is active
            // default WordPress locale or set a default language
            $current_language = substr(get_locale(), 0, 2);
        }
        return $current_language;
        
    }

    /**
     * sets the pagetitle
    */
    private function get_title_value($html){
        if (preg_match('/<title>(.*?)<\/title>/', $html, $matches)) {
            $this->pagetitle = $matches[1];  // Capture the content inside the title tag
        }
    }

    /**
     * Processes a given URL to extract and manipulate its components.
     *
     * This method parses the provided URL and extracts its components using `parse_url`.
     * It specifically looks for and handles the 'path' query parameter. If the 'path'
     * parameter exists, it updates the class property `$this->path` and removes 'path'
     * from the query parameters of the URL.
     *
     * After processing, the method rebuilds the query string without the 'path'
     * parameter and reconstructs the URL accordingly. The final output is an array
     * containing the modified URL ('parentUrl') and the extracted 'path' value.
     *
     * @param string $url The URL to be processed.
     * @return array An associative array with 'parentUrl' and 'path' as keys.
     */   
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
    
    
    /**
     * adds the custom body classes
    */
    public function add_custom_body_classes($classes) {
        if (is_page($this->pageslug)){
            $classes[] = 'isPageFullWidthEnabled';
            $classes[] = 'template1';
            $classes[] = 'isFullWidthPage';
        }
        return $classes;
    }
    
    /**
     * adds the custom meta tags
    */
    public function add_custom_meta_tags() {
        if (is_page($this->pageslug) && $this->data_fetched){
            echo $this->metas;
        }
    }

    /**
     * adds the custom inline tags
    */
    public function add_custom_js_inline_tags() {
        $this->fetch_data();
        if (is_page($this->pageslug) && $this->data_fetched){
            echo '<script id="d2c_js_inline" type="text/javascript">' . $this->js_inline . '</script>';
        }
    }

    /**
     * adds the custom JS tags
    */
    public function add_custom_js_rl_tags() {
        $this->fetch_data();
        if (is_page($this->pageslug) && $this->data_fetched){
            echo '<script id="d2c_js_rl" type="text/javascript">' . $this->js_rl . $this->ajaxsetupcode . '</script>';
        }
    }

    /**
     * Modifies the page title based on data fetched by the `fetch_data` method.
    */
    public function modify_page_title($title){
        $this->fetch_data();
        if (is_page($this->pageslug) && $this->data_fetched){
            $title['title'] = $this->pagetitle;
            $title['site'] = '';
        }
        return $title;
    }

    /**
     * Handles the shortcode functionality for the D2C_Embedder class.
     *
     * This method is responsible for fetching data using the `fetch_data` method.
     * After attempting to fetch data, it checks if the data was successfully retrieved.
     * If the data is successfully fetched, it returns the formatted HTML content.
     * Otherwise, it returns an error message in HTML format.
     */
    public function handle_shortcode() {
        $this->fetch_data();
        if($this->data_fetched){
            return $this->html;

        }else{
            return '<p>Error fetching data.</p>';
        }
    }

}

// Create an instance of the D2C_Embedder class.
// This object will be used to access the functionality provided by the D2C_Embedder plugin,
// such as fetching data from the D2C API, processing URLs, and handling shortcodes.
$d2c_embedder_plugin = new D2C_Embedder();
