<?php

namespace Thomasmarinissen\ClassDependencyResolver;

use Exception;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class FileScanner
 *
 * This class is responsible for scanning directories and collecting PHP files.
 * It can exclude specified paths and handles symlinks.
 */
class FileScanner
{
    /**
     * List of PHP file paths.
     *
     * @var array|null
     */
    protected ?array $phpFiles = null;

    /**
     * The FilePath instance for path normalization.
     *
     * @var FilePath|null
     */
    protected ?FilePath $filePath = null;

    /**
     * List of scanned real paths to avoid duplicate scans.
     *
     * @var array
     */
    protected array $scannedRealPaths = [];

    /**
     * Constructor for the FileScanner class.
     *
     * @param  array  $directories  List of directories to scan.
     * @param  array  $excludePaths  List of paths to exclude from the scan.
     */
    public function __construct(protected array $directories, protected array $excludePaths = [])
    {
        //
    }

    /**
     * Retrieve all PHP files from the specified directories.
     *
     * @return array List of PHP file paths.
     */
    public function phpFiles(): array
    {
        // Check if PHP files have already been collected, return them if so
        if ($this->phpFiles !== null) {
            return $this->phpFiles;
        }

        // Initialize the array to store PHP files
        $this->phpFiles = [];

        // Iterate through each directory specified for scanning
        foreach ($this->directories as $directory) {
            // Normalize the directory path without resolving symlinks
            $normalizedDirectory = $this->filePath()->normalizePathWithoutResolvingSymlinks($directory);

            // Collect PHP files from the normalized directory
            $this->collectPhpFilesFromDirectory($normalizedDirectory);
        }

        // Remove duplicate file paths, set the phpFiles property and return the list
        return $this->phpFiles = array_unique($this->phpFiles);
    }

    /**
     * Get the FilePath instance for path normalization.
     *
     * @return FilePath The FilePath instance.
     */
    public function filePath(): FilePath
    {
        // Return the existing FilePath instance if it exists
        if ($this->filePath !== null) {
            return $this->filePath;
        }

        // Create a new FilePath instance, set it and return it
        return $this->filePath = new FilePath();
    }

    /**
     * Check if a path is excluded from the scan.
     *
     * @param  string  $path  The path to check.
     * @return bool True if the path is excluded, false otherwise.
     */
    public function isExcluded(string $path): bool
    {
        // Iterate over the exclude paths
        foreach ($this->excludePaths as $excludePath) {
            // Normalize the exclude path without resolving symlinks
            $normalizedExcludePath = $this->filePath()
                ->normalizePathWithoutResolvingSymlinks($excludePath);

            // Check if the path starts with the normalized exclude path
            if (str_starts_with($path, $normalizedExcludePath)) {
                // Return true if the path is excluded
                return true;
            }
        }

        // Return false if no exclude path matched
        return false;
    }

    /**
     * Collect PHP files from a single directory.
     *
     * @param  string  $directory  The directory to scan.
     *
     * @throws InvalidArgumentException If the directory does not exist.
     */
    protected function collectPhpFilesFromDirectory(string $directory): void
    {
        // Check if the directory exists
        if (!is_dir($directory)) {
            // Throw an exception if the directory doesn't exist
            throw new InvalidArgumentException("Directory does not exist: $directory");
        }

        // Check if this real path has already been scanned, skip if so
        $realPath = realpath($directory);
        if (in_array($realPath, $this->scannedRealPaths)) {
            return;
        }

        // Add the real path to the list of scanned paths
        $this->scannedRealPaths[] = $realPath;

        try {
            // Resolve all PHP files in the directory
            $this->resolvePhpFiles($directory);
        } catch (Exception $e) {
            // Throw an exception if an error occurs during directory scanning
            throw new InvalidArgumentException("Error scanning directory {$directory}: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Helper method to resolve all the PHP files in a directory.
     *
     * @param  string  $directory  The directory to scan.
     */
    protected function resolvePhpFiles(string $directory): void
    {
        // Create a recursive directory iterator that doesn't follow symlinks
        $directoryIterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);

        // Create a recursive iterator to traverse the directory tree
        $iterator = new RecursiveIteratorIterator($directoryIterator);

        // Iterate over all files in the directory
        foreach ($iterator as $file) {
            // Get the full path of the file, preserving symlinks
            $pathName = $file->getPathname();

            // Skip the file if it's excluded
            if ($this->isExcluded($pathName)) {
                continue;
            }

            // Check if the file is a PHP file
            if ($this->isPhpFile($file)) {
                // Add the pathname of the file to the list, preserving symlinks
                $this->phpFiles[] = $pathName;
            }
        }
    }

    /**
     * Check if a file is a PHP file.
     *
     * @param  SplFileInfo  $file  The file to check.
     * @return bool True if it's a PHP file, false otherwise.
     */
    protected function isPhpFile(SplFileInfo $file): bool
    {
        // Check if the file is a regular file and has a .php extension (case-insensitive)
        return $file->isFile() && strtolower($file->getExtension()) === 'php';
    }
}
