<?php

if (!function_exists('generateTrigramme')) {
    function generateTrigramme(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        $trigramme = '';

        if (count($words) === 1) {
            $trigramme = substr($words[0], 0, 3);
        } elseif (count($words) === 2) {
            $trigramme = substr($words[0], 0, 2) . substr($words[1], 0, 1);
        } else {
            $trigramme = substr($words[0], 0, 1) . substr($words[1], 0, 1) . substr($words[2], 0, 1);
        }

        return strtolower($trigramme);
    }
}
