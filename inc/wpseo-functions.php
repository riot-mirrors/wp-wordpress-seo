<?php

function wpseo_get_value( $val, $postid = '' ) {
	if ( empty($postid) ) {
		global $post;
		if (isset($post))
			$postid = $post->ID;
		else 
			return false;
	}
	$custom = get_post_custom($postid);
	if (!empty($custom['_yoast_wpseo_'.$val][0]))
		return maybe_unserialize( $custom['_yoast_wpseo_'.$val][0] );
	else
		return false;
}

function wpseo_set_value( $meta, $val, $postid ) {
	update_post_meta( $postid, '_yoast_wpseo_'.$meta, $val );
}

function get_wpseo_options_arr() {
	$optarr = array('wpseo', 'wpseo_permalinks', 'wpseo_titles', 'wpseo_rss', 'wpseo_internallinks', 'wpseo_xml', 'wpseo_social');
	return apply_filters( 'wpseo_options', $optarr );
}

function get_wpseo_options() {
	$options = array();
	foreach( get_wpseo_options_arr() as $opt ) {
		$options = array_merge( $options, (array) get_option($opt) );
	}
	return $options;
}

function wpseo_replace_vars($string, $args, $omit = array() ) {
	
	$args = (array) $args;
	
	$string = strip_tags( $string );
	
	// Let's see if we can bail super early.
	if ( strpos( $string, '%%' ) === false )
		return trim( preg_replace('/\s+/u',' ', $string) );

	global $sep;
	if ( !isset( $sep ) || empty( $sep ) )
		$sep = '-';
		
	$simple_replacements = array(
		'%%sep%%'					=> $sep,
		'%%sitename%%'				=> get_bloginfo('name'),
		'%%sitedesc%%'				=> get_bloginfo('description'),
		'%%currenttime%%'			=> date('H:i'),
		'%%currentdate%%'			=> date('M jS Y'),
		'%%currentmonth%%'			=> date('F'),
		'%%currentyear%%'			=> date('Y'),
	);

	foreach ($simple_replacements as $var => $repl) {
		$string = str_replace($var, $repl, $string);
	}
	
	// Let's see if we can bail early.
	if ( strpos( $string, '%%' ) === false )
		return trim( preg_replace('/\s+/u',' ', $string) );

	global $wp_query;
	
	$defaults = array(
		'ID' => '',
		'name' => '',
		'post_author' => '',
		'post_content' => '',
		'post_date' => '',
		'post_content' => '',
		'post_excerpt' => '',
		'post_modified' => '',
		'post_title' => '',
		'taxonomy' => '',
		'term_id' => '',
	);
	
	if ( isset( $args['post_content'] ) )
		$args['post_content'] = wpseo_strip_shortcode( $args['post_content'] );
	if ( isset( $args['post_excerpt'] ) )
		$args['post_excerpt'] = wpseo_strip_shortcode( $args['post_excerpt'] );
		
	$r = (object) wp_parse_args($args, $defaults);

	// Only global $post on single's, otherwise some expressions will return wrong results.
	if ( is_singular() || ( is_front_page() && 'posts' != get_option('show_on_front') ) ) {
		global $post;
	}

	$pagenum = 0;
	$max_num_pages = 1;
	if ( !is_single() ) {
		$pagenum = get_query_var('paged');
		if ($pagenum === 0) 
			$pagenum = 1;

		if ( isset( $wp_query->max_num_pages ) && $wp_query->max_num_pages != '' && $wp_query->max_num_pages != 0 )
			$max_num_pages = $wp_query->max_num_pages;
	} else {
		$pagenum = get_query_var('page');
		$max_num_pages = substr_count( $post->post_content, '<!--nextpage-->' );
		if ( $max_num_pages >= 1 )
			$max_num_pages++;
	}
		
	// Let's do date first as it's a bit more work to get right.
	if ( $r->post_date != '' ) {
		$date = mysql2date( get_option('date_format'), $r->post_date );
	} else {
		if ( get_query_var('day') && get_query_var('day') != '' ) {
			$date = get_the_date();
		} else {
			if ( single_month_title(' ', false) && single_month_title(' ', false) != '' ) {
				$date = single_month_title(' ', false);
			} else if ( get_query_var('year') != '' ){
				$date = get_query_var('year');
			} else {
				$date = '';
			}
		}
	}
	
	$replacements = array(
		'%%date%%'					=> $date,
		'%%title%%'					=> stripslashes( $r->post_title ),
		'%%excerpt%%'				=> ( !empty($r->post_excerpt) ) ? strip_tags( $r->post_excerpt ) : substr( strip_shortcodes( strip_tags( $r->post_content ) ), 0, 155 ),
		'%%excerpt_only%%'			=> strip_tags( $r->post_excerpt ),
		'%%category%%'				=> wpseo_get_terms($r->ID, 'category'),
		'%%category_description%%'	=> !empty($r->taxonomy) ? trim(strip_tags(get_term_field( 'description', $r->term_id, $r->taxonomy ))) : '',
		'%%tag_description%%'		=> !empty($r->taxonomy) ? trim(strip_tags(get_term_field( 'description', $r->term_id, $r->taxonomy ))) : '',
		'%%term_description%%'		=> !empty($r->taxonomy) ? trim(strip_tags(get_term_field( 'description', $r->term_id, $r->taxonomy ))) : '',
		'%%term_title%%'			=> $r->name,
		'%%focuskw%%'				=> wpseo_get_value('focuskw', $r->ID),
		'%%tag%%'					=> wpseo_get_terms($r->ID, 'post_tag'),
		'%%modified%%'				=> mysql2date( get_option('date_format'), $r->post_modified ),
		'%%id%%'					=> $r->ID,
		'%%name%%'					=> get_the_author_meta('display_name', !empty($r->post_author) ? $r->post_author : get_query_var('author')),
		'%%userid%%'				=> !empty($r->post_author) ? $r->post_author : get_query_var('author'),
		'%%searchphrase%%'			=> esc_html(get_query_var('s')),
		'%%page%%'		 			=> ( $max_num_pages > 1 && $pagenum > 1 ) ? sprintf( $sep . ' ' . __('Page %d of %d','wordpress-seo'), $pagenum, $max_num_pages) : '', 
		'%%pagetotal%%'	 			=> $max_num_pages, 
		'%%pagenumber%%' 			=> $pagenum,
		'%%caption%%'				=> $r->post_excerpt,
	);
	
	foreach ($replacements as $var => $repl) {
		if ( !in_array($var, $omit) )
			$string = str_replace($var, $repl, $string);
	}
	
	if ( strpos( $string, '%%' ) === false ) {
		$string = preg_replace( '/\s+/u',' ', $string );
		return trim( $string );
	}

	if ( isset( $wp_query->query_vars['post_type'] ) && preg_match_all( '/%%pt_([^%]+)%%/u', $string, $matches, PREG_SET_ORDER ) ) {
		$pt = get_post_type_object( $wp_query->query_vars['post_type'] );
		$pt_plural = $pt_singular = $pt->name;
		if ( isset( $pt->labels->singular_name ) )
			$pt_singular = $pt->labels->singular_name;
		if ( isset( $pt->labels->name ) )
			$pt_plural = $pt->labels->name;
		$string = str_replace( '%%pt_single%%', $pt_singular, $string);
		$string = str_replace( '%%pt_plural%%', $pt_plural, $string);
	}

	if ( preg_match_all( '/%%cf_([^%]+)%%/u', $string, $matches, PREG_SET_ORDER ) ) {
		global $post;
		foreach ($matches as $match) {
			$string = str_replace( $match[0], get_post_meta( $post->ID, $match[1], true), $string );
		}
	}

	if ( preg_match_all( '/%%ct_desc_([^%]+)?%%/u', $string, $matches, PREG_SET_ORDER ) ) {
		global $post;
		foreach ($matches as $match) {
			$terms = get_the_terms( $post->ID, $match[1] );
			$string = str_replace( $match[0], get_term_field( 'description', $terms[0]->term_id, $match[1] ), $string );
		}
	}

	if ( preg_match_all( '/%%ct_([^%]+)%%(single%%)?/u', $string, $matches, PREG_SET_ORDER ) ) {
		global $post;
		foreach ($matches as $match) {
			$single = false;
			if ( isset($match[2]) && $match[2] == 'single%%' )
				$single = true;
			$ct_terms = wpseo_get_terms( $r->ID, $match[1], $single );

			$string = str_replace( $match[0], $ct_terms, $string );
		}
	}
	
	$string = preg_replace( '/\s+/u',' ', $string );
	return trim( $string );
}

