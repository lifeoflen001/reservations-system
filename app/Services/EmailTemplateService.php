<?php

namespace App\Services;

use App\Models\EmailTemplate;

class EmailTemplateService
{
    public function __construct(private readonly SafeTemplateRenderer $renderer) {}

    public function render(string $key, array $variables): array
    {
        $template = EmailTemplate::query()->where('key', $key)->where('is_enabled', true)->first();
        if (! $template) {
            return ['subject' => '', 'body' => ''];
        }

        return ['subject' => $this->renderer->render($template->subject, $variables), 'body' => $this->renderer->render($template->body, $variables)];
    }
}
