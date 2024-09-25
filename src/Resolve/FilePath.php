<?php

namespace Thomasmarinissen\ClassDependencyResolver\Resolve;

use InvalidArgumentException;

/**
 * Class FilePath
 *
 * Utility class to normalize file paths by converting them to absolute paths
 * and resolving special directory entries.
 */
class FilePath
{
    /**
     * Normalize a file path by converting it to the absolute path and resolving special directory entries.
     *
     * @param  string  $path  The input path to normalize.
     * @return string The normalized absolute path.
     *
     * @throws InvalidArgumentException If the path is empty.
     */
    public function normalizePath(string $path): string
    {
        // Validate the input path is not empty
        $this->validatePath($path);

        // Try to resolve the real path
        $realPath = $this->getRealPath($path);

        // If the real path exists, return it
        if ($realPath !== false) {
            return $realPath;
        }

        // Normalize the path and return it
        return $this->processNormalizePath($path);
    }

    /**
     * Normalize a file path without resolving symlinks.
     *
     * @param  string  $path  The input path to normalize.
     * @return string The normalized absolute path without resolving symlinks.
     *
     * @throws InvalidArgumentException If the path is empty.
     */
    public function normalizePathWithoutResolvingSymlinks(string $path): string
    {
        // Validate the input path is not empty
        $this->validatePath($path);

        // Normalize the path and return it
        return $this->processNormalizePath($path);
    }

    /**
     * Helper method to normalize the file path
     *
     * @param  string  $path  The input path to normalize.
     * @return string The normalized absolute path.
     */
    protected function processNormalizePath(string $path): string
    {
        // Expand the home directory symbol '~' if present
        $path = $this->expandHomeDirectory($path);

        // Convert Windows backslashes to directory separators
        $path = $this->convertBackslashes($path);

        // Convert relative paths to absolute paths
        $path = $this->makeAbsolute($path);

        // Remove redundant slashes and './' components
        $path = $this->removeRedundantSlashes($path);

        // Resolve any parent directory references ('..')
        $path = $this->resolveParentDirectories($path);

        // Remove trailing slashes except for the root directory and return the final normalized path
        return $this->removeTrailingSlash($path);
    }

    /**
     * Validate the input path.
     *
     * @param  string  $path  The input path to validate.
     *
     * @throws InvalidArgumentException If the path is empty.
     */
    protected function validatePath(string $path): void
    {
        // Check if the path is empty and throw an exception if true
        if (empty($path)) {
            throw new InvalidArgumentException('The provided path is empty.');
        }
    }

    /**
     * Attempt to get the real path of the input.
     *
     * @param  string  $path  The input path.
     * @return string|false The real path or false if it doesn't exist.
     */
    protected function getRealPath(string $path): string|false
    {
        // Try to resolve the real path using PHP's realpath function
        return realpath($path);
    }

    /**
     * Expand the home directory symbol '~' to the actual home path.
     *
     * @param  string  $path  The input path.
     * @return string The path with the home directory expanded.
     */
    protected function expandHomeDirectory(string $path): string
    {
        // Check if the path starts with '~' and expand it to the full home directory path
        if (str_starts_with($path, '~')) {
            $homeDir = $this->getHomeDirectory();
            // Replace '~' with the actual home directory
            $path = preg_replace('/^~/', $homeDir, $path);
        }

        // Return the expanded path
        return $path;
    }

    /**
     * Get the current user's home directory based on the OS.
     *
     * @return string The home directory path.
     */
    protected function getHomeDirectory(): string
    {
        // Return the home directory path based on the operating system
        return PHP_OS_FAMILY === 'Windows' ? getenv('USERPROFILE') : getenv('HOME');
    }

    /**
     * Convert Windows backslashes to directory separators for consistency.
     *
     * @param  string  $path  The input path.
     * @return string The path with standardized directory separators.
     */
    protected function convertBackslashes(string $path): string
    {
        // Replace backslashes with the correct directory separator for the current OS
        return str_replace('\\', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Convert a relative path to an absolute path.
     *
     * @param  string  $path  The input path.
     * @return string The absolute path.
     */
    protected function makeAbsolute(string $path): string
    {
        // If the path is relative, prepend the current working directory
        if (!str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
        }

        // Return the absolute path
        return $path;
    }

    /**
     * Remove redundant slashes and './' components from the path.
     *
     * @param  string  $path  The input path.
     * @return string The path with redundant elements removed.
     */
    protected function removeRedundantSlashes(string $path): string
    {
        // Remove './' components and replace multiple slashes with a single slash
        $path = str_replace(DIRECTORY_SEPARATOR . '.' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path);

        // Remove any redundant slashes in the path
        return preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '+#', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Resolve parent directory references ('..') in the path.
     *
     * @param  string  $path  The input path.
     * @return string The path with parent directories resolved.
     */
    protected function resolveParentDirectories(string $path): string
    {
        // Initialize an array to hold the resolved path parts
        $resolved = [];

        // Split the path into its components
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            // Skip empty parts and current directory references
            if ($part === '' || $part === '.') {
                continue;
            }

            // Handle parent directory references by removing the last part if possible
            if ($part === '..') {
                array_pop($resolved);
            } else {
                // Add the current part to the resolved path
                $resolved[] = $part;
            }
        }

        // Rebuild and return the resolved path
        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $resolved);
    }

    /**
     * Remove any trailing directory separator, except for the root directory.
     *
     * @param  string  $path  The input path.
     * @return string The path without a trailing slash.
     */
    protected function removeTrailingSlash(string $path): string
    {
        // Remove any trailing slash, except for the root directory
        return rtrim($path, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }
}
