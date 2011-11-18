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
   * Resized image will be saved or later use. Will be updated if source image is changed.
   *
   * example:
   * image_thumb("http://i.ytimg.com/vi/the0KZLEacs/hqdefault.jpg", 90, 120)
   * image_thumb("http://i.ytimg.com/vi/the0KZLEacs/hqdefault.jpg", 180, 240)
   * image_thumb("hqdefault.jpg", 90, 120)
   * image_thumb("images/hqdefault.jpg", 90, 120)
   * image_thumb("resources/images/hqdefault.jpg", 90, 120)
   * 
   * config item:
   * image_cacheFolderName: The folder name of cache folder, default to 'cache'
   * image_externalSourceFolderName: The folder that external source file will be copied to, relative to CI webroot
   *                          image_cacheFolderName will still be created inside.
   *                          Default assets/img/external
   *
   * Important variables:
   * $path: URL or path of a file. Filename included
   * $sourcePath: Path of local system to the source image
   * $sourceUrl: URL of source image
   * $sourceDir
   * $cachePath: Path of local system to cached image
   * $cacheUrl: URL of cached image
   * $cacheDir
   *
   * @param string $path Path to the image file, relative to CI webroot or an URL
   * @param integer $width Image of returned image
   * @param integer $height Height of returned image
   * @param boolean $aspect Maintain aspect ratio (default: true)
   * @param array    $htmlAttributes Array of HTML attributes.
   * @param boolean $return Wheter this method should return a value or output it. This overrides AUTO_OUTPUT.
   * @return mixed    Either string or echos the value, depends on AUTO_OUTPUT and $return.
   * @access public
   */
	function image_thumb($path, $width, $height, $aspect = true, $htmlAttributes = array(), $return = true) {

		$cacheFolderName = config_item('image_cacheFolderName');
    $externalSourceFolderName = config_item('image_externalSourceFolderName');
    $pathinfo = pathinfo($path);
    
    if(empty($cacheFolderName))
      $cacheFolderName = 'cache'.DIRECTORY_SEPARATOR;
    else
      $cacheFolderName .= DIRECTORY_SEPARARTOR;

    if (empty($externalSourceDir)) {
      $externalSourceDir = 'Resources/external/';
    }
    $externalSourceDir = str_replace('/',DIRECTORY_SEPARATOR,$externalSourceDir);

		$types = array(1 => "gif", "jpeg", "png", "swf", "psd", "wbmp"); // used to determine image type
		if(empty($htmlAttributes['alt'])) $htmlAttributes['alt'] = 'thumb';  // Ponemos alt default

    $basepath = FCPATH;

		/**
		 * if url, check same file exists, and copy the file to images folder and continue
		 */
		if (isValidURL($path)) {
      //create folder to save original external file if not exists
      if(!is_dir($basepath.$externalSourceDir)) {
        mkdir($basepath.$externalSourceDir);
        mkdir($basepath.$externalSourceDir.$cacheFodlerName);
      } elseif (!is_dir($basepath.$externalSourceDir.$cacheFolderName)) {
        mkdir($basepath.$externalSourceDir.$cacheFolderName);
      }

      //grab the external file, save as local file
			$url_file = $basepath.$externalSourceDir.md5($path).'.'.$pathinfo['extension'];
      if (!file_exists($url_file)) {
        //supress error in case got 404
        $externalImage = @file_get_contents($path);
        if(!$externalImage) {
          //get placehold.it image
          $img = '<img src="http://placehold.it/'.$width.'x'.$height.'&text=Src+404" alt="Source image cannot be found." title="Source image cannot be found." />';
          if ($return) {
            return $img;
          } else {
            echo $img;
            return;
          }
        }
  			file_put_contents($url_file, $externalImage);
      }

      //change vars, treat external file as local file.
			$path = $externalSourceDir.md5($path).'.'.$pathinfo['extension'];
      $pathinfo = pathinfo($path);
		}

    //@var holds the path to source image, str_replace for WIN
		$sourcePath = $basepath.str_replace('/',DIRECTORY_SEPARATOR,$path);
		$sourceDir = $basepath.str_replace('/',DIRECTORY_SEPARATOR,$pathinfo['dirname']);

    if (!file_exists($sourcePath) || is_dir($sourcePath)  || !($size = getimagesize($sourcePath))) {
			// Source image doesn't exist
      $img = '<img src="http://placehold.it/'.$width.'x'.$height.'&text=Src+404" alt="Source image cannot be found." title="Source image cannot be found." />';
      if ($return) {
        return $img;
      } else {
        echo $img;
        return;
      }
    }

		if ($aspect) { // adjust to aspect.
			if (($size[1]/$height) > ($size[0]/$width))  // $size[0]:width, [1]:height, [2]:type
				$width = ceil(($size[0]/$size[1]) * $height);
			else
				$height = ceil($width / ($size[0]/$size[1]));
		}

    //url for img tag use
    $cacheUrl = base_url().$pathinfo['dirname'].DIRECTORY_SEPARATOR.$cacheFolderName.$width.'x'.$height.'_'.basename($path);
    //cache file dir, create if not existed
    $cacheDir = $sourceDir.DIRECTORY_SEPARATOR.$cacheFolderName;
    if (!is_dir($cacheDir)) {
      mkdir($cacheDir);
    }
    //full path of cache file to be created
    $cachePath = $cacheDir.$width.'x'.$height.'_'.basename($path);  // location on server

    //if cache file already exists, check if the file changed.
		if (file_exists($cachePath)) {
			$csize = getimagesize($cachePath);
			$cached = ($csize[0] == $width && $csize[1] == $height); // image is cached
			if (@filemtime($cachePath) < @filemtime($url)) // check if up to date
				$cached = false;
		} else {
			$cached = false;
		}

    //only resize if target thumbnail smaller than source
		if (!$cached) {
			$resize = ($size[0] > $width || $size[1] > $height) || ($size[0] < $width || $size[1] < $height);
		} else {
			$resize = false;
		}

		if ($resize) {
      //create source image obj to be resized
			$image = call_user_func('imagecreatefrom'.$types[$size[2]], $sourcePath);
      //create a temp image obj of thumbnail size
			if (function_exists("imagecreatetruecolor") && ($temp = imagecreatetruecolor ($width, $height))) {
				imagecopyresampled ($temp, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
			} else {
				$temp = imagecreate ($width, $height);
				imagecopyresized ($temp, $image, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
      }
      //map the temp image to target cache file
			call_user_func("image".$types[$size[2]], $temp, $cachePath);
			imagedestroy ($image);
			imagedestroy ($temp);
		} elseif (!$cached) {
			copy($url, $cachePath);
		}

		$htmlAttributes['src'] = $cacheUrl;
		$htmlAttributes['height'] = $height;
		$htmlAttributes['width'] = $width;

		if ($return) {
		  // code...
      return img($htmlAttributes);
    } else {
      echo img($htmlAttributes);
      return;
    }
	}

}
?>
