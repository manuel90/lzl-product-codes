<?php
/*
Plugin Name: LzL Product Codes
Plugin URI: https://github.com/manuel90
Description: Add codes to woocommerce products.
Version: 0.0.1
Author: Manuel
Author URI: https://github.com/manuel90
License: GPLv2 or later
Text Domain: lzl-product-codes
*/

define('LZL_PRODUCT_CODES_TBL_NAME','product_codes_lzl');

class LZL_Product_Codes {


    public function __construct() {
        add_filter( 'woocommerce_product_data_tabs', array($this,'tabCodes'), 10 );
        add_action( 'woocommerce_product_data_panels', array($this,'panelCodes') );
        add_action( 'admin_footer', array($this,'adminFooter') );
        add_action( 'admin_enqueue_scripts', array($this,'scripts') );
        add_action( 'wp_ajax_lzlapcode2', array($this,'ajaxCheckIfExists') );
        add_action( 'wp_ajax_lzlspcode7', array($this,'ajaxGetHtmlCodesProduct') );
        add_action( 'wp_ajax_lzlsemailu5', array($this,'ajaxSendCodeEmail') );
        
        add_action( 'save_post', array($this,'saveCodes') );

        add_action( 'confirmation_payment_larep', array($this,'responsePayment'), 10, 2);
        add_filter( 'report_str_product', array($this,'filterDataReportProduct'), 10, 2);
        add_filter( 'list_products_home', array($this,'filterListProductsHome'), 10);
        //add_action( 'phpmailer_init', array($this,'mailFilter'), 18, 1);

        load_plugin_textdomain( 'lzl-product-codes', false, basename( dirname( __FILE__ ) ) . '/languages' );
    }

    public function filterListProductsHome($products) {
        $new_list = [];
        foreach($products as $key=>$p) {
            if( count( self::getCodesProductAvailable($p->ID) ) > 0 ) {
                $new_list[] = $p;
            }
        }
        return $new_list;
    }

    public function filterDataReportProduct($str_p,$data_product) {
        $product_id = $data_product['product_id'];
        $list_codes = self::getCodesProduct($product_id);

        $list = [];
        foreach($list_codes as $item) {
            if( $item->user_email ) {
                $list[] = sprintf(__('%s asociate to %s','lzl-product-codes'), $item->code, $item->user_email);
            }
         }

        if( empty($list) ) {
            return $str_p;
        }

        return $str_p."\n".__('List Codes:','lzl-product-codes')."\n".implode("\n",$list)."\n-----------";
    }

    public function responsePayment($order,$request) {

        $items = $order->get_items();

        global $wpdb;
        $table_name = $wpdb->prefix.LZL_PRODUCT_CODES_TBL_NAME;

        $to = $order->get_data()['billing']['email'];

        foreach($items as $product) {
            $product_id = $product->get_product_id();

            $list_codes = self::getCodesProduct($product_id);

            if( empty($list_codes) ) {
                continue;
            }

            $subject = get_post_meta($product_id,'lzl_custom_subject_codes',true);
            $message = get_post_meta($product_id,'lzl_custom_message_codes',true);

            if( empty($subject) || empty($message) ) {
                continue;
            }
            
            $html_codes = [];
            foreach ($list_codes as $item) {
                if( !$item->user_email ) {
                    $html_codes[] = $item->code;
                }
            }

            if( empty($html_codes) ) {
                continue;
            }

            $message = str_replace(['{email}','{codes}'],[$to,'<ul><li>'.implode('</li><li>',$html_codes).'</li></ul>'],$message);

            if( !self::sendMail($to, $subject, $message) ) {
                self::sendMail(get_option('admin_email'), 'ERROR: '.$subject, $message);
            }
            foreach($html_codes as $code) {
                
                $updated = $wpdb->update( 
                    $table_name, 
                    array( 
                        'user_email' => $to,
                        'status' => 1,
                    ), 
                    array( 'code' => $code ), 
                    array( 
                        '%s',
                        '%d',
                    ), 
                    array( '%s' ) 
                );
            }
        }

    }

