<?php

namespace App\Support;

class Boletas
{
    /**
     * Normalize boleta number by converting to uppercase and removing non-alphanumeric characters
     * 
     * @param string $boletaNumber
     * @return string
     */
    public static function normalize(string $boletaNumber): string
    {
        // Remove all non-alphanumeric characters and convert to uppercase
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($boletaNumber)));
    }

    /**
     * Normalize bank name by converting to uppercase and trimming whitespace
     * 
     * @param string $bankName
     * @return string
     */
    public static function normalizeBank(string $bankName): string
    {
        return strtoupper(trim($bankName));
    }

    /**
     * Calculate file SHA256 hash
     * 
     * @param string $fileContent
     * @return string
     */
    public static function calculateFileHash(string $fileContent): string
    {
        return hash('sha256', $fileContent);
    }

    /**
     * Check if two boletas are similar (soft duplicate detection)
     * 
     * @param string $boleta1
     * @param string $boleta2
     * @return bool
     */
    public static function areSimilar(string $boleta1, string $boleta2): bool
    {
        $normalized1 = self::normalize($boleta1);
        $normalized2 = self::normalize($boleta2);
        
        // Consider similar if normalized versions are the same
        return $normalized1 === $normalized2;
    }
}