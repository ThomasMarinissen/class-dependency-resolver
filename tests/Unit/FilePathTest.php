<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Thomasmarinissen\ClassDependencyResolver\Resolve\FilePath;

class FilePathTest extends TestCase
{
    private FilePath $filePath;

    // Set up the FilePath instance before each test
    protected function setUp(): void
    {
        $this->filePath = new FilePath();
    }

    public function test_empty_path_throws_exception()
    {
        // Expect an InvalidArgumentException to be thrown
        $this->expectException(InvalidArgumentException::class);

        // Call normalizePath with an empty string
        $this->filePath->normalizePath('');
    }

    public function test_realpath_returns_valid_path()
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test');

        // Call normalizePath with the temporary file path
        $result = $this->filePath->normalizePath($tempFile);

        // Assert that the result matches the realpath of the temporary file
        $this->assertEquals(realpath($tempFile), $result);

        // Clean up by deleting the temporary file
        unlink($tempFile);
    }

    public function test_home_directory_expansion()
    {
        // Skip this test on Windows as '~' expansion might not be applicable
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Home directory expansion not applicable on Windows.');
        }

        // Get the current home directory
        $homeDir = getenv('HOME');

        // Call normalizePath with a path starting with '~'
        $result = $this->filePath->normalizePath('~/test/path');

        // Assert that the result starts with the actual home directory path
        $this->assertStringStartsWith($homeDir, $result);
    }

    public function test_windows_backslash_conversion()
    {
        // Call normalizePath with a path containing backslashes
        $result = $this->filePath->normalizePath('C:\\Users\\Test\\Path');

        // Assert that the result contains only forward slashes or appropriate directory separators
        $this->assertStringNotContainsString('\\', $result);
    }

    public function test_relative_path_conversion()
    {
        // Get the current working directory
        $cwd = getcwd();

        // Call normalizePath with a relative path
        $result = $this->filePath->normalizePath('relative/path');

        // Assert that the result starts with the current working directory
        $this->assertStringStartsWith($cwd, $result);
    }

    public function test_remove_current_directory_references()
    {
        // Call normalizePath with a path containing './' references
        $result = $this->filePath->normalizePath('/path/./to/./file');

        // Assert that the result does not contain './' references
        $this->assertStringNotContainsString('./', $result);
    }

    public function test_resolve_parent_directory_references()
    {
        // Call normalizePath with a path containing '../' references
        $result = $this->filePath->normalizePath('/path/to/../file');

        // Assert that the result correctly resolves parent directory references
        $this->assertEquals('/path/file', $result);
    }

    public function test_multiple_slash_replacement()
    {
        // Call normalizePath with a path containing multiple consecutive slashes
        $result = $this->filePath->normalizePath('/path//to////file');

        // Assert that the result contains only single slashes between path components
        $this->assertEquals('/path/to/file', $result);
    }

    public function test_path_with_symlinks()
    {
        // Create a temporary directory
        $tempDir = sys_get_temp_dir() . '/test_symlink_' . uniqid();
        mkdir($tempDir);

        // Create a file and a symlink to it
        $realFile = $tempDir . '/realfile.txt';
        file_put_contents($realFile, 'test content');
        $symlinkPath = $tempDir . '/symlink.txt';
        symlink($realFile, $symlinkPath);

        // Call normalizePath with the symlink path
        $result = $this->filePath->normalizePath($symlinkPath);

        // Assert that the result resolves the symlink correctly
        $this->assertEquals(realpath($realFile), $result);

        // Clean up
        unlink($symlinkPath);
        unlink($realFile);
        rmdir($tempDir);
    }

    public function test_non_existent_path_normalization()
    {
        // Call normalizePath with a non-existent path
        $result = $this->filePath->normalizePath('/path/to/non/existent/file.txt');

        // Assert that the result is a valid normalized path string
        $this->assertIsString($result);
        $this->assertStringStartsWith('/', $result);
    }

    public function test_path_with_special_characters()
    {
        // Call normalizePath with a path containing spaces and special characters
        $result = $this->filePath->normalizePath('/path/to/file with spaces and $pecial chars.txt');

        // Assert that the result correctly handles special characters
        $this->assertStringEndsWith('file with spaces and $pecial chars.txt', $result);
    }
}