    public function mailFilter(&$phpmailer) {

        if( strtolower($phpmailer->ContentType) == 'text/html' ) {
            //return;
        }
        $wc_mailers = WC()->mailer();
    
        $wc_mailer = new WC_Email();
    
        $phpmailer->isHTML( true );
    
        if( preg_match('@<https?.*>@i',$phpmailer->Body,$matches) ) {
            foreach ($matches as $val) {
                $url = substr($val, 1, -1);
                $phpmailer->Body = str_replace($val, '<a target="_blank" href="'.$url.'">'.$url.'</a>', $phpmailer->Body);
            }
        }
        ob_start();
        $email_heading = $phpmailer->Subject;
        $message = nl2br($phpmailer->Body);
        include_once('templates/wc-email.php');
        $body_message = ob_get_clean();

        $phpmailer->Body = $wc_mailer->style_inline($body_message);
    }

    public static function sendMail($to,$subject,$message) {
        $wc_mailers = WC()->mailer();
    
        $wc_mailer = new WC_Email();
        
        ob_start();
        $email_heading = $subject;
        $message = nl2br($message);
        include_once('templates/wc-email.php');
        $body_message = $wc_mailer->style_inline( ob_get_clean() );
        return wp_mail($to,$subject,$body_message,['Content-Type: text/html']);
    }
    

    public static function statusMean($item) {
        
        switch ($item->status) {
            case '0':
                return __('Available','lzl-product-codes');
            case '1':
                if( $item->user_email ) {
                    return sprintf( __('Assign to %s','lzl-product-codes'), '<a target="_blank" href="'.site_url('/wp-admin/users.php?s='.$item->user_email).'">'.$item->user_email.'</a>' );
                }
                return sprintf( __('Assign','lzl-product-codes') );
            case '2':
                return __('Used','lzl-product-codes');
            case '3':
                return __('Pending','lzl-product-codes');            
            default:
                throw new Exception( sprintf(__('Status is invalid: %s','lzl-product-codes'),$item->status) );
                break;
        }
    }

    public static function getCodesProduct($product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix.LZL_PRODUCT_CODES_TBL_NAME;
        return $wpdb->get_results( sprintf('SELECT * FROM '.$table_name.' WHERE post_id = %d;', $product_id) );
    }

    public static function getCodesProductAvailable($product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix.LZL_PRODUCT_CODES_TBL_NAME;
        return $wpdb->get_results( sprintf('SELECT * FROM '.$table_name.' WHERE post_id = %d AND status = "0";', $product_id) );
    }

    public function ajaxSendCodeEmail() {
        
        if( empty($_POST['product']) ) {
            wp_send_json_error( __('Product ID is empty','lzl-product-codes') );
        }

        if( empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ) {
            wp_send_json_error( __('Invalid email','lzl-product-codes') );
        }
        $product_id = $_POST['product'];
        
        global $wpdb;
        $table_name = $wpdb->prefix.LZL_PRODUCT_CODES_TBL_NAME;

        $code = trim( $_POST['code'] );

        $to = trim( $_POST['email'] );
        $subject = get_post_meta($product_id,'lzl_custom_subject_codes',true);
        $message = get_post_meta($product_id,'lzl_custom_message_codes',true);
        if( empty($subject) ) {
            wp_send_json_error( __('The Subject email is empty','lzl-product-codes') );
        }

        if( empty($message) ) {
            wp_send_json_error( __('The Body Message email is empty','lzl-product-codes') );
        }

        $message = str_replace(['{email}','{code}'],[$to,$code],$message);

        if( !self::sendMail($to, $subject, $message) ) {
            wp_send_json_error( __('Error sending the email try again later','lzl-product-codes') );
        }

        $updated = $wpdb->update( 
            $table_name, 
            array( 
                'user_email' => $to,
                'status' => 1,
            ), 
            array( 'code' => $code ), 
            array( 
                '%s',
                '%d',
            ), 
            array( '%s' ) 
        );

        if( $updated === false ) {
            wp_send_json_error( __('Error sending the email try again later','lzl-product-codes') );
        }

        wp_send_json_success( __('Email was sended','lzl-product-codes') );
    }

