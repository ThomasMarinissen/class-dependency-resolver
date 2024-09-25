<?php

namespace Thomasmarinissen\ClassDependencyResolver;

use InvalidArgumentException;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory as PhpParserFactory;
use PhpParser\PhpVersion;
use Thomasmarinissen\ClassDependencyResolver\Resolve\DependencyExtractor;
use Thomasmarinissen\ClassDependencyResolver\Resolve\FileScanner;

/**
 * Class Resolver
 *
 * Responsible for mapping fully qualified class names, interfaces, and traits
 * to their corresponding file paths. It also manages the direct dependencies between files.
 */
class Resolver
{
    /**
     * @var ?FileScanner Instance of FileScanner to scan directories for PHP files.
     */
    protected ?FileScanner $fileScanner = null;

    /**
     * @var array Mapping of fully qualified class/interface/trait names to file paths.
     */
    protected array $nameToFileMap = [];

    /**
     * @var array Mapping of file paths to their direct dependencies (fully qualified class names).
     */
    protected array $fileToDependenciesMap = [];

    /**
     * @var ?NodeTraverser PHP-Parser ?NodeTraverser instance for traversing AST nodes.
     */
    protected ?NodeTraverser $nodeTraverser = null;

    /**
     * @var ?Parser PHP-Parser instance for parsing PHP files.
     */
    protected ?Parser $parser = null;

    /**
     * @var ?PhpParserFactory PHP-Parser Factory instance for creating parsers.
     */
    protected ?PhpParserFactory $parserFactory = null;

    /**
     * @var PhpVersion PHP version to target.
     */
    protected PhpVersion $phpVersion;

    /**
     * @var bool Flag to determine if the dependency maps have been built.
     */
    protected bool $mapsBuilt = false;

    /**
     * Constructor
     *
     * Initializes the DependencyResolver with directories to scan and a ParserFactory.
     *
     * @param  array  $directories  List of directories to scan for PHP files.
     * @param  PhpVersion|null  $phpVersion  (Optional) PHP version to target.
     *
     * @throws InvalidArgumentException If no directories are provided.
     */
    public function __construct(protected readonly array $directories, ?PhpVersion $phpVersion = null)
    {
        // Validate that at least one directory is provided.
        $this->validateDirectories();

        // Determine the PHP version to target; default to the host version if not specified.
        $this->phpVersion = $phpVersion ?? PhpVersion::getHostVersion();
    }

    /**
     * Get the FileScanner instance.
     *
     * @return FileScanner The FileScanner instance.
     */
    public function fileScanner(): FileScanner
    {
        // If the FileScanner instance has already been created, return it.
        if ($this->fileScanner !== null) {
            return $this->fileScanner;
        }

        // Create a new FileScanner instance, set it and return it.
        return $this->fileScanner = new FileScanner($this->directories);
    }

    /**
     * Get the PhpParserFactory class
     *
     * @return PhpParserFactory The PhpParserFactory instance.
     */
    public function parserFactory(): PhpParserFactory
    {
        // If the PhpParserFactory instance has already been created, return it.
        if ($this->parserFactory !== null) {
            return $this->parserFactory;
        }

        // Create a new PhpParserFactory instance, set it and return it.
        return $this->parserFactory = new PhpParserFactory();
    }

    /**
     * Get the parser instance.
     *
     * @return Parser The Parser instance.
     */
    public function parser(): Parser
    {
        // If the Parser instance has already been created, return it.
        if ($this->parser !== null) {
            return $this->parser;
        }

        // Create a new Parser instance, set it and return it.
        return $this->parser = $this->parserFactory()->createForVersion($this->phpVersion);
    }

    /**
     * Build the dependency maps by scanning and parsing PHP files.
     *
     * @return void
     */
    public function buildDependencyMaps(): void
    {
        // Retrieve all PHP files using the FileScanner.
        $phpFiles = $this->fileScanner()
            ->phpFiles();

        // Iterate through each PHP file to parse and extract dependencies.
        foreach ($phpFiles as $filePath) {
            // Process the current PHP file.
            $this->processFile($filePath);
        }

        // Set the flag to indicate that the maps have been built.
        $this->mapsBuilt = true;
    }

