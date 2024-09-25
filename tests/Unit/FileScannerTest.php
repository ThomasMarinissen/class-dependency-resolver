<?php

namespace Tests\Unit;

use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SplFileInfo;
use Thomasmarinissen\ClassDependencyResolver\Resolve\FileScanner;

class FileScannerTest extends TestCase
{
    /**
     * Temporary directory path for tests.
     *
     * @var string
     */
    private string $tempDir;

    /**
     * Set up the temporary directory and mocked FilePath before each test.
     */
    protected function setUp(): void
    {
        // Get the system temporary directory path
        parent::setUp();

        // Create a unique temporary directory path
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'file_scanner_test_' . uniqid();

        // Create the temporary directory with full permissions
        mkdir($this->tempDir, 0777, true);
    }

    /**
     * Clean up the temporary directory after each test.
     */
    protected function tearDown(): void
    {
        // Call the removeDirectory method to delete the temporary directory
        $this->removeDirectory($this->tempDir);
    }

    /**
     * Recursively remove a directory and its contents.
     *
     * @param  string  $dir  The directory path to remove.
     */
    private function removeDirectory(string $dir): void
    {
        // Check if the directory exists
        if (!is_dir($dir)) {
            return;
        }

        // Create a FilesystemIterator to iterate over the directory items
        $items = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);

        // Iterate over each item in the directory
        foreach ($items as $item) {
            // If the item is a directory, remove it recursively
            if ($item->isDir()) {
                $this->removeDirectory($item->getPathname());
            } else {
                // Otherwise, unlink the file
                unlink($item->getPathname());
            }
        }

