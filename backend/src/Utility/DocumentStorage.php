<?php
declare(strict_types=1);

namespace App\Utility;

use Cake\Core\env;

/** Stores employee and leave documents outside the public web root. */
final class DocumentStorage
{
    private const CATEGORIES = ['employee-documents', 'leave-documents'];

    public static function directory(string $category): string
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Unknown document category.');
        }

        $directory = self::root() . DS . 'uploads' . DS . $category;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create document storage.');
        }

        return $directory;
    }

    public static function path(string $storedPath): string
    {
        $normalized = str_replace('\\', '/', trim($storedPath, '/\\'));
        if (!preg_match('#^uploads/(employee-documents|leave-documents)/[A-Za-z0-9._-]+$#D', $normalized)) {
            throw new \RuntimeException('Invalid stored document path.');
        }

        $path = self::root() . DS . str_replace('/', DS, $normalized);
        if (is_file($path)) {
            return $path;
        }

        // Keep existing local development uploads readable while new files go private.
        $legacyPath = WWW_ROOT . str_replace('/', DS, $normalized);
        return is_file($legacyPath) ? $legacyPath : $path;
    }

    private static function root(): string
    {
        $root = rtrim((string)env('LEAVEHUB_STORAGE_PATH', ROOT . DS . 'storage'), '/\\');
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalizedPublic = rtrim(str_replace('\\', '/', WWW_ROOT), '/');
        if ($normalizedRoot === $normalizedPublic || str_starts_with($normalizedRoot, $normalizedPublic . '/')) {
            throw new \RuntimeException('Document storage must be outside the public web root.');
        }

        return $root;
    }
}