    /**
     * Ensure that the dependency maps are built.
     *
     * @return void
     */
    public function ensureDependencyMapsBuilt(): void
    {
        // If the dependency maps have not been built, build them now.
        if (!$this->mapsBuilt) {
            // Build the dependency maps.
            $this->buildDependencyMaps();
        }
    }

    /**
     * Get the file path for a given fully qualified class/interface/trait name.
     *
     * @param  string  $name  The fully qualified name of the class, interface, or trait.
     * @return string|null The file path if found, or null otherwise.
     */
    public function filePathByName(string $name): ?string
    {
        // Get the name to file map.
        $nameToFileMap = $this->allMappedNames();

        // Return the file path if the name exists in the map, otherwise null.
        return $nameToFileMap[$name] ?? null;
    }

    /**
     * Get the fully qualified name for a given file path.
     *
     * @param  string  $filePath  The file path to retrieve the name for.
     * @return string|null The fully qualified name if found, or null otherwise.
     */
    public function nameByFilePath(string $filePath): ?string
    {
        // Normalize the file path to ensure consistency.
        $filePath = $this->fileScanner()
            ->filePath()
            ->normalizePath($filePath);

        // Get the name to file map.
        $nameToFileMap = $this->allMappedNames();

        // Use array_search to find the name by file path.
        $name = array_search($filePath, $nameToFileMap);

        // Return the name if found, otherwise null.
        return $name !== false ? $name : null;
    }

    /**
     * Get the direct dependencies for a given file path.
     *
     * @param  string  $filePath  The file path to retrieve dependencies for.
     * @return array The list of direct dependencies (fully qualified class names).
     */
    public function dependenciesByFile(string $filePath): array
    {
        // Normalize the file path to ensure consistency.
        $filePath = $this->fileScanner()
            ->filePath()
            ->normalizePath($filePath);

        // Get the file do dependencies map.
        $fileToDependenciesMap = $this->allFileDependencies();

        // Return the dependencies if the file exists in the map, otherwise an empty array.
        return $fileToDependenciesMap[$filePath] ?? [];
    }

    /**
     * Get the direct dependencies for a given class/interface/trait name.
     *
     * @param  string  $name  The fully qualified name of the class, interface, or trait.
     * @return array The list of direct dependencies (fully qualified class names).
     */
    public function dependenciesByName(string $name): array
    {
        // Retrieve the file path for the given name.
        $filePath = $this->filePathByName($name);

        // If the file path is not found, return an empty array.
        if ($filePath === null) {
            return [];
        }

        // Return the dependencies for the retrieved file path.
        return $this->dependenciesByFile($filePath);
    }

    /**
     * Get all mapped names (classes, interfaces, traits).
     *
     * @return array The list of all fully qualified names mapped to file paths.
     */
    public function allMappedNames(): array
    {
        // Ensure that the dependency maps are built.
        $this->ensureDependencyMapsBuilt();

        // Return the entire name to the file map.
        return $this->nameToFileMap;
    }

    /**
     * Get all file dependencies.
     *
     * @return array The list of all file paths mapped to their dependencies.
     */
    public function allFileDependencies(): array
    {
        // Ensure that the dependency maps are built.
        $this->ensureDependencyMapsBuilt();

        // Return the entire file to the dependencies map.
        return $this->fileToDependenciesMap;
    }

    /**
     * Validate that at least one directory is provided.
     *
     * @return void
     *
     * @throws InvalidArgumentException If no directories are provided.
     */
    protected function validateDirectories(): void
    {
        // Check if the directories array is empty.
        if (empty($this->directories)) {
            // Throw an exception if no directories are provided.
            throw new InvalidArgumentException('At least one directory must be provided.');
        }
    }

