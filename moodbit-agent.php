<?php
/**
 * Moodbit Agent
 *
 * @copyright Copyright (C) 2024, Moodbit Inc - support@mymoodbit.com
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name: Moodbit Agent
 * Version:     1.0.0
 * Description: Moodbit Agent integrates Moodbit Copilot with your WordPress site to automate content creation.
 * License:     GPL v3
 * Requires at least: 5.4.0
 * Requires PHP: 5.6
 *
 * Moodbit Agent is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodbit Agent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodbit Agent.  If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

class MoodbitAgent{
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('moodbit-wordpress/v1', '/check-connection/', array(
            'methods' => 'POST',
            'callback' => array($this, 'check_connection'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        register_rest_route('moodbit-wordpress/v1', '/create-post/', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_post'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }

    // Function to verify the connection
    public function check_connection(WP_REST_Request $request){
        $params = $request->get_json_params();
        $user = wp_authenticate($params['username'], $params['password']);
        if (is_wp_error($user)) {
            $error_code = $user->get_error_code();
            return new WP_Error($error_code, $user->get_error_message($error_code), [ 'status' => 401]);
        }
        $site_url = get_site_url();
        return rest_ensure_response(array('site_url' => $site_url));
    }

    // Function to check_credentials the user
    public function check_credentials($username, $password) {
        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            $error_code = $user->get_error_code();
            return new WP_Error($error_code, $user->get_error_message($error_code), [ 'status' => 401]);
        }
        return true;
    }

    // Helper function to upload an image from a URL
    // public function upload_image_from_url($image_url, $post_id) {
    //     // Download the image
    //     $tmp = download_url($image_url);
    //     if (is_wp_error($tmp)) {
    //         return $tmp;
    //     }

    //     // Set up the file array
    //     $file = array(
    //         'name'     => basename($image_url),
    //         'tmp_name' => $tmp,
    //     );

    //     // Upload the file to WordPress media library
    //     $attachment_id = media_handle_sideload($file, $post_id);
    //     if (is_wp_error($attachment_id)) {
    //         wp_delete_file($file['tmp_name']);  // Clean up the temporary file if there's an error
    //         return $attachment_id;
    //     }

    //     return $attachment_id;
    // }

    // Function to handle post creation
    public function create_post(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $res = $this->check_credentials($params['username'], $params['password']);
        if ($res !== true) {
            return $res; // error
        }

        // Prepare the post data
        if ($params['use_image']) {
            $htmlImage = '<p><img src="'.$params['image_url'].'" style="width: 100%; height: auto;" /></p>';
        } else {
            $htmlImage = '';
        }
        $moreSection = '<!--more-->';
        $post_data = array(
            'post_title'   => sanitize_text_field($params['title']),
            'post_content' => wp_kses_post($htmlImage.' '.$moreSection.' '.$params['content']),
            'post_status'  => 'publish',  // or 'draft' depending on your needs
            // 'post_author'  => get_current_user_id(),  // Assign the post to the current user
            // 'post_type'    => 'post',  // You can change this to custom post types if needed
        );

        // Publish the post
        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            return new WP_Error('post_creation_failed', 'Failed to create post', array('status' => 500));
        }

        // Attach the image to the post if an image URL is provided.
        /*if (!empty($params['image_url'])) {
            $image_id = $this->upload_image_from_url($params['image_url'], $post_id);
            if (is_wp_error($image_id)) {
                return new WP_Error('image_upload_failed', 'Image upload failed.', ['status' => 500]);
            }
            set_post_thumbnail($post_id, $image_id);
        }*/
        return rest_ensure_response(array('post_id' => $post_id, 'message' => 'Post created successfully'));
    }

    // Permission callback
    public function check_permissions() {
        // Add your permission check logic
        // return current_user_can('edit_posts');
        return true;
    }
}

$moodbit_agent_handler = new MoodbitAgent();

// Register a custom route to handle POST requests

// public function init(){
//     add_action('rest_api_init', function() {
//         register_rest_route('moodbit-wordpress/v1', '/check-connection/', array(
//             'methods' => 'POST',
//             'callback' => 'check_connection',
//             'permission_callback' => function () {
//                 // Add authentication check here if needed (JWT, basic auth, etc.)
//                 // return current_user_can('edit_posts');
//                 return true;
//             }
//         ));
    
//         register_rest_route('moodbit-wordpress/v1', '/create-post/', array(
//             'methods' => 'POST',
//             'callback' => 'create_post',
//             'permission_callback' => function () {
//                 // Add authentication check here if needed (JWT, basic auth, etc.)
//                 // return current_user_can('edit_posts');
//                 return true;
//             }
//         ));
    
//     });
// }

// // Function to verify the connection
// function check_connection(WP_REST_Request $request){
//     $params = $request->get_json_params();
//     $user = wp_authenticate($params['username'], $params['password']);
//     if (is_wp_error($user)) {
//         $error_code = $user->get_error_code();
//         return new WP_Error($error_code, $user->get_error_message($error_code), [ 'status' => 401]);
//     }
//     $site_url = get_site_url();
//     return rest_ensure_response(array('site_url' => $site_url));
// }

// // Function to check_credentials the user
// function check_credentials($username, $password) {
//     $user = wp_authenticate($username, $password);
//     if (is_wp_error($user)) {
//         $error_code = $user->get_error_code();
//         return new WP_Error($error_code, $user->get_error_message($error_code), [ 'status' => 401]);
//     }
//     return true;
// }

// // Helper function to upload an image from a URL
// function upload_image_from_url($image_url, $post_id) {
//     // Download the image
//     $tmp = download_url($image_url);
//     if (is_wp_error($tmp)) {
//         return $tmp;
//     }

//     // Set up the file array
//     $file = array(
//         'name'     => basename($image_url),
//         'tmp_name' => $tmp,
//     );

//     // Upload the file to WordPress media library
//     $attachment_id = media_handle_sideload($file, $post_id);
//     if (is_wp_error($attachment_id)) {
//         wp_delete_file($file['tmp_name']);  // Clean up the temporary file if there's an error
//         return $attachment_id;
//     }

//     return $attachment_id;
// }

// // Function to handle post creation
// function create_post(WP_REST_Request $request) {
//     $params = $request->get_json_params();
//     $res = check_credentials($params['username'], $params['password']);
//     if ($res !== true) {
//         return $res; // error
//     }

//     // Prepare the post data
//     $post_data = array(
//         'post_title'   => sanitize_text_field($params['title']),
//         'post_content' => wp_kses_post($params['content']),
//         'post_status'  => 'publish',  // or 'draft' depending on your needs
//         // 'post_author'  => get_current_user_id(),  // Assign the post to the current user
//         // 'post_type'    => 'post',  // You can change this to custom post types if needed
//     );

//     // Publish the post
//     $post_id = wp_insert_post($post_data);
//     if (is_wp_error($post_id)) {
//         return new WP_Error('post_creation_failed', 'Failed to create post', array('status' => 500));
//     }

//     // Attach the image to the post if an image URL is provided.
//     if (!empty($params['image_url'])) {
//         $image_id = upload_image_from_url($params['image_url'], $post_id);
//         if (is_wp_error($image_id)) {
//             return new WP_Error('image_upload_failed', 'Image upload failed.', ['status' => 500]);
//         }
//         set_post_thumbnail($post_id, $image_id);
//     }
//     return rest_ensure_response(array('post_id' => $post_id, 'message' => 'Post created successfully'));
// }
