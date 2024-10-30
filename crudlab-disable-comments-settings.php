<?php
if (!defined('ABSPATH'))
    exit;

class Crudlab_Disable_Comments_Settings {

    private $parrent = null;

    public function __construct(Crudlab_Disable_Comments &$parrent) {
        $this->parrent = $parrent;
    }

    public function addJSCSS() {
        add_action('wp_enqueue_scripts', array($this, 'registerJSCSS'));
    }

    public function registerJSCSS() {
        wp_register_style('cldc-bootstrap', plugins_url('dist/css/vendor/bootstrap/css/bootstrap.min.css', __FILE__), array(), '20120208', 'all');
        wp_register_style('cldc-style-flat', plugins_url('dist/css/flat-ui.css', __FILE__), array(), '20120208', 'all');
        wp_register_style('cldc-fontawsome', '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css', array(), '20120208', 'all');
        wp_register_style('cldc-magicsuggeststyle', plugins_url('dist/css/magicsuggest-min.css', __FILE__), array(), '20120208', 'all');

        wp_enqueue_style('cldc-bootstrap');
        wp_enqueue_style('cldc-style-flat');
        wp_enqueue_style('cldc-fontawsome');
        wp_enqueue_style('cldc-magicsuggeststyle');

        wp_register_script('cldc-custom', plugins_url('js/custom.js', __FILE__), array('jquery'));
        wp_register_script('cldc-radiocheck', plugins_url('/js/radiocheck.js?ver=4.4', __FILE__), array('jquery'));
        wp_register_script('cldc-magicsuggest', plugins_url('/js/magicsuggest-min.js', __FILE__), array('jquery'));
        wp_register_script('cldc-btswitch', plugins_url('/js/bootstrap-switch.min.js', __FILE__), array('jquery'));
        wp_register_script('cldc-tool-tip', plugins_url('/js/tooltip.js', __FILE__), array('jquery'));

        wp_enqueue_script('cldc-radiocheck');
        wp_enqueue_script('cldc-magicsuggest');
        wp_enqueue_script('cldc-btswitch');
        wp_enqueue_script('cldc-tool-tip');
        wp_enqueue_script('cldc-custom');
    }

    public function validateData() {
        return true;
    }

    public function changeSwitch() {
        $val = $_POST["cldc_switchonoff"];
        $obj = $this->parrent->getSettingsData();
        update_option(Crudlab_Disable_Comments::$optionName, serialize(array(
            'comment_text' => $obj["comment_text"],
            'where' => $obj["where"],
            'comment' => $obj["comment"],
            'display' => $obj["display"],
            'except_ids' => $obj["except_ids"],
            'tenacious' => $obj["tenacious"],
            'status' => $val,
        )));
        $this->parrent->reloadSettings();
        die;
    }

    public function saveData() {
        update_option(Crudlab_Disable_Comments::$optionName, serialize(array(
            'comment_text' => sanitize_text_field($_POST['comment_text']),
            'where' => sanitize_text_field($_POST['where']),
            'comment' => sanitize_text_field($_POST['comment']),
            'display' => sanitize_text_field(serialize($_POST['display'])),
            'except_ids' => sanitize_text_field(serialize($_POST['except_ids'])),
            'tenacious' => (isset($_POST['tenacious'])) ? 1 : 0,
            'status' => (isset($_POST['status'])) ? 1 : 0,
        )));
        $this->parrent->reloadSettings();
        if (isset($_POST["tenacious"]) && $this->tenacious_mode_allowed()) {
            $this->tenacious_mode_on();
        }
    }

