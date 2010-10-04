<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/* 
 * image helper
 *
 * pre-library: html helper
 *
 */

if ( ! function_exists('isValidURL')) {
	function isValidURL($url) {
		return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
	}
}

if ( ! function_exists('image_thumb')) {

	/**
	 * Automatically resizes an image and returns formatted IMG tag
	 *
	 * example:
	 * image_thumb("http://i.ytimg.com/vi/the0KZLEacs/hqdefault.jpg", 90, 120)
	 * image_thumb("http://i.ytimg.com/vi/the0KZLEacs/hqdefault.jpg", 180, 240)
	 * image_thumb("hqdefault.jpg", 90, 120)
	 *
	 * @param string $path Path to the image file, relative to the images directory or an URL
	 * @param integer $width Image of returned image
	 * @param integer $height Height of returned image
	 * @param boolean $aspect Maintain aspect ratio (default: true)
	 * @param array    $htmlAttributes Array of HTML attributes.
	 * @param boolean $return Wheter this method should return a value or output it. This overrides AUTO_OUTPUT.
	 * @return mixed    Either string or echos the value, depends on AUTO_OUTPUT and $return.
	 * @access public
	 */
	function image_thumb($path, $width, $height, $aspect = true, $htmlAttributes = array(), $return = false) {

		$sourceDir = config_item('image_sourceDir');
		$cacheDir = config_item('image_cacheDir');

		$types = array(1 => "gif", "jpeg", "png", "swf", "psd", "wbmp"); // used to determine image type
		if(empty($htmlAttributes['alt'])) $htmlAttributes['alt'] = 'thumb';  // Ponemos alt default

		$fullpath = FCPATH.$sourceDir.DIRECTORY_SEPARATOR;

		/**
		 * if url, check same file exists, and copy the file to images folder and continue
		 */
		if (isValidURL($path)) {
			$url_file = $fullpath.md5($path).'.'.pathinfo($path, PATHINFO_EXTENSION);
			if (!file_exists($url_file)) {
				file_put_contents($url_file,file_get_contents($path));
			}
			$path = md5($path).'.'.pathinfo($path, PATHINFO_EXTENSION);
		}

		$url = $fullpath.$path;

		if (!file_exists($url) || is_dir($url)  || !($size = getimagesize($url)))
			return; // image doesn't exist

		if ($aspect) { // adjust to aspect.
			if (($size[1]/$height) > ($size[0]/$width))  // $size[0]:width, [1]:height, [2]:type
				$width = ceil(($size[0]/$size[1]) * $height);
			else
				$height = ceil($width / ($size[0]/$size[1]));
		}

		$relfile = base_url().$sourceDir.'/'.$cacheDir.'/'.$width.'x'.$height.'_'.basename($path); // relative file
		$cachefile = $fullpath.$cacheDir.DIRECTORY_SEPARATOR.$width.'x'.$height.'_'.basename($path);  // location on server

		if (file_exists($cachefile)) {
			$csize = getimagesize($cachefile);
			$cached = ($csize[0] == $width && $csize[1] == $height); // image is cached
			if (@filemtime($cachefile) < @filemtime($url)) // check if up to date
				$cached = false;
		} else {
			$cached = false;
		}

		if (!$cached) {
			$resize = ($size[0] > $width || $size[1] > $height) || ($size[0] < $width || $size[1] < $height);
		} else {
			$resize = false;
		}

		if ($resize) {
			$image = call_user_func('imagecreatefrom'.$types[$size[2]], $url);
			if (function_exists("imagecreatetruecolor") && ($temp = imagecreatetruecolor ($width, $height))) {
				imagecopyresampled ($temp, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
			} else {
				$temp = imagecreate ($width, $height);
				imagecopyresized ($temp, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
			}
			call_user_func("image".$types[$size[2]], $temp, $cachefile);
			imagedestroy ($image);
			imagedestroy ($temp);
		} elseif (!$cached) {
			copy($url, $cachefile);
		}

		$htmlAttributes['src'] = $relfile;
		$htmlAttributes['height'] = $height;
		$htmlAttributes['width'] = $width;
		return img($htmlAttributes);
	}

}
?>
