# MosaicBuilder
Create photo mosaics with ease in PHP.

<img src="https://raw.githubusercontent.com/vafakaramzadegan/MosaicBuilder/main/eiffel-tower.jpg" width="300" alt="Eiffel Tower">

## Installing using Composer
MosaicBuilder can installed using Composer:

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
Suppose there is a directory containing a number of your favorite images (
these images will be used to build the final mosaic image):

`/home/YourUserName/foo/bar/images`

You also have an specific image to be converted to a mosaic image:

`/home/YourUserName/image.jpg`

All you have to do is:

```php
<?php

    $builder = new MosaicBuilder();
    
    $builder->
        // create a list of images inside a directory based on their brightness value
        scan_dir("/home/YourUserName/foo/bar/images")
        // set the amount of detail. the greater the level you set, the smaller the mosaics become
        set_mosaic_detail_level(0.05)->
        // opacity of the original image shown as an overlay on the final image
        set_background_opacity(0.3)->
        // choose the image to create a photo mosaic from
        create_from_path("/home/YourUserName/image.jpg")->
        // output mosaic image to the Browser
        output();
```
