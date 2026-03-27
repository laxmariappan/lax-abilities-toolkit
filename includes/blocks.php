<?php
/**
 * Gutenberg block editor support.
 *
 * Detects whether the block editor is active and converts plain text / HTML
 * content into native WordPress block markup, so posts open cleanly in the
 * block editor instead of being wrapped in a legacy "Classic" block.
 *
 * Conversion is opt-out: pass `content_format: "classic"` in any create/update
 * ability call to skip conversion entirely.
 *
 * ## Content format parameter values
 *
 * - "auto"    (default) Convert to blocks only when the block editor is active.
 * - "blocks"  Always convert, even when the Classic Editor plugin is installed.
 * - "classic" Pass content through unchanged (stored as classic HTML/text).
 *
 * @package LaxAbilitiesToolkit
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// Detection
// =============================================================================

/**
 * Returns true when the Gutenberg block editor is the active post editor.
 *
 * Returns false when the Classic Editor plugin is active (regardless of its
 * per-user or per-post-type settings).
 *
 * @since 1.2.0
 *
 * @return bool
 */
function lax_abilities_is_block_editor_active() {
	// Classic Editor plugin disables the block editor entirely.
	if ( function_exists( 'classic_editor_init' ) ) {
		return false;
	}
	// register_block_type() was introduced in WP 5.0 alongside the block editor.
	return function_exists( 'register_block_type' );
}

// =============================================================================
// Entry point
// =============================================================================

/**
 * Converts plain text or HTML content into native Gutenberg block markup.
 *
 * Dispatches to the appropriate converter based on detected content type:
 *  1. Content already containing `<!-- wp:` markers → returned as-is.
 *  2. Pure plain text (no HTML tags) → paragraph blocks.
 *  3. HTML content → per-element block mapping via DOMDocument.
 *
 * @since 1.2.0
 *
 * @param string $content Raw content from the MCP caller.
 * @return string         Content in Gutenberg block markup format.
 */
function lax_abilities_content_to_blocks( $content ) {
	$content = trim( (string) $content );

	if ( '' === $content ) {
		return $content;
	}

	// Already Gutenberg block markup — pass through untouched.
	if ( false !== strpos( $content, '<!-- wp:' ) ) {
		return $content;
	}

	// Pure plain text (no HTML tags at all).
	if ( wp_strip_all_tags( $content ) === $content ) {
		return lax_abilities_plain_text_to_blocks( $content );
	}

	return lax_abilities_html_to_blocks( $content );
}

// =============================================================================
// Plain-text converter
// =============================================================================

/**
 * Wraps plain-text paragraphs in `<!-- wp:paragraph -->` blocks.
 *
 * Double line-breaks are treated as paragraph separators; single line-breaks
 * within a paragraph are preserved as `<br>`.
 *
 * @since 1.2.0
 *
 * @param string $content Plain text without any HTML tags.
 * @return string         Block markup.
 */
function lax_abilities_plain_text_to_blocks( $content ) {
	$paragraphs = array_filter( array_map( 'trim', preg_split( '/\n{2,}/', $content ) ) );
	$output     = '';

	foreach ( $paragraphs as $paragraph ) {
		// Single newlines become <br> within the paragraph.
		$lines = implode(
			'<br>',
			array_map( 'esc_html', explode( "\n", $paragraph ) )
		);
		$output .= "<!-- wp:paragraph -->\n<p>{$lines}</p>\n<!-- /wp:paragraph -->\n\n";
	}

	return trim( $output );
}

// =============================================================================
// HTML converter
// =============================================================================

/**
 * Parses an HTML string and maps top-level elements to native block markup.
 *
 * Mapping:
 *  <p>           → wp:paragraph
 *  <h1>–<h6>    → wp:heading  (level attribute added for non-h2)
 *  <ul>          → wp:list
 *  <ol>          → wp:list {"ordered":true}
 *  <li>          → wp:list-item
 *  <blockquote>  → wp:quote
 *  <pre>         → wp:code
 *  <hr>          → wp:separator
 *  everything else → wp:html
 *
 * @since 1.2.0
 *
 * @param string $html HTML content string.
 * @return string      Block markup.
 */
function lax_abilities_html_to_blocks( $html ) {
	$doc = new DOMDocument( '1.0', 'UTF-8' );
	libxml_use_internal_errors( true );
	// Wrap in full HTML envelope to ensure consistent parsing and UTF-8 handling.
	$doc->loadHTML(
		'<!DOCTYPE html><html><head><meta charset="UTF-8"/></head><body>' . $html . '</body></html>'
	);
	libxml_clear_errors();

	$body = $doc->getElementsByTagName( 'body' )->item( 0 );
	if ( ! $body ) {
		// Fallback — wrap everything in an HTML block.
		return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->";
	}

	$output = '';
	foreach ( $body->childNodes as $node ) {
		$output .= lax_abilities_dom_node_to_block( $node, $doc );
	}

	return trim( $output );
}

/**
 * Converts a single DOMNode to the corresponding Gutenberg block markup string.
 *
 * @since 1.2.0
 *
 * @param DOMNode     $node The node to convert.
 * @param DOMDocument $doc  Owning document (used for inner HTML extraction).
 * @return string           Block markup fragment, empty string for whitespace nodes.
 */
