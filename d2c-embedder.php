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
 * Version:       1.0.5
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
    private $menu_new;
    private $url="";
    private $defaultpage="/new/new.html";
    private $data_fetched = false;
    private $is_mobile = false;
    
    // The plugin works only on these pages 
    private $pageslug = array('d2c-showroom-test','d2c-showroom-test-fr','d2c-vdp-test','d2c-vdp-test-fr');
    //TODO may move this to settings page 

    private $path;

private $otherPagecode = <<<'OTHERPAGE'
    const baseDomain = document.getElementById("d2cDataStore").dataset.baseurl;
    function addCssStyles() {

        var styles = `

            #d2c_bodyContent.d2c-embedded-content{
                font-size: 0.875rem;
                font-family: var(--font-text);
                font-weight: var(--font-text-weight, 400);
            }

            #d2c_bodyContent.d2c-embedded-content-mobile{
                font-size: 1rem;
                font-family: var(--font-text);
                font-weight: var(--font-text-weight, 400);
            }

            #d2c_bodyContent #filterMainBox label {
                margin-inline-start: 0.3rem;
            }

            #d2c_bodyContent a {
                text-decoration: none;
            }

            #d2c_bodyContent .white-txt:is(.toggle-icon,.icon-button){
                height: 1.4rem;
                width: 1.4rem;
            }
            #d2c_bodyContent .glossy-curved-black .slide-arrows a{
                background-image:url(${baseDomain}/images/arrows.png);
            }

            #d2c_bodyContent .owl-controls .owl-buttons div {
                top: 40% !important;
                position: absolute !important;
                width: 37px !important;
                height: 38px !important;
            }

            #d2c_bodyContent .owl-controls .owl-buttons .owl-prev {
                background: url(${baseDomain}/css/tango/left.png) !important;
            }

            #d2c_bodyContent .owl-controls .owl-buttons .owl-next {
                background: url(${baseDomain}/css/tango/right.png) !important;
            }

            #header #header-bottom .navbar #menu-top-menu>li>a {line-height:unset !important;}
            .up_menu ul.nav > li > a {height:unset !important;min-height:18px;}
            .up_menu ul.nav > li#MainMenu_NEW:hover > ul.sub {min-width:20rem !important;}
            .up_menu ul.nav {background-color:transparent !important;}

            .d2c-embedded-content #TradeInBarPopupContainer {
                min-width: 700px;
                min-height: 700px;
                top: 3rem !important;
            }

            #d2c_bodyContent :is(h1.default span.icon, svg.fa-svg, .tradeInBarMobileButton) {
                box-sizing: content-box;
            }

            #d2c_bodyContent .old-details td i.fa-chevron-right {
                box-sizing:content-box;
            }

            @media only screen and (min-width: 1301px){
                #d2c_bodyContent li.carBoxWrapper {
                    width: calc(33.33333333333333% - 15px);
                }
            }
        `;

        var styleTag = document.createElement("style");
        styleTag.type = "text/css";
        styleTag.innerHTML = styles;
        document.head.appendChild(styleTag);
    }
    function handleAdjsutments(){


        //if(window.gUsedSrpAppz || false){
        //    gUsedSrpAppz.filterInterface.getFilterController().init();
        //}
        let isMobile = (document.getElementById('d2cDataStore').dataset.ismobile || 0) === '1';

        if(window.gUsedSrpAppz || false){
            let filterController = window.gUsedSrpAppz.filterInterface.getFilterController();
            filterController._isMobile = isMobile;
            filterController.initActions()
        }



        document.getElementById('d2c_bodyContent').hidden = false;

        let mainTag = document.getElementsByTagName('main')[0] || false;

        if(!mainTag) return;

        mainTag.parentNode.classList.toggle('col-sm-8',false);
        mainTag.parentNode.classList.toggle('col-sm-12',true);

    }
    function addCssFile(url) {

        var link = document.createElement("link");

        link.rel = "stylesheet";
        link.type = "text/css";
        link.href = `${baseDomain}${url}`;
        document.head.appendChild(link);
    }
    function handleMenu(){

        let menu_html = document.getElementById('d2c_menuNew')?.children[0] || false;
        if(!menu_html) return;
    
        menu_html.querySelector('li[data-id="NEW_BUILDPRICE"]').remove();
        //let menuNewNode = document.querySelector('#menu-top-menu .new-dropdown');
        let menuNewNode = document.querySelector('#menu-main-menu .newVehicles');

        menuNewNode.closest('.navbar').classList.add('up_menu');
        menuNewNode.id = 'MainMenu_NEW';
        menuNewNode.appendChild(menu_html);
        
        menuNewNode.querySelectorAll('img[data-src]').forEach(img => img.src = img.dataset.src);

    }
    function handleLinks(){

        const linkMap = {
            'new-vehicles': '/new/new.html',
            'used-vehicles': '/used/search.html',
            'vehicules-neufs': '/neufs/nouveau.html',
            'vehicules-doccasion': '/occasion/recherche.html',
            //'/new-vehicles/chevrolet/': '/d2c-showroom-test/?path=/new/Chevrolet.html',
            //'/new-vehicles/buick/': '/d2c-showroom-test/?path=/new/Buick.html',
            //'/new-vehicles/gmc/': '/d2c-showroom-test/?path=/new/GMC.html',
            //'/new-vehicles/cadillac/': '/d2c-showroom-test/?path=/new/Cadillac.html',
            //'/new-vehicles/used/': '/d2c-showroom-test/?path=/new/used.html',
            //'/new-vehicles/finance/': '/d2c-showroom-test/?path=/new/finance.html',
            //'/new-vehicles/service/': '/d2c-showroom-test/?path=/new/service.html',
            //'/new-vehicles/contact/': '/d2c-showroom-test/?path=/new/contact.html',
            //'/new-vehicles/current-offers/': '/d2c-showroom-test/?path=/new/current-offers.html',
        };


        for(key in linkMap){
            let link = document.querySelectorAll(`a[href$="/${key}/"]`);
            link.forEach(a => {a.href = `/d2c-vdp-test/?path=${linkMap[key]}`});
            console.log('key=',key);
        }

    }
    addCssStyles();
    addCssFile('/css/menu-base.css');
    addCssFile("/css/menu7.css");
    //handleAdjsutments();
    handleMenu();
    handleLinks();
