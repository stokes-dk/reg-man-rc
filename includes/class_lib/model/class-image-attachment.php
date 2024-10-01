<?php
namespace Reg_Man_RC\Model;

/**
 * An instance of this class represents an image attachment.
 * It is used for Fixer_Station icons
 *
 * @since	v0.8.9
 *
 */
class Image_Attachment {
	private $attachment_id;
	private $title;
	private $alt_text;
	private $caption;
	private $thumbnail_image_url;
	private $thumbnail_img_element;
	private $thumbnail_figure_element;
	
	private function __construct() {
	} // function
	
	/**
	 * Create an instance of this class
	 * @param int $attachment_id
	 * @return \Reg_Man_RC\Model\Image_Attachment
	 */
	public static function create( $attachment_id ) {
		$result = new self();
		$result->attachment_id = $attachment_id;
		return $result;
	} // function
	
	public function get_attachment_id() {
		return $this->attachment_id;
	} // function
	
	private function get_title() {
		if ( ! isset( $this->title ) ) {
			$attach_id = $this->get_attachment_id();
			if ( isset( $attach_id ) ) {
				$this->title = get_the_title( $attach_id );
			} // endif
		} // endif
		return $this->title;
	} // function
	
	private function get_alt_text() {
		if ( ! isset( $this->alt_text ) ) {
			$attach_id = $this->get_attachment_id();
			if ( isset( $attach_id ) ) {
				$is_single = TRUE;
				$key = '_wp_attachment_image_alt';
				$alt = get_post_meta( $attach_id, $key, $is_single );
				$this->alt_text = ! empty( $alt ) ? $alt : $this->get_title();
			} // endif
		} // endif
		return $this->alt_text;
	} // function
	
	private function get_caption() {
		if ( ! isset( $this->caption ) ) {
			$attach_id = $this->get_attachment_id();
			if ( isset( $attach_id ) ) {
				$caption = wp_get_attachment_caption( $attach_id );
				$this->caption = ! empty( $caption ) ? $caption : $this->get_title();
			} // endif
		} // endif
		return $this->caption;
	} // function
	
	private function get_thumbnail_image_url() {
		if ( ! isset( $this->thumbnail_image_url ) ) {
			$attach_id = $this->get_attachment_id();
			if ( isset( $attach_id ) ) {
				$size = 'thumbnail';
				$url = wp_get_attachment_image_url( $attach_id, $size );
				if ( ! empty( $url ) ) {
					$this->thumbnail_image_url = $url;
				} // endif
			} // endif
		} // endif
		return $this->thumbnail_image_url;
	} // function
	
	/**
	 * Get an <img> element for this attachment
	 * @return string
	 */
	public function get_thumbnail_img_element() {
		if ( ! isset( $this->thumbnail_img_element ) ) {
			$url = $this->get_thumbnail_image_url();
			$title = $this->get_title();
			$title_attr = esc_attr( $title );
			$this->thumbnail_img_element = "<img src=\"$url\" title=\"$title_attr\" alt=\"$title_attr\">";
		} // endif
		return $this->thumbnail_img_element;
	} // function

	/**
	 * Get an <img> element for this attachment
	 * @return string
	 */
	public function get_thumbnail_figure_element( $caption = NULL ) {
		if ( ! isset( $this->thumbnail_figure_element ) ) {
			$img = $this->get_thumbnail_img_element();
			$caption = ! empty( $caption ) ? esc_html( $caption ) : esc_html( $this->get_caption() );
			$this->thumbnail_figure_element = "<figure>$img<figcaption>$caption</figcaption></figure>";
		} // endif
		return $this->thumbnail_figure_element;
	} // function
} // class