    private function tenacious_mode_on() {

        $obj = $this->parrent->getSettingsData();
        $obj["display"] = unserialize($obj["display"]);
        $obj["except_ids"] = unserialize($obj["except_ids"]);
        global $wpdb;
        if ($this->networkactive) {
            $blogs = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d AND public = '1' AND archived = '0' AND deleted = '0'", $wpdb->siteid));
            foreach ($blogs as $id) {
                switch_to_blog($id);
                $this->close_comments_in_db($types);
                restore_current_blog();
            }
        } else {
            if ($obj["where"] == 0) {
                $types = array("page", "post", "attachment");
                $this->close_comments_in_db($types);
            } else if ($obj["where"] == 1) {
                if ($obj["display"][0] == 1) {
                    $this->close_comments_in_db("page");
                }
                if ($obj["display"][0] == 2 || $obj["display"][1] == 2) {
                    $this->close_comments_in_db("post");
                }
                if ($obj["display"][0] == 3 || $obj["display"][1] == 3 || $obj["display"][2] == 3) {
                    $this->close_comments_in_db("attachment");
                }
                if ($obj["display"][0] == 4 || $obj["display"][1] == 4 || $obj["display"][2] == 4 || $obj["display"][3] == 4) {
                    foreach ($obj["except_ids"] as $pid) {
                        $this->close_comments_in_db_by_id($pid);
                    }
                }
            }
        }
    }

    private function close_comments_in_db_by_id($pid) {
        global $wpdb;
        $wpdb->query("UPDATE `$wpdb->posts` SET `comment_status` = 'closed', ping_status = 'closed' WHERE `ID` = $pid");
    }

    private function close_comments_in_db($types) {
        global $wpdb;
        $bits = implode(', ', array_pad(array(), count($types), '%s'));
        $wpdb->query($wpdb->prepare("UPDATE `$wpdb->posts` SET `comment_status` = 'closed', ping_status = 'closed' WHERE `post_type` IN ( $bits )", $types));
    }

    private function tenacious_mode_allowed() {
        if (defined('DISABLE_COMMENTS_ALLOW_PERSISTENT_MODE') && DISABLE_COMMENTS_ALLOW_PERSISTENT_MODE == false) {
            return false;
        }
        return apply_filters('disable_comments_allow_persistent_mode', true);
    }

