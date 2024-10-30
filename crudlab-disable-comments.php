<?php

/*
  Plugin Name: CRUDLab Disable Comments
  Description: CRUDLab Disable Comments allows you to disable comments for any page or post or for whole site.
  Author: <a href="http://crudlab.com/">CRUDLab</a>
  Version: 1.0.5
 */

if (!defined('ABSPATH'))
    exit;
error_reporting(0);
$CLDCBPath = plugin_dir_path(__FILE__);
require_once $CLDCBPath . 'crudlab-disable-comments-settings.php';

class Crudlab_Disable_Comments {

    private $CLDCSettings = null;
    public static $optionName = 'cldisablecomments-options';

    const DB_VERSION = '1';

    private $menuSlug = "cl-disable-comments";
    private $settingsData = null;
    public static $defaultSettings = array(
        'comment_text' => "",
        'where' => 0,
        'comment' => 0,
        'display' => "",
        'except_ids' => "",
        'tenacious' => 0,
        'status' => 1
    );

    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function __construct() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_REQUEST["cldc_magic_data"])) {
            $this->getAllPostsPages();
            exit();
        }
        $this->CLDCSettings = new Crudlab_Disable_Comments_Settings($this);
        if (get_option(Crudlab_Disable_Comments::$optionName) == "") {
            update_option(Crudlab_Disable_Comments::$optionName, serialize(Crudlab_Disable_Comments::$defaultSettings));
            $this->settingsData = unserialize(get_option(Crudlab_Disable_Comments::$optionName));
        } else {
            $this->settingsData = unserialize(get_option(Crudlab_Disable_Comments::$optionName));
        }
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST["cldc_switchonoff"])) {
            $this->CLDCSettings->changeSwitch();
            exit();
        }

        add_action('admin_menu', array($this, 'setup_menu'));

        add_action('wp_ajax_clgbactive', array($this, 'activePlugin'));
        add_action('wp_ajax_clgbphtml', array($this, 'getPopHtml'));

        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_$plugin", array($this, 'settingsLink'));
        register_deactivation_hook(__FILE__, array($this, 'cldc_uninstall_hook'));
        $this->initialize_filters();
    }

    public function cldc_uninstall_hook() {
        delete_option(Crudlab_Disable_Comments::$optionName);
    }

    public function initialize_filters() {
        $settings = $this->settingsData;
        if ($settings["status"] == 1) {
            if ($settings['where'] == 0) {
                add_action('widgets_init', array($this, 'disable_recent_comment_widget'));
                add_filter('wp_headers', array($this, 'filter_x_pingback_from_wp_headers'));
                add_action('template_redirect', array($this, 'closed_comment_page'), 9);

                // Admin bar filtering has to happen here since WP 3.6
                add_action('template_redirect', array($this, 'remove_comments_link_admin_bar'));
                add_action('admin_init', array($this, 'remove_comments_link_admin_bar'));
            }
            add_action('wp_loaded', array($this, 'initialize_filters_on_wp_load'));
        }
    }

    public function closed_comment_page() {
        if (is_comment_feed()) {
            if ($this->settingsData["comment"] == null || $this->settingsData["comment"] == 0) {
                wp_die(__('Comments are closed.'), '', array('response' => 403));
            } else {
                wp_die(__($this->settingsData["comment_text"]), '', array('response' => 403));
            }
        }
    }

    public function closed_comment_page_admin() {
        global $pagenow;

        if ($pagenow == 'comment.php' || $pagenow == 'edit-comments.php' || $pagenow == 'options-discussion.php')
            if ($this->settingsData["comment"] == null || $this->settingsData["comment"] == 0) {
                wp_die(__('Comments are closed.'), '', array('response' => 403));
            } else {
                wp_die(__($this->settingsData["comment_text"]), '', array('response' => 403));
            }

        remove_menu_page('edit-comments.php');
        remove_submenu_page('options-general.php', 'options-discussion.php');
    }

    public function disable_recent_comment_widget() {
        unregister_widget('WP_Widget_Recent_Comments');
    }

    public function filter_x_pingback_from_wp_headers($headers) {
        unset($headers['X-Pingback']);
        return $headers;
    }

    public function remove_comments_link_admin_bar() {
        if (is_admin_bar_showing()) {
            remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 50); // WP<3.3
            remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60); // WP 3.3
        }
    }

    public function initialize_filters_on_wp_load() {
        $settings = $this->settingsData;
        $type = 'post';
        $this->modified_types[] = $type;
        remove_post_type_support($type, 'comments');
        remove_post_type_support($type, 'trackbacks');

        add_filter('comments_open', array($this, 'filter_comment_status'), 20, 2);
        add_filter('pings_open', array($this, 'filter_comment_status'), 20, 2);

        if (is_admin()) {
            if ($settings["where"] == 0) {
                add_action('admin_menu', array($this, 'closed_comment_page_admin'), 9999);
                add_action('admin_head', array($this, 'hide_comments_side_option_admin_dashboard'));
                add_action('wp_dashboard_setup', array($this, 'filter_dashboard'));
                add_filter('pre_option_default_pingback_flag', '__return_zero');
            }
        } else {
            add_action('template_redirect', array($this, 'check_comment_for_front_website'));
        }
    }

    public function hide_comments_side_option_admin_dashboard() {
        if ('dashboard' == get_current_screen()->id)
            add_action('admin_print_footer_scripts', array($this, 'hide_comments_side_option_admin_dashboard_js'));
    }

    public function hide_comments_side_option_admin_dashboard_js() {
        if (version_compare($GLOBALS['wp_version'], '3.8', '<')) {
            echo '<script> jQuery(function($){ $("#dashboard_right_now .table_discussion").has(\'a[href="edit-comments.php"]\').first().hide(); }); </script>';
        } else {
            echo '<script> jQuery(function($){ $("#dashboard_right_now .comment-count, #latest-comments").hide(); }); </script>';
        }
    }

    public function filter_comment_status($open, $post_id) {
        $post = get_post($post_id);
        $settings = $this->settingsData;
        $settings["display"] = unserialize($settings["display"]);
        $settings["except_ids"] = unserialize($settings["except_ids"]);
        if ($settings["status"] == 0) {
            return $open;
        } else if ($settings["where"] == 0) {
            return FALSE;
        } else {
            //All pages
            if ($settings["display"][0] == 1 && $post->post_type == "page") {
                return FALSE;
            } else if (($settings["display"][0] == 2 || $settings["display"][1] == 2) && $post->post_type == "post") { // All posts
                return FALSE;
            } else if (($settings["display"][0] == 3 || $settings["display"][1] == 3 || $settings["display"][2] == 3) && $post->post_type == "attachment") { // All Media
                return FALSE;
            } else if ($settings["display"][0] == 4 || $settings["display"][1] == 4 || $settings["display"][2] == 4 || $settings["display"][3] == 4) { // disable comments on selected                        
                if (in_array($post_id, $settings["except_ids"])) {
                    return FALSE;
                } else {
                    return $open;
                }
            } else {
                return $open;
            }
        }
    }

    public function check_comment_for_front_website() {
        $settings = $this->settingsData;
        $settings["display"] = unserialize($settings["display"]);
        $settings["except_ids"] = unserialize($settings["except_ids"]);
        if (is_singular() && ( $settings["where"] == 0 || ($settings["display"][0] == 1 && get_post_type() == "page") ||
                (($settings["display"][0] == 2 || $settings["display"][1] == 2) && get_post_type() == "post") ||
                (($settings["display"][0] == 3 || $settings["display"][1] == 3 || $settings["display"][2] == 3) && get_post_type() == "attachment") ||
                (($settings["display"][0] == 4 || $settings["display"][1] == 4 || $settings["display"][2] == 4 || $settings["display"][3] == 4) && in_array(get_the_ID(), $settings["except_ids"]))
                )) {
            if (!defined('DISABLE_COMMENTS_REMOVE_COMMENTS_TEMPLATE') || DISABLE_COMMENTS_REMOVE_COMMENTS_TEMPLATE == true) {
                add_filter('comments_template', array($this, 'crudlab_disable_comments_empty_comment'), 20);
            }
            wp_deregister_script('comment-reply');
            remove_action('wp_head', 'feed_links_extra', 3);
        }
    }

    public function crudlab_disable_comments_empty_comment() {
        $obj = $this->settingsData;
        if ($obj["comment"] == 1) {
            $myfile = fopen(dirname(__FILE__) . "/crudlab-disable-comments-custom-comment.php", "w");
            $txt = $obj["comment_text"];
            fwrite($myfile, $txt);
            fclose($myfile);
            return dirname(__FILE__) . '/crudlab-disable-comments-custom-comment.php';
        } else {
            return dirname(__FILE__) . '/crudlab-disable-comments-empty-comment.php';
        }
    }

    public function reloadSettings() {
        $this->settingsData = unserialize(get_option(Crudlab_Disable_Comments::$optionName));
    }

    function settingsLink($links) {
        $settings_link = '<a href="admin.php?page=' . $this->menuSlug . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function getMenuSlug() {
        return $this->menuSlug;
    }

    public function setMenuSlug($menuSlug) {
        $this->menuSlug = $menuSlug;
    }

    public function getSettingsData() {
        return $this->settingsData;
    }

    public function setup_menu() {
        $set = $this->getSettingsData();
        if ($set['status'] == 0) {
            add_menu_page('CRUDLab Disable Comments', 'CL Disable Comments <span  class="update-plugins count-1" id="cldc_circ" style="background:#F00"><span class="plugin-count">&nbsp&nbsp</span></span>', 'manage_options', $this->menuSlug, array($this, 'admin_settings'), plugins_url('img/ico.png', __FILE__));
        } else {
            add_menu_page('CRUDLab Disable Comments', 'CL Disable Comments <span class="update-plugins count-1" id="cldc_circ" style="background:#0F0"><span class="plugin-count">&nbsp&nbsp</span></span>', 'manage_options', $this->menuSlug, array($this, 'admin_settings'), plugins_url('img/ico.png', __FILE__));
        }
    }

    public function getAllPostsPages() {
        $data = '';
        $args = array(
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => -1
        );
        $posts = get_posts($args);
        foreach ($posts as $post) {
            $data[] = array('id' => $post->ID, 'name' => $post->post_title);
        }
        echo json_encode($data);
        exit();
    }

    function admin_settings() {
        $this->CLDCSettings->registerJSCSS();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->CLDCSettings->validateData()) {
                $this->CLDCSettings->saveData();
            }
        }
        $this->CLDCSettings->renderPage();
    }

}

global $crudlab_disable_comments;
$crudlab_disable_comments = new Crudlab_Disable_Comments();
