<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

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
        $userAgent = config('services.image_downloader.user_agent')
            ?? config('app.name', 'Laravel').' image seeder';

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'image/*,application/octet-stream;q=0.9,*/*;q=0.1',
                ])
                ->get($url);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'image_url' => 'Impossible de télécharger l’image (réseau/URL invalide).',
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'image_url' => 'Téléchargement échoué (statut HTTP '.$response->status().').',
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
        $filename = hash('sha256', $contents).'.'.$ext;
        $path = "{$folder}/{$filename}";

        // 6) Éviter les doublons
        if ($this->exists($path)) {
            return $path;
        }

        // 7) Stocker dans S3
        Storage::disk('s3')->put($path, $contents);

        return $path;
    }

    /**
     * S'assure qu'une image locale (par défaut le placeholder Laravel) est
     * présente sur S3 et renvoie son chemin.
     */
    public function storePlaceholder(string $path = 'private/images/placeholder.svg'): string
    {
        if ($this->exists($path)) {
            return $path;
        }

        $absolutePath = storage_path('app/'.$path);

        return $this->storeLocalImage($absolutePath, $path);
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

    /**
     * Publie une image locale (depuis storage/app) vers S3 avec un chemin déterministe.
     */
    public function storeLocalImage(string $absolutePath, string $destinationPath): string
    {
        if ($this->exists($destinationPath)) {
            return $destinationPath;
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException('Impossible de lire le fichier image local : '.$absolutePath);
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            throw new RuntimeException('Impossible de lire le fichier image local : '.$absolutePath);
        }

        try {
            Storage::disk('s3')->put($destinationPath, $contents);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Impossible de stocker l\'image locale sur S3 : '.$destinationPath,
                previous: $exception,
            );
        }

        return $destinationPath;
    }
}
