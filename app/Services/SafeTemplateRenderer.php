<?php

namespace App\Services;

class SafeTemplateRenderer
{
    public function render(string $template, array $variables): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function (array $match) use ($variables): string {
            return e((string) ($variables[$match[1]] ?? ''));
        }, $template) ?? $template;
    }
}
