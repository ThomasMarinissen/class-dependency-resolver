<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Thomasmarinissen\ClassDependencyResolver\Resolver;

/**
 * Class ResolverTest
 *
 * Tests for the Resolver class.
 */
class ResolverTest extends TestCase
{
    /**
     * @var string The path to the temporary test directory.
     */
    protected string $testDir;

    /**
     * Set up the test environment by creating a temporary directory and sample PHP files.
     */
    protected function setUp(): void
    {
        // Create a unique temporary directory for testing
        $this->testDir = sys_get_temp_dir() . '/resolver_test_' . uniqid();

        // Create the test directory
        mkdir($this->testDir);

        // Create sample PHP files for testing
        $this->createSampleFiles();
    }

    protected function tearDown(): void
    {
        // Delete the temporary test directory and its contents
        $this->deleteDirectory($this->testDir);
    }

    public function test_user_cannot_initialize_resolver_without_directories()
    {
        // Expect an InvalidArgumentException to be thrown
        $this->expectException(InvalidArgumentException::class);

        // Initialize the Resolver with an empty array of directories
        new Resolver([]);
    }

    public function test_user_can_get_file_path_by_class_name()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Retrieve the file path for the class TestNamespace\ClassA
        $filePath = $resolver->filePathByName('TestNamespace\ClassA');

        // Define the expected file path
        $expectedPath = realpath($this->testDir . '/ClassA.php');

