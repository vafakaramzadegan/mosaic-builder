# MosaicBuilder
Create photo mosaics with ease in PHP.

<img src="https://raw.githubusercontent.com/vafakaramzadegan/MosaicBuilder/main/eiffel-tower.jpg" width="300" alt="Eiffel Tower">

## Installing using Composer
MosaicBuilder can be installed using Composer:

`$ composer require vafakaramzadegan/mosaic-builder`

just include the autoloader after installation:

```php
<?php

require("vendor/autoload.php");

use Vafakaramzadegan\MosaicBuilder;
```

## Manual Installation
You can also use MosaicBuilder without composer:

```php
<?php

require("MosaicBuilder.php");

# you can also remove namespace in MosaicBuilder.php file
use Vafakaramzadegan\MosaicBuilder;
```

## Using MosaicBuilder
Suppose there is a directory containing a number of your favorite images (will be used to build the final mosaic image):

`/home/YourUserName/foo/bar/images`

You also have a specific image to be converted to a mosaic image:

`/home/YourUserName/image.jpg`

All you have to do is:

```php
<?php

    $builder = new MosaicBuilder();
    
    $builder->
        // create a list of images inside a directory based on their brightness value
        scan_dir("/home/YourUserName/foo/bar/images")
        // set the amount of details. the greater the level you set, the smaller the mosaics become.
        set_mosaic_detail_level(0.05)->
        // opacity of the original image shown as an overlay on the final image
        set_background_opacity(0.3)->
        // choose the image to create a photo mosaic from
        create_from_path("/home/YourUserName/image.jpg")->
        // output mosaic image to the Browser
        output();
```

Scanning a directory each time you create a mosaic can take a long time. MosaicBuilder can scale down images and cache them for future use. this greatly improves the performance.

```php
<?php

    $builder = new MosaicBuilder();
    $builder->
        // load the current cache. We're going to append new images to the cache, not overwrite it.
        load_cache()->
        // scan multiple directories
        scan_dir("/home/YourUserName/foo/bar/flowers")->
        scan_dir("/home/YourUserName/foo/bar/cars")->
        scan_dir("/home/YourUserName/foo/bar/sights")->
        scan_dir("/home/YourUserName/foo/bar/people")->
        // you must eventually update the cache
        update_cache();
```

For now, you can create mosaics from these cached images in a short amount of time:

```php
<?php

    $builder = new MosaicBuilder();
    $builder->
        load_cache()->
        create_from_path("/home/YourUserName/image.jpg")->
        output();
```

instead of sending the final image directly to the browser, you can also save it to disk or just have it returned to you for further processing:

```php
<?php
    
    // send the image to the browser
    $builder->output();
    // save to the disk
    $builder->save('/home/YourUserName/mosaic.jpg');
    // get as GD image
    $mosaic = $builder->get_as_image();
```
