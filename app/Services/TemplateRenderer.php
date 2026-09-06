<?php

namespace App\Services;

class TemplateRenderer
{
    /**
     * Replace placeholders with actual data.
     * Supported syntaxes: {{client_name}}, {client_name}, $client_name.
     */
    public static function render(string $templateContent, array $data): string
    {
        $search = [];
        $replace = [];

        foreach ($data as $key => $value) {
            $escapedValue = e($value);

            $search[] = '{{' . $key . '}}';
            $replace[] = $escapedValue;

            $search[] = '{' . $key . '}';
            $replace[] = $escapedValue;

            $search[] = '$' . $key;
            $replace[] = $escapedValue;
        }

        // Replace all placeholders with real values
        return str_replace($search, $replace, $templateContent);
    }
}
