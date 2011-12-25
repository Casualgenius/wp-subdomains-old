<?php

class Wps_Plugin
{
    const VERSION = '2.0.0';
    
    const WP_VERSION_MIN = '3.0.0';
    
    const METAKEY_THEME = '_wps_page_theme';
    const METAKEY_SUBDOMAIN = '_wps_page_subdomain';
    const METAKEY_TIE = '_wps_tie_to_category';
    const METAKEY_SHOWALL = '_wps_showall';
    const METAKEY_ONMAININDEX = '_wps_on_main_index';

    const TYPE_CATEGORY = 'category';
    const TYPE_PAGE = 'page';
    const TYPE_AUTHOR = 'author';

    const OPTION_DOMAIN = 'wps_domain';
    const OPTION_SUBPAGES = 'wps_subpages';
    const OPTION_SUBAUTHORS = 'wps_subauthors';
    const OPTION_THEMES = 'wps_themes';
    const OPTION_ARCFILTER = 'wps_arcfilter';
    const OPTION_TAGFILTER = 'wps_tagfilter';
    const OPTION_PAGEFILTER = 'wps_pagefilter';
    const OPTION_SUBALL = 'wps_subdomainall';
    const OPTION_NOCATBASE = 'wps_nocatbase';
    const OPTION_REDIRECTOLD = 'wps_redirectold';
    const OPTION_DISABLED = 'wps_disabled';
    const OPTION_KEEPPAGESUB = 'wps_keeppagesub';
    const OPTION_SUBISINDEX = 'wps_subisindex';
    const OPTION_ATTACHMENT = 'wps_attachment';
    
    const VALUE_ON = 'on';
    
    /**
     * @var Wps_Subdomains
     */
    protected $_subdomains;
    
    /**
     * @var Wps_Subdomain
     */
    protected $_subdomain;
    
    /**
     * @var Wps_Admin
     */
    protected $_admin;
    
    /**
     * @var Wps_Hooks_Actions
     */
    protected $_actions;
    
    /**
     * @var Wps_Hooks_RewriteRules
     */
    protected $_rewriteRules;
    
    /**
     * @var Wps_Hooks_Filters
     */
    protected $_filters;
    
    /**
     * @var array
     */
    protected $_showAllPages;
    
    /**
     * @var string
     */
    protected $_categoryBase;
    
    /**
     * @var boolean
     */
    protected $_permalinkSet;
    
    function __construct ()
    {
        // @todo check this still required
        create_initial_taxonomies();
        
        // create the SubDomains Object
        $this->_subdomains = new Wps_Subdomains();
        
        // grab This Subdomain object (if we're on one)
        $this->_subdomain = $this->_subdomains->getThisSubdomain();
        
        // add the admin section
        $this->_addAdmin();
        
        // If the permalink is configured then we can setup everything else
        if ($this->isPermalinkSet() && (get_option(self::OPTION_DISABLED) == '')) {
            // add the Actions
            $this->_addActions();
            
            // add the rewrite rules
            $this->_addRewriteRules();
            
            // add the Filters
            $this->_addFilters();
            
            // add the Widgets
            $this->_addWidgets();
        }
    }

    public function getSubdomain ()
    {
        return $this->_subdomain;
    }

    public function getSubdomains ()
    {
        return $this->_subdomains;
    }
    
    public function showAllPages ()
    {
        if (! isset($this->_showAllPages)) {
            global $wpdb;
            
            $query = "SELECT Post_ID FROM {$wpdb->postmeta} WHERE meta_key = '" . self::METAKEY_SHOWALL .
             "' and meta_value = 'true'";
            
            $this->_showAllPages = $wpdb->get_col($query);
        }
        
        return $this->_showAllPages;
    }

    public function getCategoryBase ()
    {
        if (! isset($this->_categoryBase)) {
            if (get_option('category_base')) {
                $this->_categoryBase = get_option('category_base') . '/';
            } else {
                $this->_categoryBase = 'category/';
            }
        }
        
        return $this->_categoryBase;
    }

    public function isPermalinkSet ()
    {
        if (! isset($this->_permalinkSet)) {
            $this->_permalinkSet = get_option('permalink_structure') ? true : false;
        }
        
        return $this->_permalinkSet;
    }
    
    protected function _addAdmin()
    {
        $this->_admin = new Wps_Admin($this);
        
        // add Admin Menu Pages
        add_action('admin_menu', array($this->_admin, 'wps_add_options'));
        
        // add Admin Init
        add_action('admin_init', array($this->_admin, 'wps_admin_init'));
    }