    public function renderPage() {
        global $CLDCBPath;
        error_reporting(0);
        $obj = $this->parrent->getSettingsData();
        $obj["display"] = unserialize($obj["display"]);
        $obj["except_ids"] = implode(', ', unserialize($obj["except_ids"]));
        ?>
        <div class="row">
            <div class="col-md-12">
                <h5>CRUDLab Disable Comments Settings</h5>
                <div class="col-md-8">
                    <form method="post">
                        <div class="well">
                            <div class="col-md-12">
                                Where would you like to disable comments?
                            </div>
                            <div class="col-md-12">
                                <ul>
                                    <li>
                                        <div class="form-group">
                                            <label class="radio" for="radio1">
                                                <input type="radio" name="where" data-toggle="radio" id="radio1" value="0" class="custom-radio"  <?php echo($obj["where"] == 0) ? " checked=''" : ""; ?>>
                                                Everywhere
                                            </label>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="form-group">
                                            <label class="radio" for="radio2">
                                                <input type="radio" name="where" data-toggle="radio" id="radio2" value="1" class="custom-radio" <?php echo($obj["where"] == 1) ? " checked=''" : ""; ?>>
                                                Pages, Posts or Media
                                            </label>
                                            <ul style="padding-left: 30px;">
                                                <li>
                                                    <label class="checkbox" for="display1">
                                                        <input type="checkbox" name="display[]" data-toggle="checkbox" id="display1" value="1" class="custom-checkbox" <?php echo($obj["display"][0] == 1) ? 'checked ' : ""; ?>>
                                                        All Pages
                                                    </label>
                                                </li>
                                                <li>
                                                    <label class="checkbox" for="display2">
                                                        <input type="checkbox" name="display[]" data-toggle="checkbox" id="display2" value="2" class="custom-checkbox" <?php echo($obj["display"][0] == 2 || $obj["display"][1] == 2) ? 'checked ' : ""; ?> >
                                                        All Posts
                                                    </label>
                                                </li>
                                                <li>
                                                    <label class="checkbox" for="display3">
                                                        <input type="checkbox" name="display[]" data-toggle="checkbox" id="display3" value="3" class="custom-checkbox" <?php echo($obj["display"][0] == 3 || $obj["display"][1] == 3 || $obj["display"][2] == 3) ? 'checked ' : ""; ?> >
                                                        All Media
                                                    </label>
                                                </li>
                                                <li>
                                                    <label class="checkbox" for="display4">
                                                        <input type="checkbox" name="display[]" data-toggle="checkbox" id="display4" value="4" class="custom-checkbox" <?php echo($obj["display"][0] == 4 || $obj["display"][1] == 4 || $obj["display"][2] == 4 || $obj["display"][3] == 4) ? 'checked ' : ""; ?> >
                                                        Disable comments on following pages and posts                                                    
                                                    </label>
                                                </li>
                                                <li class="magicsuggest" <?php echo($obj["display"][0] == 4 || $obj["display"][1] == 4 || $obj["display"][2] == 4 || $obj["display"][3] == 4) ? '' : 'style="display: none;" '; ?> >
                                                    <div class="col-md-11">
                                                        <div id="magicsuggest" class="col-md-12" value="[<?php echo $obj["except_ids"]; ?>]" name="except_ids[]" ></div>
                                                    </div>                                                
                                                    <i class="fa fa-question-circle" style="font-size: 19px;"  data-toggle="tooltip" title="You can type in page or post name and plugin will auto suggest post and page name. You can select as many pages or posts as you want. Or, simple click on down arrow and select pages or posts where you want to disable comments."></i>

                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                </ul>                            
                            </div>
                            <div class="clearfix"></div>
                        </div>
                        <?php /*
                        <div class="well">
                            <div class="col-md-12">
                                Would you like to show only text in place of comments?
                            </div>
                            <div class="col-md-12">
                                <ul>
                                    <li>
                                        <div class="form-group">
                                            <label class="radio" for="comment1">
                                                <input type="radio" name="comment" data-toggle="radio" id="comment1" value="0" class="custom-radio" <?php echo($obj["comment"] == 0) ? "checked" : ""; ?>>
                                                No
                                            </label>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="form-group">
                                            <label class="radio" for="comment2">
                                                <input type="radio" name="comment" data-toggle="radio" id="comment2" value="1" class="custom-radio" <?php echo($obj["comment"] == 1) ? "checked" : ""; ?>>
                                                Yes
                                            </label>
                                        </div>
                                    </li>
                                </ul>
                                <textarea class="col-md-12" name="comment_text"><?php echo $obj["comment_text"]; ?></textarea>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                        */ ?>
                        <div class="well">
                            <div class="form-group">
                                <label class="checkbox" for="tenacious">
                                    <input type="checkbox" name="tenacious" data-toggle="checkbox" id="tenacious" value="1" class="custom-checkbox" >
                                    Enable Tenacious Mode
                                    <i class="fa fa-question-circle" style="font-size: 19px;"  data-toggle="tooltip" title="This will be a permanent change to your database and can't be undone. Comments will permanently disabled even if you inactive and delete plugin. Please don't use it if you want to disable comments temporarily."></i>
                                </label>
                            </div>
                            <div class="clearfix"></div>
                        </div>
                        <div class="well">
                            <div class="pull-left">
                                <input type="submit" class="btn btn-block btn-lg btn-success col-md-2" name="update_cldisablecomments" value="Save Settings">
                            </div>
                            <div class="pull-right">
                                <input type="checkbox" data-toggle="switch" id="switchonoff" name="status" value="" <?php echo($obj["status"] == 1) ? "checked" : ""; ?> />
                            </div>
                            <div class="clearfix"></div>
                        </div>
                    </form>
                </div>                
            </div>    
        </div>
        <?php
    }
}
