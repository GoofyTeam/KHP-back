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
        $this->assertPublicUrl($url);

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
     * Vérifie que l'URL ne pointe pas vers une adresse interne.
     * TODO: envisager une liste blanche de domaines ou l'utilisation d'un proxy dédié.
     */
    private function assertPublicUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host || in_array(strtolower($host), ['localhost'])) {
            throw ValidationException::withMessages([
                'image_url' => 'Domaine ou URL non autorisé.',
            ]);
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
            foreach ($records as $rec) {
                if (isset($rec['ip'])) {
                    $ips[] = $rec['ip'];
                }
                if (isset($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                throw ValidationException::withMessages([
                    'image_url' => 'Domaine ou URL non autorisé.',
                ]);
            }
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
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