function lax_abilities_dom_node_to_block( DOMNode $node, DOMDocument $doc ) {
	// Text nodes: only emit non-whitespace text as a paragraph.
	if ( XML_TEXT_NODE === $node->nodeType ) {
		$text = trim( $node->nodeValue );
		if ( '' === $text ) {
			return '';
		}
		return "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->\n\n";
	}

	if ( XML_ELEMENT_NODE !== $node->nodeType ) {
		return '';
	}

	$tag   = strtolower( $node->nodeName );
	$inner = lax_abilities_dom_inner_html( $node, $doc );

	switch ( $tag ) {

		case 'p':
			if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
				return ''; // Skip empty paragraphs.
			}
			return "<!-- wp:paragraph -->\n<p>{$inner}</p>\n<!-- /wp:paragraph -->\n\n";

		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			$level      = (int) substr( $tag, 1 );
			// wp:heading defaults to level 2 — only add the attribute for other levels.
			$level_attr = 2 !== $level ? ' {"level":' . $level . '}' : '';
			return "<!-- wp:heading{$level_attr} -->\n<{$tag} class=\"wp-block-heading\">{$inner}</{$tag}>\n<!-- /wp:heading -->\n\n";

		case 'ul':
			$items = lax_abilities_dom_list_items( $node, $doc );
			return "<!-- wp:list -->\n<ul class=\"wp-block-list\">{$items}</ul>\n<!-- /wp:list -->\n\n";

		case 'ol':
			$items = lax_abilities_dom_list_items( $node, $doc );
			return "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">{$items}</ol>\n<!-- /wp:list -->\n\n";

		case 'blockquote':
			return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>{$inner}</p></blockquote>\n<!-- /wp:quote -->\n\n";

		case 'pre':
			// Strip wrapping <code> tag — wp:code provides its own.
			$code = trim( preg_replace( '/^<code[^>]*>|<\/code>$/i', '', $inner ) );
			return "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>{$code}</code></pre>\n<!-- /wp:code -->\n\n";

		case 'hr':
			return "<!-- wp:separator /-->\n\n";

		case 'img':
			$src = $node->getAttribute( 'src' );
			$alt = $node->getAttribute( 'alt' );
			if ( empty( $src ) ) {
				return '';
			}
			return "<!-- wp:image -->\n"
				. "<figure class=\"wp-block-image\">"
				. "<img src=\"" . esc_attr( $src ) . "\" alt=\"" . esc_attr( $alt ) . "\"/>"
				. "</figure>\n<!-- /wp:image -->\n\n";

		case 'figure':
			// If it wraps an <img>, convert to wp:image; otherwise fall to wp:html.
			$imgs = $node->getElementsByTagName( 'img' );
			if ( $imgs->length > 0 ) {
				$img        = $imgs->item( 0 );
				$src        = $img->getAttribute( 'src' );
				$alt        = $img->getAttribute( 'alt' );
				$figcaption = '';
				foreach ( $node->childNodes as $child ) {
					if ( XML_ELEMENT_NODE === $child->nodeType && 'figcaption' === strtolower( $child->nodeName ) ) {
						$figcaption = '<figcaption class="wp-element-caption">'
							. lax_abilities_dom_inner_html( $child, $doc )
							. '</figcaption>';
						break;
					}
				}
				return "<!-- wp:image -->\n"
					. "<figure class=\"wp-block-image\">"
					. "<img src=\"" . esc_attr( $src ) . "\" alt=\"" . esc_attr( $alt ) . "\"/>"
					. $figcaption
					. "</figure>\n<!-- /wp:image -->\n\n";
			}
			return "<!-- wp:html -->\n" . $doc->saveHTML( $node ) . "\n<!-- /wp:html -->\n\n";

		case 'br':
		case 'script':
		case 'style':
			return ''; // Discard script/style and bare br.

		default:
			// Tables, iframes, and other unknown elements go into an HTML block.
			return "<!-- wp:html -->\n" . $doc->saveHTML( $node ) . "\n<!-- /wp:html -->\n\n";
	}
}

/**
 * Extracts inner HTML of a DOM node without the node's own opening/closing tags.
 *
 * @since 1.2.0
 *
 * @param DOMNode     $node Parent node.
 * @param DOMDocument $doc  Owning document.
 * @return string           Concatenated HTML of all child nodes.
 */
function lax_abilities_dom_inner_html( DOMNode $node, DOMDocument $doc ) {
	$html = '';
	foreach ( $node->childNodes as $child ) {
		$html .= $doc->saveHTML( $child );
	}
	return $html;
}

/**
 * Renders `<li>` children of a list element as `wp:list-item` blocks.
 *
 * @since 1.2.0
 *
 * @param DOMNode     $list_node The <ul> or <ol> node.
 * @param DOMDocument $doc       Owning document.
 * @return string                Inner markup for the wp:list block.
 */
function lax_abilities_dom_list_items( DOMNode $list_node, DOMDocument $doc ) {
	$output = '';
	foreach ( $list_node->childNodes as $child ) {
		if ( XML_ELEMENT_NODE !== $child->nodeType ) {
			continue;
		}
		if ( 'li' !== strtolower( $child->nodeName ) ) {
			continue;
		}
		$inner   = lax_abilities_dom_inner_html( $child, $doc );
		$output .= "<!-- wp:list-item --><li>{$inner}</li><!-- /wp:list-item -->";
	}
	return $output;
}
