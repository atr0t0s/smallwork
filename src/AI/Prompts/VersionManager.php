<?php
// src/AI/Prompts/VersionManager.php
declare(strict_types=1);
namespace Smallwork\AI\Prompts;

use RuntimeException;

class VersionManager
{
    public function __construct(private string $directory)
    {
    }

    /**
     * Returns sorted list of available version numbers for a prompt.
     *
     * @return int[]
     */
    public function versions(string $name): array
    {
        $pattern = $this->directory . "/{$name}.v*.prompt";
        $files = glob($pattern);

        if ($files === false || count($files) === 0) {
            throw new RuntimeException("Prompt \"{$name}\" not found");
        }

        $versions = [];
        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match('/^' . preg_quote($name, '/') . '\.v(\d+)\.prompt$/', $basename, $matches)) {
                $versions[] = (int) $matches[1];
            }
        }

        if (count($versions) === 0) {
            throw new RuntimeException("Prompt \"{$name}\" not found");
        }

        sort($versions);

        return $versions;
    }

    /**
     * Returns the content of the highest-versioned prompt file.
     */
    public function latest(string $name): string
    {
        $versions = $this->versions($name);
        $latest = end($versions);

        return $this->version($name, $latest);
    }

    /**
     * Returns content of a specific version.
     */
    public function version(string $name, int $version): string
    {
        $path = $this->directory . "/{$name}.v{$version}.prompt";

        if (!file_exists($path)) {
            // Check if prompt exists at all
            $pattern = $this->directory . "/{$name}.v*.prompt";
            $files = glob($pattern);

            if ($files === false || count($files) === 0) {
                throw new RuntimeException("Prompt \"{$name}\" not found");
            }

            throw new RuntimeException("Version {$version} of prompt \"{$name}\" not found");
        }

        return file_get_contents($path);
    }
}