    public function ajaxGetHtmlCodesProduct() {
        if( empty($_POST['product']) ) {
            wp_send_json_error( __('Product ID is empty','lzl-product-codes') );
        }
        $product_id = $_POST['product'];
        
        $results = self::getCodesProduct($product_id);
        if( count($results) > 0 ) {
            ob_start(); 
            ?>
            <div class="wrap-table-codes-results">
                <table class="codes-results">
                    <thead>
                        <tr>
                            <th><?php _e('Code','lzl-product-codes'); ?></th>
                            <th><?php _e('Status','lzl-product-codes'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($results as $item) { ?>
                        <tr>
                            <td><?php echo $item->code; ?></td>
                            <td><?php
                            echo LZL_Product_Codes::statusMean( $item );
                            /*if( $item->status == 0 ) { ?>
                            <hr/>
                            <input type="email" name="lzl_sendeto<?php echo $item->code; ?>"  />
                            <a class="lzl-btn-send-email button button-secondary" href="#" data-code="<?php echo $item->code; ?>" data-product="<?php echo $item->post_id; ?>"><?php _e('Send','lzl-product-codes'); ?></a>
                            <hr/>
                            <?php }*/
                            ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <div id="lzl-modal-messages" class="lzl-panel-view-messages" style="display: none;"></div>
            <?php
            $html = ob_get_clean();
        } else {
            $html = '<b>'.__('Codes not found to this product.','lzl-product-codes').'</b>';
        }

        wp_send_json_success( $html );
    }
    
    public function saveCodes($post_id) {

        global $wpdb;
        
        if ( $parent_id = wp_is_post_revision( $post_id ) ) {
            $post_id = $parent_id;
        }

        if ( !empty($_POST['lzl_codes']) ) {

            $values = [];
            foreach($_POST['lzl_codes'] as $code) {
                if( $this->exists( $code ) ) {
                    continue;
                }
                $values[] = sprintf('(%d,%s)',$post_id,$code);
            }

            $table_name = $wpdb->prefix.LZL_PRODUCT_CODES_TBL_NAME;
            $r = $wpdb->query('INSERT INTO '.$table_name.'(post_id,code) VALUES '.implode(',', $values).';');
        }

        if ( !empty($_POST['lzl_body_message_codes']) ) {
            update_post_meta($post_id,'lzl_custom_message_codes', trim($_POST['lzl_body_message_codes']));
        }
        if ( !empty($_POST['lzl_subject_codes']) ) {
            update_post_meta($post_id,'lzl_custom_subject_codes', trim($_POST['lzl_subject_codes']));
        }

        
    }

    public function exists($code) {
        global $wpdb;
        $table_name = $wpdb->prefix.LZL_PRODUCT_CODES_TBL_NAME;
        $row = $wpdb->get_row( sprintf('SELECT * FROM '.$table_name.' WHERE code = %s LIMIT 1;',$code) );
        return !(!$row);
    }

    public function ajaxCheckIfExists() {
        if( empty($_POST['code']) ) {
            wp_send_json_error( __('Code is empty','lzl-product-codes') );
        }
        $code = $_POST['code'];
        if( $this->exists( $code ) ) {
            wp_send_json_error( __('Code exists','lzl-product-codes') );
        }

        wp_send_json_success( __('Code not found','lzl-product-codes') );
    }


    public function tabCodes($tabs) {
        $tabs['option_codes_lzl'] = array(
            'label' => __('Options Codes','lzl-product-codes'),
            'target' => 'lzl_tab_product_codes',
            'class' => [''],
            'priority' => 90
        );
        return $tabs; 
    }

    public function panelCodes() {
            global $post;

            $cmessage = "";
            
            if( get_post_meta($post->ID,'lzl_custom_message_codes',true) ) {
                $cmessage = get_post_meta($post->ID,'lzl_custom_message_codes',true);
            }
            $csubject = "";

            if( get_post_meta($post->ID,'lzl_custom_subject_codes',true) ) {
                $csubject = get_post_meta($post->ID,'lzl_custom_subject_codes',true);
            }
            ?>
            <div id="lzl_tab_product_codes" class="panel woocommerce_options_panel">
                <?php
                $codes = [];
                ?>
                <div class="options_group">
                    <p class="form-field">
                        <a id="btn-view-codes-product" data-product="<?php echo $post->ID; ?>" class="button button-secondary" href="#"><?php _e('See codes','lzl-product-codes'); ?></a>
                    </p>
                    <p class="form-field">
                        <label><?php _e('Subject email','lzl-product-codes'); ?></label>
                        <input type="text" name="lzl_subject_codes" value="<?php echo $csubject; ?>" />
                    </p>
                    <p class="form-field">
                        <label><?php _e('Body Message email','lzl-product-codes'); ?></label>
                        <textarea name="lzl_body_message_codes" style="margin-top: 0px; margin-bottom: 0px; height: 135px;"><?php echo $cmessage; ?></textarea>
                    </p>
                </div>
                <div class="options_group">
                    <p class="form-field">
                        <label><?php _e('Code','lzl-product-codes'); ?></label>
                        <input id="ipt-code-pcodes" type="text" value="" placeholder="<?php _e('Code','lzl-product-codes'); ?>" />&nbsp;
                        <a id="btn-add-form-codes" class="button button-primary" href="#" data-msg-empty-code="<?php _e('Code value is empty','lzl-product-codes'); ?>" data-msg-code-exists="<?php _e('Code exists','lzl-product-codes'); ?>"><?php _e('Add code','lzl-product-codes'); ?></a>
                    </p>
                </div>
                <div id="panel-view-codes-messages" class="lzl-panel-view-messages" style="display: none;"></div>
                <ul id="panel-view-codes-added"></ul>
            </div>
            <?php
            
        }

        public function adminFooter() {
            
        }

        public function scripts() {
            wp_register_script( 'lzl-product-codes-js', plugins_url('/js/scripts.js',__FILE__), ['jquery'], '1.0.0', true );

            wp_enqueue_script( 'lzl-product-codes-js' );

            wp_register_style( 'lzl-product-codes-css', plugins_url('/css/styles.css',__FILE__) );

            wp_enqueue_style( 'lzl-product-codes-css' );
        }

}

function lzl_product_codes_install() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix.LZL_PRODUCT_CODES_TBL_NAME;

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, 
            `code` varchar(255) NOT NULL UNIQUE,
            `post_id` bigint(20) unsigned NOT NULL,
            `user_email` varchar(100),
            `status` char(1) DEFAULT '0' comment \"0=available, 1=assign, 2=used, 3=pending\",
            CONSTRAINT Pk_$table_name PRIMARY KEY (`id`),
            CONSTRAINT Fk_posts FOREIGN KEY (`post_id`) REFERENCES {$wpdb->prefix}posts(ID)
        );";

    

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    dbDelta( $sql );
}
register_activation_hook( __FILE__,  'lzl_product_codes_install' );

function lzl_product_codes_when_activate() {
        $pgl_status = get_option('lzl_pro_codes_pgl_status');

        if ( !is_plugin_active('woocommerce/woocommerce.php') && $pgl_status != 'active' ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e( 'WooCommerce plugin is required', 'lzl-product-codes' ); ?></p>
            </div>
            <?php
            deactivate_plugins(plugin_basename(__FILE__));
            return;
        }

        if ( $pgl_status != 'active' && is_plugin_active(plugin_basename(__FILE__)) ) {
            update_option('lzl_pro_codes_pgl_status', 'active');
        }

    }

add_action( 'admin_notices', 'lzl_product_codes_when_activate' );

function lzl_product_codes_when_deactivate() {
    update_option('lzl_pro_codes_pgl_status', 'inactive');
}
register_deactivation_hook( __FILE__, 'lzl_product_codes_when_deactivate' );

new LZL_Product_Codes();