        // Assert that the retrieved file path matches the expected path
        $this->assertEquals($expectedPath, $filePath);
    }

    public function test_user_can_get_dependencies_by_class_name()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Get the dependencies for TestNamespace\ClassA
        $dependencies = $resolver->dependenciesByName('TestNamespace\ClassA');

        // Define the expected dependencies
        $expectedDependencies = ['TestNamespace\ClassB'];

        // Assert that the dependencies match the expected dependencies
        $this->assertEquals($expectedDependencies, $dependencies);
    }

    public function test_user_gets_null_for_nonexistent_class_name()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Try to retrieve the file path for a non-existent class
        $filePath = $resolver->filePathByName('TestNamespace\NonExistentClass');

        // Assert that the file path is null
        $this->assertNull($filePath);
    }

    public function test_user_gets_class_name_by_file_path()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Retrieve the class name for the file ClassA.php
        $className = $resolver->nameByFilePath($this->testDir . '/ClassA.php');

        // Define the expected class name
        $expectedName = 'TestNamespace\ClassA';

        // Assert that the retrieved class name matches the expected name
        $this->assertEquals($expectedName, $className);
    }

    public function test_user_gets_null_for_nonexistent_file_path()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Try to retrieve the class name for a non-existent file
        $className = $resolver->nameByFilePath('NonExistentFile.php');

        // Assert that the class name is null
        $this->assertNull($className);
    }

    public function test_user_gets_empty_dependencies_for_nonexistent_class_name()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Get the dependencies for a non-existent class
        $dependencies = $resolver->dependenciesByName('TestNamespace\NonExistentClass');

        // Assert that the dependencies array is empty
        $this->assertEmpty($dependencies);
    }

    public function test_resolver_handles_invalid_php_files_gracefully()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Get the list of all mapped names
        $mappedNames = $resolver->allMappedNames();

        // Assert that the invalid class is not included in the mapped names
        $this->assertArrayNotHasKey('TestNamespace\Invalid', $mappedNames);
    }

    public function test_user_can_get_all_mapped_names()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Get all mapped names
        $mappedNames = $resolver->allMappedNames();

        // Define the expected mapped names
        $expectedNames = [
            'TestNamespace\ClassA',
            'TestNamespace\ClassB',
            'TestNamespace\ClassD',
            'TestNamespace\ClassF',
            'TestNamespace\ClassG',
            'TestNamespace\ClassH',
            'TestNamespace\InterfaceC',
            'TestNamespace\TraitE',
            'TestNamespace\SubNamespace\ClassI',
        ];

        // Assert that all expected names are in the mapped names
        foreach ($expectedNames as $name) {
            $this->assertArrayHasKey($name, $mappedNames);
        }
    }

    public function test_user_can_get_dependencies_for_class_implementing_interface()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Get the dependencies for TestNamespace\ClassD
        $dependencies = $resolver->dependenciesByName('TestNamespace\ClassD');

        // Define the expected dependency
        $expectedDependencies = ['TestNamespace\InterfaceC'];

        // Assert that the dependencies match the expected dependencies
        $this->assertEquals($expectedDependencies, $dependencies);
    }

    public function test_user_can_get_dependencies_for_class_using_trait()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Get the dependencies for TestNamespace\ClassF
        $dependencies = $resolver->dependenciesByName('TestNamespace\ClassF');

        // Define the expected dependency
        $expectedDependencies = ['TestNamespace\TraitE'];

        // Assert that the dependencies match the expected dependencies
        $this->assertEquals($expectedDependencies, $dependencies);
    }

    public function test_resolver_handles_circular_dependencies()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Get the dependencies for TestNamespace\ClassG
        $dependenciesG = $resolver->dependenciesByName('TestNamespace\ClassG');

        // Get the dependencies for TestNamespace\ClassH
        $dependenciesH = $resolver->dependenciesByName('TestNamespace\ClassH');

        // Define the expected dependencies
        $expectedDependenciesG = ['TestNamespace\ClassH'];
        $expectedDependenciesH = ['TestNamespace\ClassG'];

        // Assert that ClassG depends on ClassH
        $this->assertEquals($expectedDependenciesG, $dependenciesG);

        // Assert that ClassH depends on ClassG
        $this->assertEquals($expectedDependenciesH, $dependenciesH);
    }

    public function test_resolver_includes_files_from_subdirectories()
    {
        // Create a new instance of the Resolver with the test directory
        $resolver = new Resolver([$this->testDir]);

        // Retrieve the file path for the class TestNamespace\SubNamespace\ClassI
        $filePath = $resolver->filePathByName('TestNamespace\SubNamespace\ClassI');

        // Define the expected file path
        $expectedPath = realpath($this->testDir . '/SubDir/ClassI.php');

        // Assert that the retrieved file path matches the expected path
        $this->assertEquals($expectedPath, $filePath);
    }

    private function createSampleFiles(): void
    {
        // Create the content for ClassA.php
        $classAContent = <<<'CODE'
        <?php
        namespace TestNamespace;

        use TestNamespace\ClassB;

        class ClassA
        {
            public function __construct(ClassB $b)
            {
                // ...
            }
        }
        CODE;

        // Write ClassA.php to the test directory
        file_put_contents($this->testDir . '/ClassA.php', $classAContent);

        // Create the content for ClassB.php
        $classBContent = <<<'CODE'
        <?php
        namespace TestNamespace;

        class ClassB
        {
            // ...
        }
        CODE;

        // Write ClassB.php to the test directory
        file_put_contents($this->testDir . '/ClassB.php', $classBContent);

        // Create the content for InterfaceC.php
        $interfaceCContent = <<<'CODE'
        <?php
        namespace TestNamespace;

        interface InterfaceC
        {
            public function doSomething();
        }
        CODE;

        // Write InterfaceC.php to the test directory
        file_put_contents($this->testDir . '/InterfaceC.php', $interfaceCContent);

        // Create the content for ClassD.php
        $classDContent = <<<'CODE'
        <?php
        namespace TestNamespace;

        class ClassD implements InterfaceC
        {
            public function doSomething()
            {
                // ...
            }
        }
        CODE;

        // Write ClassD.php to the test directory
        file_put_contents($this->testDir . '/ClassD.php', $classDContent);

        // Create the content for TraitE.php
        $traitEContent = <<<'CODE'
        <?php
        namespace TestNamespace;

        trait TraitE
        {
            public function helper()
            {
                // ...
            }
        }
        CODE;

        // Write TraitE.php to the test directory
        file_put_contents($this->testDir . '/TraitE.php', $traitEContent);

        // Create the content for ClassF.php
        $classFContent = <<<'CODE'
        <?php
        namespace TestNamespace;

        class ClassF
        {
            use TraitE;
        }
        CODE;

        // Write ClassF.php to the test directory
        file_put_contents($this->testDir . '/ClassF.php', $classFContent);

        // Create the content for an invalid PHP file Invalid.php
        $invalidContent = <<<'CODE'
        <?php
        namespace TestNamespace;

        class Invalid
        {
            public function __construct()
            {
                // Missing closing brace
        CODE;

        // Write Invalid.php to the test directory
        file_put_contents($this->testDir . '/Invalid.php', $invalidContent);

        // Create the content for ClassG.php
        $classGContent = <<<'CODE'
        <?php
        namespace TestNamespace;

        use TestNamespace\ClassH;

        class ClassG
        {
            public function __construct(ClassH $h)
            {
                // ...
            }
        }
        CODE;

        // Write ClassG.php to the test directory
        file_put_contents($this->testDir . '/ClassG.php', $classGContent);

        // Create the content for ClassH.php
        $classHContent = <<<'CODE'
        <?php
        namespace TestNamespace;

        use TestNamespace\ClassG;

        class ClassH
        {
            public function __construct(ClassG $g)
            {
                // ...
            }
        }
        CODE;

        // Write ClassH.php to the test directory
        file_put_contents($this->testDir . '/ClassH.php', $classHContent);

        // Create a subdirectory in the test directory
        $subDir = $this->testDir . '/SubDir';
        mkdir($subDir);

        // Create the content for ClassI.php in the subdirectory
        $classIContent = <<<'CODE'
        <?php
        namespace TestNamespace\SubNamespace;

        class ClassI
        {
            // ...
        }
        CODE;

        // Write ClassI.php to the subdirectory
        file_put_contents($subDir . '/ClassI.php', $classIContent);
    }

    /**
     * Delete a directory and its contents recursively.
     *
     * @param  string  $dir  The directory path.
     */
    private function deleteDirectory(string $dir): void
    {
        // Check if the directory exists
        if (!file_exists($dir)) {
            return;
        }

        // Get all files and directories within the directory
        $files = array_diff(scandir($dir), ['.', '..']);

        // Iterate over each item
        foreach ($files as $file) {
            // Construct the full path
            $path = $dir . DIRECTORY_SEPARATOR . $file;

            // If it's a directory, recursively delete it
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                // If it's a file, delete it
                unlink($path);
            }
        }

        // Remove the directory itself
        rmdir($dir);
    }
}