OTHERPAGE;

private $ajaxsetupcode = <<<'AJAXCODE'
const baseDomain = document.getElementById("d2cDataStore").dataset.baseurl;
function setBodyClass() {

    let bodyClass = document.getElementById('d2cDataStore').dataset.bodyclass.trim().split(' ');

    bodyClass.forEach(cls => document.body.classList.add(cls));

}

function handleAdjsutments(){


    //if(window.gUsedSrpAppz || false){
    //    gUsedSrpAppz.filterInterface.getFilterController().init();
    //}
    let isMobile = (document.getElementById('d2cDataStore').dataset.ismobile || 0) === '1';

    if(window.gUsedSrpAppz || false){
        let filterController = window.gUsedSrpAppz.filterInterface.getFilterController();
        filterController._isMobile = isMobile;
        filterController.initActions()
    }
    document.getElementById('d2c_bodyContent').hidden = false;

    let mainTag = document.getElementsByTagName('main')[0] || false;

    if(!mainTag) return;

    mainTag.parentNode.classList.toggle('col-sm-8',false);
    mainTag.parentNode.classList.toggle('col-sm-12',true);

}
function handleMenu(){

    let menu_html = document.getElementById('d2c_menuNew')?.children[0] || false;
    if(!menu_html) return;

    menu_html.querySelector('li[data-id="NEW_BUILDPRICE"]').remove();
    let menuNewNode = document.querySelector('#menu-top-menu .new-dropdown');
    //let menuNewNode = document.querySelector('#menu-main-menu-1 .newVehicles');

    menuNewNode.closest('.navbar').classList.add('up_menu');
    menuNewNode.id = 'MainMenu_NEW';
    menuNewNode.appendChild(menu_html);

    menuNewNode.querySelectorAll('img[data-src]').forEach(img => img.src = img.dataset.src);


}

