<?php
require_once("SaveDB_Utilities.php");
parse_str(implode('&', array_slice($argv, 1)), $_GET);

$Source = array(
    "DB_Name" => $_GET["s"][0],
    "DB_User" => $_GET["s"][1],
    "DB_Pass" => $_GET["s"][2],
    "Path" =>  rtrim($_GET["s"][3], '/') . '/',
);

$Destination = array(
    "DB_Name" => $_GET["d"][0],
    "DB_User" => $_GET["d"][1],
    "DB_Pass" => $_GET["d"][2],
    "Path" => rtrim($_GET["d"][3], '/') . '/',
);

$S_DB = connect_db($Source);
$D_DB = connect_db($Destination);

/* Get source data */
// Categories and related data
$db_term_taxonomies = db_categories_term_taxonomies($S_DB);
$category_ids = db_ids($db_term_taxonomies, "term_id");
$term_taxonomy_ids = db_ids($db_term_taxonomies, "term_taxonomy_id");
$db_categories = db_categories($S_DB, $category_ids);
$term_relationships = db_categories_term_relationships($S_DB, $term_taxonomy_ids);

// Posts
$db_posts = db_posts($S_DB);
$db_post_ids = db_ids($db_posts, "ID");
$db_postmetas = db_postmetas($S_DB, $db_post_ids);

// MAX values of tables
$POST_ID = db_max($D_DB, "wp_posts", "ID") + 1;
$TERM_ID = db_max($D_DB, "wp_terms", "term_id") + 1;
$TERM_TAXONOMY_ID = db_max($D_DB, "wp_term_taxonomy", "term_taxonomy_id") + 1;

/* Generate Destination data */
// Categories
$categories = array();
foreach ($db_categories as $category) {
    $term_id = (int) $category["term_id"];
    $category["term_id"] = $TERM_ID++;
    $categories[$term_id] = $category;
}

// Term Taxonomies
$term_taxonomies = array();
foreach ($db_term_taxonomies as $tt) {
    $cat_id = (int) $tt["term_id"];
    $term_taxonomy_id = (int) $tt["term_taxonomy_id"];
    $tt["term_taxonomy_id"] = $TERM_TAXONOMY_ID++;
    $tt["term_id"] = $categories[$cat_id]["term_id"];;
    $term_taxonomies[$term_taxonomy_id] = $tt;
}

// Posts
$posts = array();
foreach ($db_posts as $post) {
    $record = array();
    $record["data"] = $post;
    $record["data"]["ID"] = (string) $POST_ID++;
    $posts[$post["ID"]] = $record;
}

foreach ($term_relationships as $relationship) {
    $prev_taxonomy_id = (int) $relationship["term_taxonomy_id"];
    $post_id = (int) $relationship["object_id"];
    unset($relationship["object_id"]);
    $relationship["term_taxonomy_id"] = $term_taxonomies[$prev_taxonomy_id]["term_taxonomy_id"];
    $posts[$post_id]["relationships"][] = $relationship;
}

foreach ($db_postmetas as $postmeta) {
    $post_id = (int) $postmeta["post_id"];
    unset($postmeta["post_id"]);
    unset($postmeta["meta_id"]);
    $posts[$post_id]["metas"][] = $postmeta;
}

/* Delete current info on destination DB */
// Categories info
$delete_term_taxonomies = db_categories_term_taxonomies($D_DB);
$delete_category_ids = db_ids($delete_term_taxonomies, "term_id");
$delete_term_taxonomy_ids = db_ids($delete_term_taxonomies, "term_taxonomy_id");

// Posts info
$delete_posts = db_posts($D_DB);
$delete_post_ids = db_ids($delete_posts, "ID");

// Thumbnails info
$delete_postmetas_thumbnail = db_postmetas_thumbnail($D_DB);
$delete_thumbnails_post_ids = db_ids($delete_postmetas_thumbnail, "meta_value");
$delete_thumbnails_posts_postmetas = db_postmetas($D_DB, $delete_thumbnails_post_ids);
$delete_thumbnail_urls = db_get_thumbnail_urls($delete_thumbnails_posts_postmetas);

/* Delete executions */
// Categories info
echo "Deletes\n";
db_delete_term_taxonomies($D_DB, $delete_term_taxonomy_ids);
db_delete_term_relationships($D_DB, $delete_term_taxonomy_ids);
db_delete_terms($D_DB, $delete_category_ids);

// Post Info
db_delete_postmetas($D_DB, $delete_post_ids);
db_delete_posts($D_DB, $delete_post_ids);

// Thumbnails info
db_delete_postmetas($D_DB, $delete_thumbnails_post_ids);
db_delete_posts($D_DB, $delete_thumbnails_post_ids);

// Delete thumbnail img files
$deleted_count = bulk_delete($Destination["Path"], $delete_thumbnail_urls);
echo "Image files deleted $deleted_count/" . count($delete_thumbnail_urls) . "\n";


/* Insert new info on destination DB */
echo "Inserts\n";
db_insert_terms($D_DB, $categories);
db_insert_term_taxonomies($D_DB, $term_taxonomies);
db_insert_posts($D_DB, $posts);

// Thumbnails
$postmetas_thumbnail = db_postmetas_thumbnail($D_DB);
$thumbnail_post_ids = db_ids($postmetas_thumbnail, "meta_value");
$db_thumbnails_posts = db_posts_by_id($S_DB, $thumbnail_post_ids);
$db_thumbnails_posts_postmetas = db_postmetas($S_DB, $thumbnail_post_ids);

// Post array structure for thumbnails
$thumbnails_posts = array();
foreach ($db_thumbnails_posts as $post) {
    $prev_post_id = $post["ID"];
    $record = array();
    $record["data"] = $post;
    $record["data"]["ID"] = (string) $POST_ID++;
    $thumbnails_posts[$prev_post_id] = $record;
}

foreach ($db_thumbnails_posts_postmetas as $postmeta) {
    $post_id = (int) $postmeta["post_id"];
    unset($postmeta["post_id"]);
    unset($postmeta["meta_id"]);
    $thumbnails_posts[$post_id]["metas"][] = $postmeta;
}
echo "Thumbnail\n";
db_insert_posts($D_DB, $thumbnails_posts);

foreach ($postmetas_thumbnail as $i => $postmeta) {
    $prev_post_id = $postmeta["meta_value"];
    $postmetas_thumbnail[$i]["meta_value"] = $thumbnails_posts[$prev_post_id]["data"]["ID"];
}
echo "Set thumbnail metas\n";
db_set_postmetas($D_DB, $postmetas_thumbnail);

// Copy thumbnail images
$thumbnail_urls = db_get_thumbnail_urls($db_thumbnails_posts_postmetas);
$copied_count = bulk_copy($Source["Path"], $Destination["Path"], $thumbnail_urls);
echo "Image files copied $copied_count/" . count($thumbnail_urls) . "\n";

// Close connections
$S_DB->close();
$D_DB->close();