function wpseo_get_terms($id, $taxonomy, $return_single = false ) {
	// If we're on a specific tag, category or taxonomy page, return that and bail.
	if ( is_category() || is_tag() || is_tax() ) {
		global $wp_query;
		$term = $wp_query->get_queried_object();
		return $term->name;
	}
	
	if ( empty($id) || empty($taxonomy) )
		return '';
		
	$output = '';
	$terms = get_the_terms($id, $taxonomy);
	if ( $terms ) {
		foreach ($terms as $term) {
			if ( $return_single )
				return $term->name;
			$output .= $term->name.', ';
		}
		return rtrim( trim($output), ',' );
	}
	return '';
}

function wpseo_get_term_meta( $term, $taxonomy, $meta ) {
	if ( is_string( $term ) ) 
		$term = get_term_by('slug', $term, $taxonomy);

	if ( is_object( $term ) )
		$term = $term->term_id;
	else
		return false;
	
	$tax_meta = get_option( 'wpseo_taxonomy_meta' );
	if ( isset($tax_meta[$taxonomy][$term]) )
		$tax_meta = $tax_meta[$taxonomy][$term];
	else
		return false;
	
	return (isset($tax_meta['wpseo_'.$meta])) ? $tax_meta['wpseo_'.$meta] : false;
}


/**
 * Strip out the shortcodes with a filthy regex, because people don't properly register their shortcodes.
 *
 * @param $text string input string that might contain shortcodes
 * @return $text string string without shortcodes
 */
function wpseo_strip_shortcode( $text ) {
	return preg_replace( '|\[(.+?)\](.+?\[/\\1\])?|s', '', $text );
}