function handleLinks(){

    const linkMap = {
        'new-vehicles': '/new/new.html',
        'used-vehicles': '/used/search.html',
        'vehicules-neufs': '/neufs/nouveau.html',
        'vehicules-doccasion': '/occasion/recherche.html',
        //'/new-vehicles/chevrolet/': '/d2c-showroom-test/?path=/new/Chevrolet.html',
        //'/new-vehicles/buick/': '/d2c-showroom-test/?path=/new/Buick.html',
        //'/new-vehicles/gmc/': '/d2c-showroom-test/?path=/new/GMC.html',
        //'/new-vehicles/cadillac/': '/d2c-showroom-test/?path=/new/Cadillac.html',
        //'/new-vehicles/used/': '/d2c-showroom-test/?path=/new/used.html',
        //'/new-vehicles/finance/': '/d2c-showroom-test/?path=/new/finance.html',
        //'/new-vehicles/service/': '/d2c-showroom-test/?path=/new/service.html',
        //'/new-vehicles/contact/': '/d2c-showroom-test/?path=/new/contact.html',
        //'/new-vehicles/current-offers/': '/d2c-showroom-test/?path=/new/current-offers.html',
    };


    for(key in linkMap){
        let link = document.querySelectorAll(`a[href$="/${key}/"]`);
        link.forEach(a => {a.href = `/d2c-vdp-test/?path=${linkMap[key]}`});
    }

}

function addCssStyles() {

    var styles = `

    #d2c_bodyContent.d2c-embedded-content{
        font-size: 0.875rem;
        font-family: var(--font-text);
        font-weight: var(--font-text-weight, 400);
    }

    #d2c_bodyContent.d2c-embedded-content-mobile{
        font-size: 1rem;
        font-family: var(--font-text);
        font-weight: var(--font-text-weight, 400);
    }

    #d2c_bodyContent #filterMainBox label {
        margin-inline-start: 0.3rem;
    }

    #d2c_bodyContent a {
        text-decoration: none;
    }

    #d2c_bodyContent .white-txt:is(.toggle-icon,.icon-button){
        height: 1.4rem;
        width: 1.4rem;
    }
    #d2c_bodyContent .glossy-curved-black .slide-arrows a{
        background-image:url(${baseDomain}/images/arrows.png);
    }

    #d2c_bodyContent .owl-controls .owl-buttons div {
        top: 40% !important;
        position: absolute !important;
        width: 37px !important;
        height: 38px !important;
    }

    #d2c_bodyContent .owl-controls .owl-buttons .owl-prev {
        background: url(${baseDomain}/css/tango/left.png) !important;
    }

    #d2c_bodyContent .owl-controls .owl-buttons .owl-next {
        background: url(${baseDomain}/css/tango/right.png) !important;
    }

    #header #header-bottom .navbar #menu-top-menu>li>a {line-height:unset !important;}
    .up_menu ul.nav > li > a {height:unset !important;min-height:18px;}
    .up_menu ul.nav > li#MainMenu_NEW:hover > ul.sub {min-width:20rem !important;}
    .up_menu ul.nav {background-color:transparent !important;}

    .d2c-embedded-content #TradeInBarPopupContainer {
        min-width: 700px;
        min-height: 700px;
        top: 3rem !important;
    }

    #d2c_bodyContent :is(h1.default span.icon, svg.fa-svg, .tradeInBarMobileButton) {
        box-sizing: content-box;
    }

    #d2c_bodyContent .old-details td i.fa-chevron-right {
        box-sizing:content-box;
    }

    @media only screen and (min-width: 1301px){
        #d2c_bodyContent li.carBoxWrapper {
            width: calc(33.33333333333333% - 15px);
        }
    }
`;

var styleTag = document.createElement("style");
    styleTag.type = "text/css";
    styleTag.innerHTML = styles;
    document.head.appendChild(styleTag);
}

function addCssFile(url) {

    var link = document.createElement("link");

    link.rel = "stylesheet";
    link.type = "text/css";
    link.href = `${baseDomain}${url}`;

    document.head.appendChild(link);


}


function ajaxPreset() {

    let dataStore = document.getElementById('d2cDataStore').dataset;

    $.ajaxPrefilter(function( options, originalOptions, jqXHR ) {
        if (options.url.match(/^\/\w/i) != null){
            options.url = baseDomain + options.url;
        }

        if(dataStore.ismobile === '1' && options.type == 'GET')
            options.data += `&isMobile=1`;
    });
    $( document ).on( "ajaxSuccess", function( event, xhr, settings ) {

        if ( settings.url.includes('getPopupContent')) {
            let popup_match = settings.url.match(/&id=(\w*)&/);
            if(popup_match[1]){
                document.querySelectorAll(`[data-lazyloadid="${popup_match[1]}"] img`).forEach(img => {

                    if(img.dataset.src || false)
                        img.dataset.src = baseDomain + img.dataset.src;
                    else
                        img.src = baseDomain + img.src
                });
            }

            //console.log("ajaxSuccess:", event, xhr, settings);
        }
    } );
    $.ajaxSetup({
        beforeSend: function(jqXHR, settings) {

            console.log('AJAX beforeSend OPTINS: ',jqXHR,settings);

            if (settings.url.match(/^\/\w/i) != null){
                settings.url = baseDomain + settings.url;
            }

        }
    });
}