        // Remove the directory itself
        rmdir($dir);
    }

    public function test_php_files_returns_cached_results()
    {
        // Create a PHP file in the temporary directory
        file_put_contents($this->tempDir . '/file.php', '<?php');

        // Create a new FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Call phpFiles() once to cache the results
        $result1 = $scanner->phpFiles();

        // Call phpFiles() again to retrieve cached results
        $result2 = $scanner->phpFiles();

        // Assert that the cached results are returned
        $this->assertSame($result1, $result2);
    }

    public function test_php_files_scans_directories()
    {
        // Create two PHP files in the temporary directory
        file_put_contents($this->tempDir . '/file1.php', '<?php');
        file_put_contents($this->tempDir . '/file2.php', '<?php');

        // Create a new FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Call phpFiles() to scan the directory and collect PHP files
        $phpFiles = $scanner->phpFiles();

        // Assert that two PHP files are found
        $this->assertCount(2, $phpFiles);

        // Assert that the first PHP file is included in the result
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'file1.php', $phpFiles);

        // Assert that the second PHP file is included in the result
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'file2.php', $phpFiles);
    }

    public function test_file_path_instance_is_created_and_cached()
    {
        // Create a new FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Call filePath() once to create the FilePath instance
        $filePath1 = $scanner->filePath();

        // Call filePath() again to retrieve the cached FilePath instance
        $filePath2 = $scanner->filePath();

        // Assert that the same FilePath instance is returned
        $this->assertSame($filePath1, $filePath2);
    }

    public function test_is_excluded_returns_true_for_excluded_path()
    {
        // Define the exclude path
        $excludePath = $this->tempDir . '/exclude';

        // Create a new FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir], [$excludePath]);

        // Assert that isExcluded() returns true for the excluded path
        $this->assertTrue($scanner->isExcluded($this->tempDir . '/exclude/file.php'));
    }

    public function test_is_excluded_returns_false_for_non_excluded_path()
    {
        // Define the exclude path
        $excludePath = $this->tempDir . '/exclude';

        // Create a new FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir], [$excludePath]);

        // Assert that isExcluded() returns false for a non-excluded path
        $this->assertFalse($scanner->isExcluded($this->tempDir . '/include/file.php'));
    }

    public function test_collect_php_files_from_directory_throws_exception_for_non_existent_directory()
    {
        // Create a new FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Use Reflection to access the protected collectPhpFilesFromDirectory() method
        $reflection = new ReflectionClass($scanner);
        $collectMethod = $reflection->getMethod('collectPhpFilesFromDirectory');

        // Assert that an InvalidArgumentException is thrown for a non-existent directory
        $this->expectException(InvalidArgumentException::class);
        $collectMethod->invoke($scanner, '/non/existent/directory');
    }

    public function test_php_files_adds_php_files()
    {
        // Create PHP files in the temporary directory
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file1.php', '<?php');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file2.php', '<?php');

        // Create a FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Call the phpFiles method
        $phpFiles = $scanner->phpFiles();

        // Debug information
        $this->addToAssertionCount(1);

        // Assert that PHP files are added to the result array
        $this->assertCount(2, $phpFiles, 'Expected 2 PHP files, but found ' . count($phpFiles));
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'file1.php', $phpFiles, 'file1.php not found in phpFiles array');
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'file2.php', $phpFiles, 'file2.php not found in phpFiles array');
    }

    public function test_php_files_excludes_non_php_files()
    {
        // Create PHP and non-PHP files in the temporary directory
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file1.php', '<?php');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file2.txt', 'text');

        // Create a FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Call the phpFiles method
        $phpFiles = $scanner->phpFiles();

        // Debug information
        $this->addToAssertionCount(1);

        // Assert that only PHP files are added to the result array
        $this->assertCount(1, $phpFiles, 'Expected 1 PHP file, but found ' . count($phpFiles));
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'file1.php', $phpFiles, 'file1.php not found in phpFiles array');
        $this->assertNotContains($this->tempDir . DIRECTORY_SEPARATOR . 'file2.txt', $phpFiles, 'file2.txt should not be in phpFiles array');
    }

    public function test_php_files_excludes_specified_paths()
    {
        // Create PHP files in the temporary directory, including in an excluded path
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'exclude');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'file1.php', '<?php');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'exclude' . DIRECTORY_SEPARATOR . 'file2.php', '<?php');

        // Create a FileScanner instance with an exclude path
        $scanner = $this->createFileScanner([$this->tempDir], [$this->tempDir . DIRECTORY_SEPARATOR . 'exclude']);

        // Call the phpFiles method
        $phpFiles = $scanner->phpFiles();

        // Assert that PHP files in the excluded path are not added to the result array
        $this->assertCount(1, $phpFiles);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'file1.php', $phpFiles);
        $this->assertNotContains($this->tempDir . DIRECTORY_SEPARATOR . 'exclude' . DIRECTORY_SEPARATOR . 'file2.php', $phpFiles);
    }

    public function test_is_php_file_returns_true_for_php_files()
    {
        // Create a PHP file
        $phpFile = $this->tempDir . DIRECTORY_SEPARATOR . 'file.php';
        file_put_contents($phpFile, '<?php');

        // Create a FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Use reflection to access protected method
        $reflection = new ReflectionClass($scanner);
        $isPhpFileMethod = $reflection->getMethod('isPhpFile');

        // Assert that isPhpFile returns true for a PHP file
        $this->assertTrue($isPhpFileMethod->invoke($scanner, new SplFileInfo($phpFile)));
    }

    public function test_is_php_file_returns_false_for_non_php_files()
    {
        // Create a non-PHP file
        $nonPhpFile = $this->tempDir . DIRECTORY_SEPARATOR . 'file.txt';
        file_put_contents($nonPhpFile, 'text');

        // Create a FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Use reflection to access protected method
        $reflection = new ReflectionClass($scanner);
        $isPhpFileMethod = $reflection->getMethod('isPhpFile');

        // Assert that isPhpFile returns false for a non-PHP file
        $this->assertFalse($isPhpFileMethod->invoke($scanner, new SplFileInfo($nonPhpFile)));
    }

    public function test_php_files_removes_duplicates()
    {
        // Create temporary directories with duplicate PHP files
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'dir1');
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'dir2');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'dir1' . DIRECTORY_SEPARATOR . 'file.php', '<?php');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'dir2' . DIRECTORY_SEPARATOR . 'file.php', '<?php');

        // Create a FileScanner instance with both directories
        $scanner = $this->createFileScanner([
            $this->tempDir . DIRECTORY_SEPARATOR . 'dir1',
            $this->tempDir . DIRECTORY_SEPARATOR . 'dir2',
        ]);

        // Get the PHP files
        $phpFiles = $scanner->phpFiles();

        // Assert that the result contains no duplicates
        $this->assertCount(2, $phpFiles);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'dir1' . DIRECTORY_SEPARATOR . 'file.php', $phpFiles);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'dir2' . DIRECTORY_SEPARATOR . 'file.php', $phpFiles);
    }

    public function test_php_files_handles_empty_directories()
    {
        // Create an empty temporary directory
        $emptyDir = $this->tempDir . DIRECTORY_SEPARATOR . 'empty';
        mkdir($emptyDir);

        // Create a FileScanner instance with the empty directory
        $scanner = $this->createFileScanner([$emptyDir]);

        // Get the PHP files
        $phpFiles = $scanner->phpFiles();

        // Assert that the result is an empty array
        $this->assertEmpty($phpFiles);
    }

    public function test_php_files_handles_nested_directories()
    {
        // Create temporary directories with nested structure and PHP files
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'level1');
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'level1' . DIRECTORY_SEPARATOR . 'level2');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'level1' . DIRECTORY_SEPARATOR . 'file1.php', '<?php');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'level1' . DIRECTORY_SEPARATOR . 'level2' . DIRECTORY_SEPARATOR . 'file2.php', '<?php');

        // Create a FileScanner instance with the top-level directory
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Get the PHP files
        $phpFiles = $scanner->phpFiles();

        // Assert that all PHP files, including those in nested directories, are found
        $this->assertCount(2, $phpFiles);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'level1' . DIRECTORY_SEPARATOR . 'file1.php', $phpFiles);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'level1' . DIRECTORY_SEPARATOR . 'level2' . DIRECTORY_SEPARATOR . 'file2.php', $phpFiles);
    }

    public function test_php_files_handles_symlinks()
    {
        // Skip this test on Windows as symlinks might not be supported
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Symlinks are not consistently supported on Windows.');
        }

        // Create a directory with a PHP file
        $realDir = $this->tempDir . DIRECTORY_SEPARATOR . 'real';
        mkdir($realDir);
        $realFile = $realDir . DIRECTORY_SEPARATOR . 'file.php';
        file_put_contents($realFile, '<?php');

        // Create a symlink to the directory
        $linkDir = $this->tempDir . DIRECTORY_SEPARATOR . 'link';
        symlink($realDir, $linkDir);

        // Create a FileScanner instance with the symlink directory
        $scanner = $this->createFileScanner([$linkDir]);

        // Get the PHP files
        $phpFiles = $scanner->phpFiles();

        // Assert that exactly one PHP file is found
        $this->assertCount(1, $phpFiles);

        // Get the found file path
        $foundFile = reset($phpFiles);

        // Assert that the found file exists and is readable
        $this->assertFileExists($foundFile);
        $this->assertIsReadable($foundFile);

        // Assert that the found file is the symlinked file
        $expectedPath = $linkDir . DIRECTORY_SEPARATOR . 'file.php';
        $this->assertEquals($expectedPath, $foundFile, 'Found file does not match expected symlink path');
    }

    public function test_php_files_handles_file_permissions()
    {
        // Create a PHP file with read-only permissions
        $readOnlyFile = $this->tempDir . DIRECTORY_SEPARATOR . 'readonly.php';
        file_put_contents($readOnlyFile, '<?php');
        chmod($readOnlyFile, 0444);

        // Create a FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Get the PHP files
        $phpFiles = $scanner->phpFiles();

        // Assert that the read-only PHP file is found
        $this->assertCount(1, $phpFiles);
        $this->assertContains($readOnlyFile, $phpFiles);

        // Reset file permissions to allow deletion during tearDown
        chmod($readOnlyFile, 0644);
    }

    public function test_php_files_handles_large_number_of_files()
    {
        // Create a large number of PHP files
        for ($i = 0; $i < 100; $i++) {
            file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . "file$i.php", '<?php');
        }

        // Create a FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Get the PHP files
        $phpFiles = $scanner->phpFiles();

        // Assert that all 100 PHP files are found
        $this->assertCount(100, $phpFiles);
    }

    public function test_php_files_respects_case_sensitivity()
    {
        // Create PHP files with different case extensions
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'lowercase.php', '<?php');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'uppercase.PHP', '<?php');

        // Create a FileScanner instance
        $scanner = $this->createFileScanner([$this->tempDir]);

        // Get the PHP files
        $phpFiles = $scanner->phpFiles();

        // Assert that both files are found, regardless of case
        $this->assertCount(2, $phpFiles);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'lowercase.php', $phpFiles);
        $this->assertContains($this->tempDir . DIRECTORY_SEPARATOR . 'uppercase.PHP', $phpFiles);
    }

    /**
     * Create the FileScanner instance with the specified directories and exclude paths.
     *
     * @param  array  $directories  The directories to scan.
     * @param  array  $excludePaths  The paths to exclude from the scan.
     * @return FileScanner The FileScanner instance.
     */
    private function createFileScanner(array $directories, array $excludePaths = []): FileScanner
    {
        // Create a new FileScanner instance and return it
        return new FileScanner($directories, $excludePaths);
    }
}
