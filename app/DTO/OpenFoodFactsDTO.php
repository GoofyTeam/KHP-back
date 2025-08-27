<?php

namespace App\DTO;

class OpenFoodFactsDTO
{
    public string $barcode;

    public string $product_name;

    public float $base_quantity;

    public string $unit;

    public array $categories;

    public string $imageUrl;

    public bool $is_already_in_database = false;

    public ?int $ingredient_id = null;

    /**
     * Initialise le DTO avec les données brutes de l'API Open Food Facts.
     * Gère le cas où les données sont dans ['product'] ou à la racine.
     */
    public function __construct(array $data)
    {
        $product = $data['product'] ?? $data;

        $this->barcode = $product['code'] ?? '';

        $this->product_name = $product['product_name_fr']
            ?? $product['product_name']
            ?? '';

        if (isset($product['product_quantity'])) {
            $this->base_quantity = (float) $product['product_quantity'];
        } elseif (! empty($product['quantity'])) {

            if (preg_match('/^(\d+)/', $product['quantity'], $m)) {
                $this->base_quantity = (float) $m[1];
            } else {
                $this->base_quantity = 0;
            }
        } else {
            $this->base_quantity = 0;
        }

        $this->unit = $product['product_quantity_unit'] ?? '';

        $this->categories = [];
        if (! empty($product['categories'])) {
            $this->categories = array_map('trim', explode(',', $product['categories']));
        }

        $this->imageUrl = $product['image_front_url']
            ?? $product['image_url']
            ?? '';

        if (isset($product['is_already_in_database'])) {
            $this->is_already_in_database = (bool) $product['is_already_in_database'];
        }

        if (isset($product['ingredient_id'])) {
            $this->ingredient_id = $product['ingredient_id'];
        }
    }
}
