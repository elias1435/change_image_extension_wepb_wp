add_action('init', 'convert_featured_images_to_webp');

function convert_featured_images_to_webp() {
    // Only run for admin users to avoid slowing down frontend
    if (!current_user_can('manage_options')) return;

    $args = [
        'post_type' => 'post',
        'posts_per_page' => -1,
        'post_status' => 'any',
    ];

    $posts = get_posts($args);

    foreach ($posts as $post) {
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if (!$thumbnail_id) continue;

        $image_path = get_attached_file($thumbnail_id);
        $image_ext = pathinfo($image_path, PATHINFO_EXTENSION);
        
        // Only convert if not already webp
        if (strtolower($image_ext) === 'webp') continue;

        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_path);
        $webp_url = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', wp_get_attachment_url($thumbnail_id));

        // If webp file doesn't exist, generate it
        if (!file_exists($webp_path)) {
            $image = wp_get_image_editor($image_path);
            if (!is_wp_error($image)) {
                $image->save($webp_path, 'image/webp');
            } else {
                continue; // skip if error in image editing
            }
        }

        // Upload new .webp as attachment (if needed)
        $upload_dir = wp_upload_dir();
        $webp_rel_path = str_replace(trailingslashit($upload_dir['basedir']), '', $webp_path);
        $existing = attachment_url_to_postid($webp_url);

        if (!$existing) {
            $attachment = [
                'guid'           => $webp_url,
                'post_mime_type' => 'image/webp',
                'post_title'     => get_the_title($post),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ];
            $webp_id = wp_insert_attachment($attachment, $webp_path, $post->ID);
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata($webp_id, $webp_path);
            wp_update_attachment_metadata($webp_id, $attach_data);
        } else {
            $webp_id = $existing;
        }

        // Update featured image
        set_post_thumbnail($post->ID, $webp_id);
    }

    // Optional: run this only once
    remove_action('init', 'convert_featured_images_to_webp');
}
