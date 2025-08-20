<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImageService
{
    /**
     * Stocke une image uploadée dans S3 et renvoie le chemin.
     */
    public function store(UploadedFile $image, string $folder): string
    {
        // NB: hashName() n'est pas déterministe; on garde le comportement existant.
        $filename = $image->hashName();
        $path = "{$folder}/{$filename}";

        if ($this->exists($path)) {
            return $path;
        }

        $image->storeAs($folder, $filename, 's3');

        return $path;
    }

    /**
     * Télécharge une image depuis une URL, la valide et la stocke dans S3.
     * Renvoie le chemin S3.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeFromUrl(string $url, string $folder, int $maxBytes = 2_048_000): string
    {
        // 1) Récupérer le contenu distant
        try {
            $response = Http::timeout(10)->get($url);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'image_url' => 'Impossible de télécharger l’image (réseau/URL invalide).',
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'image_url' => 'Téléchargement échoué (statut HTTP invalide).',
            ]);
        }

        // 2) Valider le type MIME image/*
        $mime = $response->header('Content-Type');
        if (! is_string($mime) || ! str_starts_with($mime, 'image/')) {
            throw ValidationException::withMessages([
                'image_url' => 'Le contenu récupéré n’est pas une image valide.',
            ]);
        }

        // 3) Valider la taille (si connue) puis après téléchargement
        $lenHeader = $response->header('Content-Length');
        if (is_numeric($lenHeader) && (int) $lenHeader > $maxBytes) {
            throw ValidationException::withMessages([
                'image_url' => 'L’image distante dépasse la taille maximale autorisée de '.number_format($maxBytes / 1024, 0).' Ko.',
            ]);
        }

        $contents = $response->body();
        if (strlen($contents) > $maxBytes) {
            throw ValidationException::withMessages([
                'image_url' => 'L’image téléchargée dépasse la taille maximale autorisée de '.number_format($maxBytes / 1024, 0).' Ko.',
            ]);
        }

        // 4) Déterminer l’extension
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
            'image/heic' => 'heic',
        ];
        $ext = $extMap[$mime] ?? pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $ext = strtolower($ext);
        if (! preg_match('/^(jpg|jpeg|png|gif|webp|bmp|svg|heic)$/', $ext)) {
            $ext = 'jpg';
        }

        // 5) Créer un nom de fichier déterministe basé sur le contenu
        $filename = sha1($contents).'.'.$ext;
        $path = "{$folder}/{$filename}";

        // 6) Éviter les doublons
        if ($this->exists($path)) {
            return $path;
        }

        // 7) Stocker dans S3
        Storage::disk('s3')->put($path, $contents);

        return $path;
    }

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
