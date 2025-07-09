<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    /**
     * Store an image into the s3 bucket and return the path.
     * If the image already exists, it returns the path without storing it again.
     */
    public function store(UploadedFile $image, string $folder): string
    {
        // Generate file name and path
        $filename = $image->hashName();
        $path = "{$folder}/{$filename}";

        // Check if image already exists
        if ($this->exists($path)) {
            return $path;
        }

        // Store the image in the S3 bucket
        $image->storeAs($folder, $filename, 's3');

        return $path;
    }

    /**
     * Check if the image exists in the s3 bucket.
     */
    public function exists(string $path): bool
    {
        return Storage::disk('s3')->exists($path);
    }

    /**
     * Delete an image from the s3 bucket.
     * This method checks if the image exists before attempting to delete it.
     */
    public function delete(string $path): bool
    {
        if ($this->exists($path)) {
            Storage::disk('s3')->delete($path);

            return true;
        }

        return false;
    }
}
