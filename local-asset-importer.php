<?php
/*
Plugin Name: Local Asset Importer
Plugin URI: https://github.com/krues8dr/local-asset-importer
Description: Downloads assets from a given host and replaces them with a local copy. Inspired by Image Teleporter.
Version: 1.0.0
Author: Krues8dr
Author URI: http://www.krues8dr.com/
License: GPL3
*/

// require_once(ABSPATH . 'wp-admin/includes/file.php');
// require_once(ABSPATH . 'wp-admin/includes/media.php');


/** Step 2 (from text above). */
add_action( 'admin_menu', 'lai_plugin_menu' );

/** Step 1. */
function lai_plugin_menu() {
  add_submenu_page('tools.php', 'Local Asset Importer', 'Local Asset Importer', 'manage_options', 'local-asset-importer', 'lai_runner' );
}

/** Step 3. */
function lai_runner() {
  if ( !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }
  echo '<h1>' . __('Local Asset Importer') . '</h1>';

  lai_styles();
  lai_handle_assets();
  lai_show_form();
}

function lai_handle_assets() {

  if(!isset($_GET['url']) || $_GET['url'] == '') {
    return;
  }

  $url = $_GET['url'];
  $limit = $_GET['limit'];
  $offset = 0;
  $status = 'publish';

  global $wpdb;

  $search_query = "SELECT ID, post_content FROM $wpdb->posts WHERE post_content ";
  $search_query .= "REGEXP '(src|href)=[\\'\"]https?://{$url}/[^\\'\"]+[\\'\"]' ";
  if($status) {
    $search_query .= " AND post_status='$status' ";
  }
  $search_query .= " LIMIT $offset, $limit ";

  $url = 'assets.sunlightfoundation.com';

  $results = $wpdb->get_results($search_query, OBJECT );

  $seen = array();
  foreach($results as $result) {
    print "Updating $result->ID<ul>";

    $content = $result->post_content;

    preg_match_all('/(?P<attr>src|href)=([\'"])(?P<url>https?:\/\/'. $url .'\/(.*?))\2/', $result->post_content, $matches, PREG_SET_ORDER);

    foreach($matches as $match) {

      if(!in_array($match['url'], $seen)) {
        $new_filename = lai_store_file($match['url'], $result->ID);

        print "<li>Copied {$match['url']} -> $new_filename</li>";
        $seen[] = $match['url'];
      }

      $content = lai_fix_content($match, $new_filename, $content);
    }

    wp_update_post(
      array(
        'ID' => $result->ID,
        'post_content' => $content
      )
    );

    print "</ul>";
    print "Fixed content.<br><br>";
  }
}

function lai_fix_content($match, $new_filename, $content) {
  // Use a relative url;
  $url_parts = parse_url($new_filename);
  $new_url = $url_parts['path'];
  if(isset($url_parts['query']) && $url_parts['query']) {
     $new_url .= '?' . $url_parts['query'];
  }

  return str_replace(
    $match[0],
    $match['attr'] . '="' . $new_url . '"',
    $content
  );
}

function lai_store_file($path, $post_id) {
    $content = file_get_contents($path);

    $url_parts = explode('/', $path);
    $filename = end($url_parts);

    return lai_savefile($content, $filename, $post_id);
}

/*
 * Copied directly from Image Teleporter.
 * https://github.com/internetmedicineman/image-teleporter/
 */
function lai_savefile($file, $url, $post_id) {
  $time = null;

  $uploads = wp_upload_dir($time);
  $filename = wp_unique_filename( $uploads['path'], $url);
  $savepath = $uploads['path'] . "/$filename";

  if($fp = fopen($savepath, 'w')) {
    fwrite($fp, $file);
    fclose($fp);
  }

  $wp_filetype = wp_check_filetype( $savepath );
  $type = $wp_filetype['type'];
  $title = $filename;
  $content = '';

  // Construct the attachment array
  $attachment = array(
            'post_mime_type' => $type,
            'guid' => $uploads['url'] . "/$filename",
            'post_parent' => $post_id,
            'post_title' => $title,
            'post_content' => $content
            );

  // Save the data
  $id = wp_insert_attachment($attachment, $savepath, $post_id);
  if ( !is_wp_error($id) ) {
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
  } else return '';
  return $uploads['url'] . "/$filename";
}

function lai_styles() {
  echo '<style>
.lai-form-row label {
  width: 150px;
  display: inline-block;
}
.lai-form-row input[type=text] {
}
</style>';
}

function lai_show_form() {
  $limit = 25;
  if(isset($_GET['limit'])) {
    $limit = $_GET['limit'];
  }

  // $offset = 0;
  // if(isset($_GET['offset'])) {
  //   $offset = $_GET['offset'];
  // }

  $url = '';
  if(isset($_GET['url'])) {
    $url = $_GET['url'];
  }

  echo '<form method="GET" class="lai-form">
    <div class="lai-form-row">
      <p>
        Your URL should not include http:// or https:// – just the base url.
      </p>
      <label for="url">URL</label>
      <input type="text" name="url" id="url" value="' . $url . '" placeholder="assets.example.com">
    </div>' .

    // '<div class="lai-form-row">
    //   <label for="offset">Start At</label>
    //   <input type="text" name="offset" id="offset" value="' . $offset . '">
    // </div>' .

    '<div class="lai-form-row">
      <p>
        The number of entries to process at a time.  Since the search is slow, keep this number low to avoid page timeouts.
      </p>
      <label for="limit">Number of Entries</label>
      <input type="text" name="limit" id="limit" value="' . $limit . '">
    </div>

    <input type="hidden" name="page" value="local-asset-importer">
    <button name="submit" id="submit" class="button-primary">Go</button>
  </form>';
}