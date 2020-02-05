<?
function connect_db($credentials)
{
    $mysqli = new mysqli("localhost", $credentials["DB_User"], $credentials["DB_Pass"], $credentials["DB_Name"]);

    // Check connection
    if ($mysqli->connect_errno) {
        echo "Failed to connect to MySQL: " . $mysqli->connect_error;
        throw new Exception($mysqli->connect_error);
    }
    return $mysqli;
}

function db_query($conn, $query)
{
    $rows = array();
    $result = $conn->query($query);
    if (!$result) {
        echo "Failed to execute query: " . $conn->error;
        throw new Exception($conn->error);
    }
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free_result();
    return $rows;
}


function db_row($conn, $query)
{
    $result = db_query($conn, $query);
    if (count($result) >= 1)
        return $result[0];
    return null;
}

function db_exec($conn, $query)
{
    $result = $conn->query($query);
    if ($result !== TRUE) {
        echo "Failed to execute query" . $conn->error;
        throw new Exception($conn->error);
    }
    return true;
}

function db_prepared_exec($conn, $query, $params)
{
    $stmt = $conn->prepare($query);
    $stmt->bind_param($params["types"], ...$params["values"]);
    $result = $stmt->execute();
    if ($result !== TRUE) {
        echo "Failed to execute query" . $stmt->error;
        throw new Exception($stmt->error);
    }
    return $result;
}

function db_max($conn, $table, $column)
{
    $rows = db_query($conn, "select COALESCE(MAX($column), 0) as max from $table");
    return $rows[0]["max"];
}

function db_categories_term_taxonomies($conn)
{
    return db_query($conn, "select * from wp_term_taxonomy where taxonomy = 'category'");
}

function db_ids($records, $field)
{
    $ids = array_map(function ($tt) use ($field) {
        return $tt[$field];
    }, $records);
    $filtered_ids = array_filter($ids, function ($id) {
        return !is_null($id);
    });
    $filtered_ids = array_unique($filtered_ids);

    return implode(",", $filtered_ids);
}

function db_categories($conn, $category_ids)
{
    return db_query($conn, "select * from wp_terms where term_id in ($category_ids)");
}

function db_categories_term_relationships($conn, $term_taxonomy_ids)
{
    return db_query($conn, "select * from wp_term_relationships where term_taxonomy_id in ($term_taxonomy_ids)");
}

function db_posts($conn)
{
    return db_query($conn, "select * from wp_posts where post_type = 'post' and post_status != 'auto-draft'");
}

function db_posts_by_id($conn, $post_ids)
{
    if ("" === $post_ids)
        return [];
    return db_query($conn, "select * from wp_posts where ID in($post_ids)");
}

function db_postmetas($conn, $post_ids)
{
    return db_query($conn, "select * from wp_postmeta where post_id in ($post_ids)");
}

function db_postmetas_thumbnail($conn)
{
    return db_query($conn, "select pm.* from wp_postmeta pm inner join wp_posts p on p.ID = pm.post_id where meta_key = '_thumbnail_id' and post_type = 'post'");
}

function db_delete_term_taxonomies($conn, $term_taxonomy_ids)
{
    if ("" === $term_taxonomy_ids) return;
    return db_exec($conn, "delete from wp_term_taxonomy where term_taxonomy_id in ($term_taxonomy_ids)");
}

function db_delete_terms($conn, $term_ids)
{
    if ("" === $term_ids) return;
    return db_exec($conn, "delete from wp_terms where term_id in ($term_ids)");
}

function db_delete_term_relationships($conn, $term_taxonomy_ids)
{
    if ("" === $term_taxonomy_ids) return;

    return db_exec($conn, "delete from wp_term_relationships where term_taxonomy_id in ($term_taxonomy_ids)");
}

function db_delete_postmetas($conn, $post_ids)
{
    if ("" === $post_ids) return;

    return db_exec($conn, "delete from wp_postmeta where post_id in ($post_ids)");
}

function db_delete_posts($conn, $post_ids)
{
    if ("" === $post_ids) return;

    return db_exec($conn, "delete from wp_posts where ID in ($post_ids)");
}

function db_insert_terms($conn, $terms)
{
    foreach ($terms as $term) {
        $params = array(
            "types" => "issi",
            "values" => array(
                $term["term_id"],
                $term["name"],
                $term["slug"],
                $term["term_group"],
            )
        );
        $query = "insert into wp_terms (term_id, name, slug, term_group) values (?,?,?,?)";
        db_prepared_exec($conn, $query, $params);
    }
    return true;
}

function db_insert_term_taxonomies($conn, $term_taxonomies)
{
    foreach ($term_taxonomies as $tt) {
        $params = array(
            "types" => "iissii",
            "values" => array(
                $tt["term_taxonomy_id"],
                $tt["term_id"],
                $tt["taxonomy"],
                $tt["description"],
                $tt["parent"],
                $tt["count"]
            )
        );
        $query = "insert into wp_term_taxonomy (term_taxonomy_id, term_id, taxonomy, description, parent, count) values (?,?,?,?,?,?)";
        db_prepared_exec($conn, $query, $params);
    }
    return true;
}