    protected function _addActions ()
    {
        $this->_actions = new Wps_Hooks_Actions($this);
        
        add_action('init', array($this->_actions, 'wps_init'), 2);
        
        // Only redirect pages when not in admin section
        if (! is_admin()) {
            add_action('wp', array($this->_actions, 'wps_redirect'));
        }
        
        add_action('edit_category', array($this->_actions, 'wps_edit_category'));
        
        add_action('parse_query', array($this->_actions, 'wps_action_parse_query'));
        
        // use action method for adding Subdomain options to Edit Category
        add_action('edit_category_form_fields', array($this->_actions, 'wps_action_edit_category'));
        
        // Add Meta Boxes to Page edit
        add_action('do_meta_boxes', array($this->_actions, 'wps_action_page_meta'), 10, 3);
        
        // Save Meta Boxes
        add_action('save_post', array($this->_actions, 'wps_action_page_meta_save'));
    }

    protected function _addRewriteRules ()
    {
        $this->_rewriteRules = new Wps_Hooks_RewriteRules($this);
        
        add_filter('rewrite_rules_array', array($this->_rewriteRules, 'wps_rewrite_rules'));
        add_filter('root_rewrite_rules', array($this->_rewriteRules, 'wps_root_rewrite_rules'));
        add_filter('post_rewrite_rules', array($this->_rewriteRules, 'wps_post_rewrite_rules'));
        add_filter('page_rewrite_rules', array($this->_rewriteRules, 'wps_page_rewrite_rules'));
        add_filter('date_rewrite_rules', array($this->_rewriteRules, 'wps_date_rewrite_rules'));
        add_filter('tag_rewrite_rules', array($this->_rewriteRules, 'wps_tag_rewrite_rules'));
        add_filter('category_rewrite_rules', array($this->_rewriteRules, 'wps_category_rewrite_rules'));
        add_filter('author_rewrite_rules', array($this->_rewriteRules, 'wps_author_rewrite_rules'));
    }
    
    protected function _addFilters ()
    {
        $this->_filters = new Wps_Hooks_Filters($this);
        
        // Filters for Adjacent Posts
        // FIXME: Check args getting through
        add_filter('get_previous_post_join', array($this->_filters, 'wps_filter_adjacent_join'));
        add_filter('get_next_post_join', array($this->_filters, 'wps_filter_adjacent_join'));
        add_filter('get_previous_post_where', array($this->_filters, 'wps_filter_adjacent_where'));
        add_filter('get_next_post_where', array($this->_filters, 'wps_filter_adjacent_where'));
        
        // Filters for Archives
        add_filter('getarchives_where', array($this->_filters, 'wps_filter_archive_where'), 10, 2);
        add_filter('getarchives_join', array($this->_filters, 'wps_filter_archive_join'), 10, 2);
        
        // Filter for Tag Cloud
        add_filter('widget_tag_cloud_args', array($this->_filters, 'wps_filter_tag_cloud'), 10, 2);
        add_filter('get_terms', array($this->_filters, 'wps_filter_get_terms'), 10, 3);
        
        // Need for attachment
        if (get_option(Wps_Plugin::OPTION_ATTACHMENT)) {
            add_filter('the_content', array($this->_filters, 'wps_filter_content'));
            add_filter('wp_get_attachment_url', array($this->_filters, 'wps_filter_attachement_url'));
        }
        
        add_filter('pre_option_template', array($this->_filters, 'wps_change_template'));
        add_filter('pre_option_stylesheet', array($this->_filters, 'wps_change_template'));
        
        //add_filter('wp_login', 'csd_wp_login');
        //add_filter('wp_logout', 'csd_wp_logout');
        

        // Not yet needed
        //add_filter( 'redirect_canonical', 'wps_redirect_canonical', 10, 2 );
        

        add_filter('get_pages', array($this->_filters, 'wps_filter_pages'), 10);
        
        /* URL Filters */
        add_filter('bloginfo_url', array($this->_filters, 'wps_filter_bloginfo_url'), 10, 2);
        add_filter('bloginfo', array($this->_filters, 'wps_filter_bloginfo'), 10, 2);
        add_filter('category_link', array($this->_filters, 'wps_category_link'), 10, 2);
        add_filter('post_link', array($this->_filters, 'wps_post_link'), 10, 2);
        add_filter('page_link', array($this->_filters, 'wps_page_link'), 10, 2);
        add_filter('author_link', array($this->_filters, 'wps_author_link'), 10, 2);
        add_filter('tag_link', array($this->_filters, 'wps_tag_link'), 10);
        add_filter('month_link', array($this->_filters, 'wps_month_link'));
        add_filter('get_pagenum_link', array($this->_filters, 'wps_filter_general_url'));
        add_filter('list_cats', array($this->_filters, 'wps_list_cats'), 10, 2);
    }
    
    protected function _addWidgets()
    {
        $widgetSitelist = new Wps_Widgets_Sitelist($this);
        add_action('widgets_init', array($widgetSitelist, 'init'));
        
        $widgetCategories = new Wps_Widgets_Categories($this);
        add_action('widgets_init', array($widgetCategories, 'init'));
    }
}