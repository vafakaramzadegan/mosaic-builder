<?php

namespace Vafakaramzadegan;

/**
 * MosaicBuilder
 * -----------------------------
 * Create photo mosaics with ease.
 * 
 * @author: Vafa Karamzadegan
 */

class MosaicBuilder {
    use ImageOperations;

    // size of mosaics relative to size of the source image
    private $mosaic_detail = 0.05;
    // path to the source image
    private $source_img_path;
    /**
     * scaled down/pixelated copy of the source image
     */ 
    private $source_img;
    private $source_width;
    private $source_height;
    /** 
     * it's possible to add the source image as an overlay to
     * the final mosaic image when the opacity is set to a number
     * greater than zero.
     */
    private $source_image_opacity = 50;
    // final image
    private $final_img;
    private $final_width;
    private $final_height;
    /**
     * after scanning a specific directory, a thumbnail of each image
     * is created and cached for faster operations in future.
     */
    private $thumbnail_width = 100;
    // holds the list of thumbnails with their brightness value
    private $thumbnail_images = [];
    
    function __construct(){
        $this->cache_path      = dirname(__FILE__) . '/cache';
        $this->cache_fn        = $this->cache_path . '/cache.json';
        $this->thumbnails_path = $this->cache_path . '/thumbnails';

        if (!file_exists($this->cache_path))
            mkdir($this->cache_path);
        
        if (!file_exists($this->thumbnails_path))
            mkdir($this->thumbnails_path);
    }

    private function render(){
        if (count($this->thumbnail_images) < 1)
            throw new \Exception('No thumbnails found! scan some directories first.', 1);
        $mosaic_width = floor($this->final_width / $this->source_width);
        $mosaic_height = floor($this->final_height / $this->source_height);
        for ($i=0; $i<$this->source_width; $i++){
            for ($j=0; $j<$this->source_height; $j++){
                // get the brightness of current pixel
                $pixel_lum = $this->get_pixel_brightness($this->source_img, $i, $j);
                /**
                 * find a thumbnail with the closest brightness value to
                 * current pixel.
                 * implementing a different search algorithm like Binary Search
                 * may result in better performance.
                 */
                $best = INF;
                $best_ind = -1;
                foreach ($this->thumbnail_images as $key => $imgs) {
                    if (abs($key - $pixel_lum) < $best){
                        $best = abs($key - $pixel_lum);
                        $best_ind = $key;
                    }
                    if ($key > $pixel_lum)
                        break;
                }
                if ($best_ind < 0)
                    continue;
                try {
                    /**
                     * each brightness value may contain multiple thumbnails,
                     * therefore we select a random image.
                     */
                    $index = rand(0, count($this->thumbnail_images[$best_ind])-1);
                    $path = $this->thumbnails_path . '/' . $this->thumbnail_images[$best_ind][$index];
                    $m_img = imagecreatefromjpeg($path);
                    imagecopyresampled(
                        $this->final_img, $m_img,
                        $i * $mosaic_width, $j * $mosaic_height,
                        0, 0,
                        $mosaic_width, $mosaic_height,
                        imagesx($m_img), imagesy($m_img)
                    );
                    imagedestroy($m_img);
                } catch (Exception $th) {
                    // just continue
                }
            }
        }
        if ($this->source_image_opacity > 0){
            $img = imagecreatefromjpeg($this->source_img_path);
            imagecopymerge(
                $this->final_img, $img,
                0, 0, 0, 0,
                $this->final_width, $this->final_height,
                $this->source_image_opacity
            );
            imagedestroy($img);
        }

    }

    public function set_mosaic_detail_level($scale=0.05){
        $this->mosaic_detail = $scale;

        return $this;
    }

    public function set_background_opacity($val=0.5){
        if ($val < 0 || $val > 1)
            throw new \Exception('Value for opacity must be between 0 and 1!', 1);
        $this->source_image_opacity = $val * 100;

        return $this;
    }

    public function create_from_path($path){
        // Load source image
        $source = imagecreatefromjpeg($path);
        $oldw = imagesx($source);
        $oldh = imagesy($source);
        $w = floor($oldw * $this->mosaic_detail);
        $h = floor($oldh * $this->mosaic_detail);
        $img = imagecreatetruecolor($w, $h);
        imagecopyresampled($img, $source, 0, 0, 0, 0, $w, $h, $oldw, $oldh);
        imagedestroy($img);

        $this->source_img = $img;
        $this->source_img_path = $path;
        $this->source_width = $w;
        $this->source_height = $h;

        $this->final_width = $oldw;
        $this->final_height = $oldh;
        $this->final_img = imagecreatetruecolor($oldw, $oldh);

        $this->render();
        return $this;
    }