ajaxPreset();
    setBodyClass();

    // Add CSS files
    addCssStyles();
    addCssFile("/css/menu7.css");

    handleAdjsutments();
    handleMenu();
    handleLinks();
    setTimeout(() => {
docReady();
}, 1000);
AJAXCODE;


    /**
     * Constructor for the D2C_Embedder class.
     * All actions, filters and short_code are registered 
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'initialize_plugin_settings'));
        register_deactivation_hook(__FILE__, array($this, 'cleanup_on_deactivation'));
        register_activation_hook(__FILE__, array($this, 'set_default_options'));

        add_filter('pre_update_option_d2c_api_url', array($this, 'sanitize_api_url'), 10, 2);
        add_filter("plugin_action_links", array($this, 'add_settings_link'),10,2);
        add_action('wp', array($this, 'fetch_data'));

        add_action('wp_head', array($this,'add_custom_meta_tags'));
        add_action('wp_footer', array($this,'add_custom_js_rl_tags'));
        add_filter('document_title_parts', array($this, 'modify_page_title'), 10);
        //add_filter('wp_nav_menu_objects', array($this,'remove_specific_menu_item'), 1000, 2);
        add_shortcode('d2cembedder', array($this, 'handle_shortcode'));
}

    public function add_admin_menu() {
        add_options_page('D2C Embedder Integration', 'D2C Embedder', 'manage_options', 'd2cembedder', array($this, 'display_settings_page'));
    }

    // Display settings page
    public function display_settings_page() {
        $plugin_basename = ( __FILE__  .  '.php' );
        ?>
        <div class="wrap">
            <h2>D2C Embedder Integration Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('d2c-api-options'); ?>
                <?php do_settings_sections('d2cembedder'); ?>
                <table class="form-table">
                    <tr valign="top">
                    <th scope="row">D2C Embedder API URL:</th>
                    <td><input type="text" style="width:50%" name="d2c_api_url" value="<?php echo esc_attr(get_option('d2c_api_url')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
        // Check if the API URL is set and display warning if not
        if (!get_option('d2c_api_url')) {
            echo '<div id="message" style="color:red"  class="notice notice-error"><p><strong>Warning:</strong> API URL is not set.</p></div>';
        }
    }
    public function initialize_plugin_settings() {
        register_setting('d2c-api-options', 'd2c_api_url');
    }

    // Sanitize API URL before saving
    public function sanitize_api_url($new_value, $old_value) {
        // Trim the trailing slash if it exists
        return rtrim($new_value, '/');
    }

    // Set default options on activation
    public function set_default_options() {
        if (get_option('d2c_api_url') === false) {
            update_option('d2c_api_url', 'https://oem-gmc-demo.d2cmedia.ca');
        }
    }


    // Cleanup on plugin deactivation
    public function cleanup_on_deactivation() {
        delete_option('d2c_api_url'); // Clean up the option stored in the database
    }

    // Add settings link to the plugins page
    public function add_settings_link($links, $file) {
        if ( $file == plugin_basename( dirname( __FILE__ ).'/d2c-embedder.php' ) ) {
            $links[] = '<a href="' . admin_url( 'options-general.php?page=d2cembedder' ) . '">'.__( 'Settings' ).'</a>';
        }
        return $links;
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
        //if (!is_page($this->pageslug)) return; // should match the expected page slug
       
        if($this->data_fetched) return; //already fetched data 
        if(!get_option('d2c_api_url')) return; //API URL not set

        $this->url = get_option('d2c_api_url');
        

        //$is_mobile = $this->is_mobile_by_screen_size();//         wp_is_mobile();
        $is_mobile =  wp_is_mobile();
        $current_language = $this->get_page_language();
        $processUrl = $this->url . '/embeder/process';
        if ($current_language == 'fr'){
            $this->defaultpage = '/neufs/nouveau.html';
            $processUrl = $this->url . '/embeder/process/fr';

        } 
        if ($is_mobile){
            $processUrl = $processUrl . '/m';
        }

        $this->path = isset($_GET['path']) ? $_GET['path'] : $this->defaultpage;
    
        //$postFields = $this->processUrl('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        $postFields = $this->processUrl('http://' . $_SERVER['HTTP_HOST'] . ':9081' .$_SERVER['REQUEST_URI']);
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
	    //print_r($_SERVER);
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
        $this->menu_new = base64_decode($responseData['menu_new']);
        $this->data_fetched = true;
        return true;
    }

    /**
     * get the selected page language
     * @return string en or fr
     */
    private function get_page_language(){
        $current_language = 'en';
        if (is_singular()) {
            global $post; // Get the current post data
            $slug = $post->post_name; // Get the slug of the current post
    
            // Check if the slug ends with '-fr'
            $slug_ends_with_fr = substr($slug, -3) === '-fr';
    
            // Get the current URL
            $current_url = home_url(add_query_arg(null, null));

            // Parse the URL to get query parameters
            $query_params = [];
            parse_str(parse_url($current_url, PHP_URL_QUERY), $query_params);
            
            // Check if the 'path' query parameter exists and contains '/neufs/'
            $path_contains_neufs = false;
            if (isset($query_params['path']) && strpos($query_params['path'], '/neufs/') !== false) {
                $path_contains_neufs = true;
            }
     
            // Check if the URL contains '/fr/'
            $url_contains_fr = strpos($current_url, '/fr/') !== false;
    
            // page language is fr if either the slug ends with '-fr' or the URL contains '/fr/'
            $current_language =  ($slug_ends_with_fr || $url_contains_fr || $path_contains_neufs) ? 'fr' :'en';
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
        //var_dump($url);
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
	if ($this->isLocalUrl($url)){
		$parentUrl = '//' . $urlComponents['host'] .  $urlComponents['path'];
	}else{
		$parentUrl = $urlComponents['scheme'] . '://' . $urlComponents['host'] . $urlComponents['path'];
	}
        if (!empty($queryString)) {
            $parentUrl .= '?' . $queryString;
        }
    
        return array('parentUrl' => $parentUrl, 'path' => $this->path);
    }

    private function isLocalUrl($url) {
	// Parse the URL to get its components
        $urlComponents = parse_url($url);

	//Check if the host component of the URL is 'localhost' or '127.0.0.1'
	$isLocalHost = isset($urlComponents['host']) &&
		    ($urlComponents['host'] === 'localhost' || $urlComponents['host'] === '127.0.0.1');
	    
	// Check if the port component of the URL is '9081'
	$isLocalPort = isset($urlComponents['port']) && $urlComponents['port'] === 9081;
	    
	// Return true if either condition is met
	return $isLocalHost || $isLocalPort;
    }
	
    private function is_mobile_by_screen_size() {
        $width = $_SERVER['HTTP_SCREEN_WIDTH'];
        $height = $_SERVER['HTTP_SCREEN_HEIGHT'];
        
        // Define a threshold for screen width to classify as mobile
        $mobileScreenWidthThreshold = 768; // Adjust as needed
        
        // Check if either width or height is less than the threshold
        if ($width < $mobileScreenWidthThreshold || $height < $mobileScreenWidthThreshold) {
            return true;
        } else {
            return false;
        }
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
        //if (is_page($this->pageslug) && $this->data_fetched){
        if (is_singular() && has_shortcode(get_post()->post_content, 'd2cembedder') && $this->data_fetched){
            echo $this->metas;
        }
    }

    /**
     * adds the custom JS tags
    */
    public function add_custom_js_rl_tags() {
        $this->fetch_data();
        echo  $this->menu_new;
        if (is_singular() && has_shortcode(get_post()->post_content, 'd2cembedder') && $this->data_fetched){
            echo '<script id="d2c_js_inline" type="text/javascript">' . $this->js_inline . '</script>';
            echo '<script id="d2c_js_rl" type="text/javascript">' . $this->js_rl   .  $this->ajaxsetupcode .   '</script>';
        }
        else{
            if ($this->menu_new === null || trim($this->menu_new) === "") {
                echo "<!--D2C menu empty-->";
            } else {
                echo '<script id="d2c_js_oth" type="text/javascript">'  .  $this->otherPagecode .   '</script>';
            }

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
