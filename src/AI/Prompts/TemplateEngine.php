<?php
// src/AI/Prompts/TemplateEngine.php
declare(strict_types=1);
namespace Smallwork\AI\Prompts;

use RuntimeException;

class TemplateEngine
{
    /**
     * Render a template string by substituting {{variable}} placeholders.
     *
     * @param string $template The template with {{variable}} placeholders
     * @param array<string, string> $vars Key-value pairs for substitution
     * @return string The rendered string
     * @throws RuntimeException If any placeholders remain after substitution
     */
    public function render(string $template, array $vars): string
    {
        $result = $template;

        foreach ($vars as $key => $value) {
            $result = str_replace('{{' . $key . '}}', (string) $value, $result);
        }

        if (preg_match('/\{\{(.+?)\}\}/', $result, $matches)) {
            throw new RuntimeException(
                "Unresolved placeholder: missing variable '{$matches[1]}' in template."
            );
        }

        return $result;
    }

    /**
     * Load a template from a file and render it.
     *
     * @param string $path Path to the template file
     * @param array<string, string> $vars Key-value pairs for substitution
     * @return string The rendered string
     * @throws RuntimeException If the file cannot be read or placeholders remain
     */
    public function renderFile(string $path, array $vars): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Template file not found or not readable: {$path}");
        }

        $template = file_get_contents($path);

        if ($template === false) {
            throw new RuntimeException("Failed to read template file: {$path}");
        }

        return $this->render($template, $vars);
    }
}