function db_insert_posts($conn, $posts)
{
    foreach ($posts as $post) {
        $data = $post["data"];
        // Insert post data
        $params = array(
            "types" => "iisssssssssssssssisissi",
            "values" => array(
                $data["ID"],
                $data["post_author"],
                $data["post_date"],
                $data["post_date_gmt"],
                $data["post_content"],
                $data["post_title"],
                $data["post_excerpt"],
                $data["post_status"],
                $data["comment_status"],
                $data["ping_status"],
                $data["post_password"],
                $data["post_name"],
                $data["to_ping"],
                $data["pinged"],
                $data["post_modified"],
                $data["post_modified_gmt"],
                $data["post_content_filtered"],
                $data["post_parent"],
                $data["guid"],
                $data["menu_order"],
                $data["post_type"],
                $data["post_mime_type"],
                $data["comment_count"]
            )
        );
        $query = "insert into wp_posts (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count) values (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        db_prepared_exec($conn, $query, $params);

        // Insert post relationships
        if (isset($post["relationships"])) {
            foreach ($post["relationships"] as $relationship) {
                $rel_params = array(
                    "types" => "iii",
                    "values" => array(
                        $data["ID"],
                        $relationship["term_taxonomy_id"],
                        $relationship["term_order"]
                    )
                );
                $rel_query = "insert into wp_term_relationships (object_id, term_taxonomy_id, term_order) values (?,?,?)";
                db_prepared_exec($conn, $rel_query, $rel_params);
            }
        }

        // Insert postmetas
        if (isset($post["metas"])) {
            $metas_assoc = array();
            // Remove duplicated metas
            foreach ($post["metas"] as $postmeta) {
                $metas_assoc[$postmeta["meta_key"]] = $postmeta["meta_value"];
            }

            // Insert filtered metas on DB
            foreach ($metas_assoc as $meta_key => $meta_value) {
                $meta_params = array(
                    "types" => "iss",
                    "values" => array(
                        $data["ID"],
                        $meta_key,
                        $meta_value
                    )
                );
                $meta_query = "insert into wp_postmeta (post_id, meta_key, meta_value) values (?,?,?)";
                db_prepared_exec($conn, $meta_query, $meta_params);
            }
        }
    }
}

function db_set_postmetas($conn, $postmetas)
{
    foreach ($postmetas as $postmeta) {
        $postmeta_params = array(
            "types" => "ssi",
            "values" => array(
                $postmeta["meta_key"],
                $postmeta["meta_value"],
                $postmeta["meta_id"]
            )
        );
        $postmeta_query = "update wp_postmeta set meta_key=?, meta_value=? where meta_id=?";
        db_prepared_exec($conn, $postmeta_query, $postmeta_params);
    }
}

function db_get_thumbnail_urls($thumbnail_post_postmetas)
{
    $thumbnail_img_metas = array_filter($thumbnail_post_postmetas, function ($postmeta) {
        return $postmeta["meta_key"] === "_wp_attachment_metadata";
    });
    $thumbnail_unserialized_img_metas = array_map(function ($postmeta) {
        $serialized_metas = $postmeta["meta_value"];
        // Recalculation just for corrupted metas
        $recalculated_metas = preg_replace_callback('!s:(\d+):"(.*?)";!', function ($match) {
            return ($match[1] == strlen($match[2])) ? $match[0] : 's:' . strlen($match[2]) . ':"' . $match[2] . '";';
        }, $serialized_metas);

        return unserialize($recalculated_metas);
    }, $thumbnail_img_metas);

    $thumbnail_urls = array();
    foreach ($thumbnail_unserialized_img_metas as $img_meta) {
        if (isset($img_meta["file"])) {
            $path = rtrim(dirname($img_meta["file"]), '/') . '/';
            $thumbnail_urls[] = $img_meta["file"];

            foreach ($img_meta["sizes"] as $img_size_meta) {
                $thumbnail_urls[] = $path . $img_size_meta["file"];
            }
        }
    }

    return $thumbnail_urls;
}

function prepare_path($file)
{
    $dir = dirname($file);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

function copy_file($S_Path, $D_Path, $file)
{
    $source_file = $S_Path . "wp-content/uploads/$file";
    $result = false;
    if (file_exists($source_file)) {
        $destination_file = $D_Path . "wp-content/uploads/$file";
        prepare_path($destination_file);

        $result = copy($source_file, $destination_file);
    }
    if (!$result) {
        echo "Not copied: $source_file\n";
    }
    return $result;
}

function bulk_copy($S_Path, $D_Path, $files)
{
    $count = 0;
    foreach ($files as $file) {
        $copied = copy_file($S_Path, $D_Path, $file);
        $count += $copied ? 1 : 0;
    }
    return $count;
}


function delete_file($path,  $file)
{
    $filePath = $path . "wp-content/uploads/$file";
    $result = false;
    if (file_exists($filePath)) {
        $result = unlink($filePath);
    }
    if (!$result) {
        echo "Not deleted: $filePath\n";
    }

    return $result;
}

function bulk_delete($path, $files)
{
    $count = 0;
    foreach ($files as $file) {
        $deleted = delete_file($path, $file);
        $count += $deleted ? 1 : 0;
    }
    return $count;
}
