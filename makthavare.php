<?php
/*
Plugin Name: Makthavare
Plugin URI: http://www.makthavare.se/
Description: Plugg för Makthavare.se
Version: 0.1
Author: Jonatan Fried
Author URI: http://makthavare.se
License: GPL
*/



// Skapa makthavare-posttype

add_action( 'init', 'create_makthavare_post_type' );

function create_makthavare_post_type()
{
	$labels = array
	(
		'name' => __( 'Makthavare' ),
		'singular_name' => __( 'Makthavare' )
	);
			
	$args = array
	(
		'labels' => $labels,
		'public' => true,
		// Fixa ikon! 'menu_icon' => get_stylesheet_directory_uri() . '/article16.png',
		'rewrite' => false, // Bör vara true så småningom
		'capability_type' => 'post', // Verkar inte riktigt funka som det borde, man kanske måste skapa sin egen rollhantering :(
		'hierarchical' => false,
		'menu_position' => null,
		'supports' => array('title', 'editor', 'thumbnail', 'author')
	);
	
	register_post_type( 'makthavare', $args);
}


// Kolumner till listvyn för makthavare-posttype

add_filter('manage_edit-makthavare_columns', 'makthavare_columns');

function makthavare_columns($columns)
{
	$columns = array
	(
		'cb' => '<input type="checkbox" />',		
		'title' => 'Makthavare',
		'thumbnail' => 'Bild',
		'description' => 'Biografi'		
	);
	return $columns;
}

add_action("manage_posts_custom_column", "my_custom_columns");

function my_custom_columns($column)
{
    global $post;
    
	
	// Bildstorlek
	
	$width = (int) 200;
	$height = (int) 125;
			    
    if ('ID' == $column) echo $post->ID;
	elseif ('thumbnail' == $column)
	{
	
    	// thumbnail of WP 2.9
    	
    	$thumbnail_id = get_post_meta( $post->ID, '_thumbnail_id', true );
    	
    	
    	// image from gallery
    	
	   	$attachments = get_children( array('post_parent' => $post_id, 'post_type' => 'attachment', 'post_mime_type' => 'image') );
    	if ($thumbnail_id)
    		$thumb = wp_get_attachment_image( $thumbnail_id, array($width, $height), true );
    	elseif ($attachments)
    	{
    		foreach ( $attachments as $attachment_id => $attachment )
    		{
    			$thumb = wp_get_attachment_image( $attachment_id, array($width, $height), true );
    		}
    	}
		if ( isset($thumb) && $thumb )
		{
			echo $thumb;
		}
		else
		{
			echo __('None');
		}
    }
    elseif ('description' == $column) echo $post->post_content;
}


// Kolla efter makthavare i inlägg

add_filter('content_edit_pre', 'check_for_makthavare');

function check_for_makthavare()
{
	global $post;
	preg_match_all('#\[{2}([\w åäöÅÄÖ]*)\|?([\w åäöÅÄÖ]*)?\]\]#', $post->post_content, $matches);
	foreach($matches[1] as $makthavare)
	{
		$p = get_posts("name=$makthavare&post_type=makthavare");
		if($p[0]->ID)
		{
			echo "<p><b>$makthavare</b> finns och har ID <b>".$p[0]->ID."</b></p>";
		}
	}
	return $post->post_content;
}


// Skapa makthavare

add_action('save_post', 'create_makthavare');

function create_makthavare()
{
	global $post;
	preg_match_all('#\[{2}([\w åäöÅÄÖ]*)\|?([\w åäöÅÄÖ]*)?\]\]#', $post->post_content, $matches);
	foreach($matches[1] as $makthavare)
	{
		$p = get_posts("name=$makthavare&post_type=makthavare");


		// Uppdatera eller skapa
		
		if($p[0]->ID != '')
		{
			#wp_update_post($p[0]->ID, $mypost);
		}
		else
		{
		
		
			// Dummydata från wikin

			$wiki = array
			(
				'image' => 'http://www.makthavare.se/w/images/4/4f/Sahlin_mona.jpg',
				'bio' => 'Sahlin växte upp på ett flyttlass mellan pappa Hans Anderssons jobb på olika ungdomsvårdshem.'
			);
			
			$mypost = array
	    	(
	    		'post_title' => $makthavare,
	    		'post_name' => strtolower(str_replace(' ', '-', $makthavare)),
	    		'post_type' => 'makthavare',
	    		'post_status' => 'publish',
	    		'post_content' => $wiki['bio']
	    	);
	    	
			$p_id = wp_insert_post($mypost);
			
			
			// Haffa bilden från wiki-arrayen och kopiera till /uploads

			$dir = wp_upload_dir();
			mkdir($dir['basedir'] . '/makthavare', 0777); # Säkerhet??
			$content = file_get_contents($wiki['image']);
			$filename = basename($wiki['image']);
			$destination = $dir['basedir'] . '/makthavare/' . $filename;
			$fp = fopen($destination, 'w');
			fwrite($fp, $content);
			fclose($fp);


			// Gör en attachment av bilden
			
			$wp_filetype = wp_check_filetype(basename($filename), null );
			$attachment = array
			(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
				'post_content' => '',
				'post_status' => 'inherit'
			);
			
			
			// Och attacha till posten vi just skapat
			
			$attach_id = wp_insert_attachment( $attachment, $destination, $p_id ); 
			
			
			// you must first include the image.php file for the function wp_generate_attachment_metadata() to work
			
			require_once(ABSPATH . "wp-admin" . '/includes/image.php');
			$attach_data = wp_generate_attachment_metadata( $attach_id, $destination );
			wp_update_attachment_metadata( $attach_id,  $attach_data );

			
			update_post_meta($pid, '_thumbnail_id', $attach_id, TRUE);
			return $pid;
		}
	}
	return $post->post_content;
}
?>