    public function scan_dir($dir){
        /* new images should be added to current
           cached images after being processed */
        $this->load_cache();
        // look for jpeg images in the directory
        $images = glob("$dir/*.{JPG,jpg,jpeg}", GLOB_BRACE);
        foreach ($images as $key=>$img) {
            try{
                // check if image format is valid
                if (exif_imagetype($img) === false)
                    continue;
                $thumbnail = imagecreatefromjpeg($img);
                if (!$thumbnail)
                    continue;
                // create a thumbnail of the image to cache
                $thumbnail = $this->resize_image(
                    $thumbnail,
                    $this->thumbnail_width
                );
                /* brightness is a floating point value and cannot be used
                   as an array key. therefore, it should be converted to string */
                $brightness = strval($this->get_average_brightness($thumbnail));
                // each brightness value may contain several images
                if (!array_key_exists($brightness, $this->thumbnail_images))
                    $this->thumbnail_images[$brightness] = [];
                // generate a unique filename for the thumbnail
                $hash = md5($img.time());
                array_push($this->thumbnail_images[$brightness], $hash);
                imagejpeg($thumbnail, $this->thumbnails_path . '/' . $hash);
                
                imagedestroy($thumbnail);
            } catch (Exception $e) { }
        }

        return $this;
    }

    public function load_cache(){
        if (file_exists($this->cache_fn))
            $this->thumbnail_images = json_decode(file_get_contents($this->cache_fn), true);
        // check if images loaded from cache properly
        if (!is_array($this->thumbnail_images))
            throw new \Exception('Cache is corrupt and unusable!', 1);
    
        return $this;
    }

    public function update_cache(){
        /* sort array by brightness values.
           for every pixel, a thumbnail with the closest brightness value
           should be selected from the cache.
           having a sorted list can increases searching speed. */
        ksort($this->thumbnail_images);
        // save the index
        file_put_contents(
            $this->cache_fn,
            json_encode($this->thumbnail_images)
        );

        return $this;
    }

    public function clear_cache(){
        // delete index file
        if (file_exists($this->cache_fn))
            unlink($this->cache_fn);
        // delete all cached thumbnails
        $thumbnails = glob($this->thumbnails_path . '/*');
        foreach ($thumbnails as $key=>$t){
            unlink($t);
        }
        
        return $this;
    }

    public function get_cache_info(){
        $arr = [];
        $dt = '';
        if (file_exists($this->cache_fn)){
            $arr = json_decode(file_get_contents($this->cache_fn), true);
            $dt = filemtime($this->cache_fn);
        }

        return [
            'count' => count($arr),
            'last_modified' => $dt
        ];
    }

    public function add_filter($filter='', $arg1=0, $arg2=0, $arg3=0, $arg4=0){
        if (!$this->final_img)
            throw new \Exception('Mosaic image has to be created before adding filter!', 1);
        switch ($filter) {
            case 'greyscale':
                imagefilter($this->final_img, IMG_FILTER_GRAYSCALE);
                break;
            case 'brightness':
                imagefilter($this->final_img, IMG_FILTER_BRIGHTNESS, $arg1);
                break;
            case 'contrast':
                imagefilter($this->final_img, IMG_FILTER_CONTRAST, $arg1);
                break;
            case 'colorize':
                imagefilter($this->final_img, IMG_FILTER_COLORIZE, $arg1, $arg2, $arg3, $arg4);
                break;
            case 'gaussian_blur':
                imagefilter($this->final_img, IMG_FILTER_GAUSSIAN_BLUR);
                break;
            case 'selective_blur':
                imagefilter($this->final_img, IMG_FILTER_SELECTIVE_BLUR);
                break;
            case 'mean_removal':
                imagefilter($this->final_img, IMG_FILTER_MEAN_REMOVAL);
                break;
            case 'smooth':
                imagefilter($this->final_img, IMG_FILTER_SMOOTH, $arg1);
                break;
            default:
                throw new \Exception('Unknown filter name!', 1);
                break;
        }

        return $this;
    }

    public function output(){
        /* sned the generated image to the browser */
        header('Content-Type: image/jpeg');
        imagejpeg($this->final_img);

        return $this;
    }

    public function save($path=''){
       imagejpeg($this->final_img, $path);

       return $this;
    }

    public function get_as_image(){
        return $this->final_img;
    }
}

trait ImageOperations{
    /**
     * Calculates brightness/luminance of a pixel
     */
    private function get_pixel_brightness($img, $x, $y){
        $rgb = imagecolorat($img, $x, $y);
        //https://www.php.net/manual/en/function.imagecolorat.php
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        // return the average
        return ($r+$r+$b+$g+$g+$g)/6;
    }

    /**
     * Calculates average brightness/luminance of an image.
     * the greater the number of samples, the more accurate
     * brightness value you will get.
     */
    private function get_average_brightness($img, $num_samples=30) {    
        $width = imagesx($img);
        $height = imagesy($img);
        $x_step = intval($width/$num_samples);
        $y_step = intval($height/$num_samples);
        $total_lum = 0;
        $sample_no = 1;
        for ($x=0; $x<$width; $x+=$x_step) {
            for ($y=0; $y<$height; $y+=$y_step) {
                $total_lum += $this->get_pixel_brightness($img, $x, $y);
                $sample_no++;
            }
        }
        $avg_lum = $total_lum / $sample_no;

        return ($avg_lum / 255) * 100;
    }

    /**
     * Resizes an image keeping the aspect ratio.
     */
    private function resize_image($image, $new_width)
    {
        $old_width = imagesx($image);
        $old_height = imagesy($image);
        // calculate new height based on new width.
        $new_height = floor($old_height * ($new_width / $old_width));
        $temp = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled(
            $temp, $image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $old_width, $old_height
        );

        return $temp;
    }
}
