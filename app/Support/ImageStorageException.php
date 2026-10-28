<?php

namespace App\Support;

use RuntimeException;

/**
 * UploadedImage::resizeAndStore() la lanza cuando ni la versión optimizada ni el respaldo
 * sin procesar lograron escribirse en el disco (p. ej. S3 realmente caído).
 */
class ImageStorageException extends RuntimeException {}