    /**
     * Process a single PHP file to extract declarations and dependencies.
     *
     * @param  string  $filePath  The path of the PHP file to process.
     */
    protected function processFile(string $filePath): void
    {
        // Normalize the file path before processing
        $normalizedPath = $this->fileScanner()
            ->filePath()
            ->normalizePath($filePath);

        // Read the content of the current PHP file.
        $code = $this->readFileContent($normalizedPath);

        // If the file content could not be read, skip processing.
        if ($code === false) {
            return;
        }

        try {
            // Parse the PHP code into an Abstract Syntax Tree (AST).
            $ast = $this->parser()->parse($code);

            // If parsing fails, skip processing.
            if ($ast === null) {
                return;
            }

            // Extract dependencies and declarations from the AST.
            $this->extractDependenciesAndDeclarations($ast, $normalizedPath);
        } catch (Error) {
            // If a parsing error occurs, skip the current file.
            return;
        }
    }

    /**
     * Read the content of a PHP file.
     *
     * @param  string  $filePath  The path of the PHP file.
     * @return string|false The content of the file or false on failure.
     */
    protected function readFileContent(string $filePath): string|false
    {
        // Retrieve the contents of the specified file.
        return file_get_contents($filePath);
    }

    /**
     * Extract dependencies and declarations from the AST and map them.
     *
     * @param  array  $ast  The Abstract Syntax Tree of the PHP file.
     * @param  string  $filePath  The path of the PHP file.
     */
    protected function extractDependenciesAndDeclarations(array $ast, string $filePath): void
    {
        // Create a new instance of DependencyExtractor.
        $dependencyExtractor = new DependencyExtractor();

        // Create the NodeTraverser instance.
        $nodeTraverser = new NodeTraverser();

        // Add the DependencyExtractor to the NodeTraverser.
        $nodeTraverser->addVisitor($dependencyExtractor);

        // Traverse the AST to extract dependencies and declarations.
        $nodeTraverser->traverse($ast);

        // Map the extracted declarations and dependencies.
        $this->mapDeclarations($dependencyExtractor, $filePath);
    }

    /**
     * Map declarations (classes, interfaces, traits) to their file paths and dependencies.
     *
     * @param  DependencyExtractor  $extractor  The DependencyExtractor instance.
     * @param  string  $filePath  The path of the PHP file.
     */
    protected function mapDeclarations(DependencyExtractor $extractor, string $filePath): void
    {
        // Map each declared class to its file path.
        $this->mapClasses($extractor->classNames(), $filePath);

        // Map each declared interface to its file path.
        $this->mapInterfaces($extractor->declaredInterfaces(), $filePath);

        // Map each declared trait to its file path.
        $this->mapTraits($extractor->declaredTraits(), $filePath);

        // Map the current file to its dependencies.
        $this->fileToDependenciesMap[$filePath] = $extractor->dependencies();
    }

    /**
     * Map class names to their file paths.
     *
     * @param  array  $classes  List of class names.
     * @param  string  $filePath  The path of the PHP file.
     */
    protected function mapClasses(array $classes, string $filePath): void
    {
        // Iterate through each class name.
        foreach ($classes as $className) {
            // Map the class name to its file path.
            $this->nameToFileMap[$className] = $filePath;
        }
    }

    /**
     * Map interface names to their file paths.
     *
     * @param  array  $interfaces  List of interface names.
     * @param  string  $filePath  The path of the PHP file.
     */
    protected function mapInterfaces(array $interfaces, string $filePath): void
    {
        // Iterate through each interface name.
        foreach ($interfaces as $interfaceName) {
            // Map the interface name to its file path.
            $this->nameToFileMap[$interfaceName] = $filePath;
        }
    }

    /**
     * Map trait names to their file paths.
     *
     * @param  array  $traits  List of trait names.
     * @param  string  $filePath  The path of the PHP file.
     */
    protected function mapTraits(array $traits, string $filePath): void
    {
        // Iterate through each trait name.
        foreach ($traits as $traitName) {
            // Map the trait name to its file path.
            $this->nameToFileMap[$traitName] = $filePath;
        }
    }
}
