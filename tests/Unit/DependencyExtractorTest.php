<?php

namespace Tests\Unit;

use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\Parser\Php7;
use PHPUnit\Framework\TestCase;
use Thomasmarinissen\ClassDependencyResolver\DependencyExtractor;

/**
 * Class DependencyExtractorTest
 *
 * Tests for the DependencyExtractor class to ensure accurate extraction of class dependencies.
 */
class DependencyExtractorTest extends TestCase
{
    private function getDependencies(string $code): array
    {
        // Create a PHP parser
        $parser = new Php7(new Lexer());

        // Parse the PHP code into AST
        $ast = $parser->parse($code);

        // Initialize the DependencyExtractor
        $dependencyExtractor = new DependencyExtractor();

        // Create a NodeTraverser
        $traverser = new NodeTraverser();

        // Add the DependencyExtractor to the traverser
        $traverser->addVisitor($dependencyExtractor);

        // Traverse the AST
        $traverser->traverse($ast);

        // Return the extracted dependencies
        return $dependencyExtractor->dependencies();
    }

    public function test_extracts_dependencies_from_class_instantiation()
    {
        // Define PHP code with a class that instantiates another class
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Services\UserService;

        class UserController
        {
            public function __construct()
            {
                $this->service = new UserService();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService is in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
    }

    public function test_extracts_dependencies_from_static_calls()
    {
        // Define PHP code with a static method call
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Helpers\StringHelper;

        class Util
        {
            public function process()
            {
                StringHelper::sanitize();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that StringHelper is in dependencies
        $this->assertContains('App\Helpers\StringHelper', $dependencies);
    }

    public function test_extracts_dependencies_from_assignments_with_new()
    {
        // Define PHP code with a new instantiation in an assignment
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Repositories\UserRepository;

        class UserService
        {
            private $repository;

            public function __construct()
            {
                $this->repository = new UserRepository();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserRepository is in dependencies
        $this->assertContains('App\Repositories\UserRepository', $dependencies);
    }

    public function test_extracts_dependencies_from_instanceof_expressions()
    {
        // Define PHP code with an instanceof expression
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Models\User;

        class AuthService
        {
            public function authenticate($object)
            {
                if ($object instanceof User) {
                    // Authentication logic
                }
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that User is in dependencies
        $this->assertContains('App\Models\User', $dependencies);
    }

    public function test_extracts_dependencies_from_catch_clauses()
    {
        // Define PHP code with a catch clause
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Exceptions\AuthenticationException;

        class AuthService
        {
            public function authenticate()
            {
                try {
                    // Authentication logic
                } catch (AuthenticationException $e) {
                    // Handle exception
                }
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that AuthenticationException is in dependencies
        $this->assertContains('App\Exceptions\AuthenticationException', $dependencies);
    }

    public function test_extracts_dependencies_from_parameter_types()
    {
        // Define PHP code with type-hinted parameters
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Services\UserService;

        class UserController
        {
            private $service;

            public function __construct(UserService $service)
            {
                $this->service = $service;
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService is in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
    }

    public function test_extracts_dependencies_from_property_types()
    {
        // Define PHP code with type-hinted properties
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Repositories\UserRepository;

        class UserService
        {
            private UserRepository $repository;
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserRepository is in dependencies
        $this->assertContains('App\Repositories\UserRepository', $dependencies);
    }

    public function test_extracts_dependencies_from_method_return_types()
    {
        // Define PHP code with type-hinted method return types
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Models\User;

        class UserService
        {
            public function getUser(): User
            {
                // Return user
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that User is in dependencies
        $this->assertContains('App\Models\User', $dependencies);
    }

    public function test_extracts_dependencies_from_class_inheritance()
    {
        // Define PHP code with class inheritance
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Base\BaseController;

        class UserController extends BaseController
        {
            // Controller logic
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that BaseController is in dependencies
        $this->assertContains('App\Base\BaseController', $dependencies);
    }

    public function test_extracts_dependencies_from_interface_implementation()
    {
        // Define PHP code with interface implementation
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Contracts\UserInterface;

        class UserService implements UserInterface
        {
            // Implementation logic
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserInterface is in dependencies
        $this->assertContains('App\Contracts\UserInterface', $dependencies);
    }

    public function test_extracts_dependencies_from_trait_usage()
    {
        // Define PHP code with trait usage
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Traits\Loggable;

        class UserService
        {
            use Loggable;

            // Service logic
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that Loggable is in dependencies
        $this->assertContains('App\Traits\Loggable', $dependencies);
    }

    public function test_resolves_fully_qualified_class_names()
    {
        // Define PHP code with fully qualified class names
        $code = <<<'CODE'
        <?php
        namespace App;

        class UserController
        {
            public function __construct()
            {
                $this->service = new \App\Services\UserService();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService is in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
    }

    public function test_resolves_relative_class_names()
    {
        // Define PHP code with relative class names
        $code = <<<'CODE'
        <?php
        namespace App\Controllers;

        class UserController
        {
            public function __construct()
            {
                $this->service = new Services\UserService();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that Services\UserService is in dependencies
        $this->assertContains('App\Controllers\Services\UserService', $dependencies);
    }

    public function test_resolves_class_names_with_use_statements()
    {
        // Define PHP code with use statements
        $code = <<<'CODE'
        <?php
        namespace App\Controllers;

        use App\Services\UserService;

        class UserController
        {
            public function __construct()
            {
                $this->service = new UserService();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService is in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
    }

    public function test_resolves_class_names_with_use_aliases()
    {
        // Define PHP code with use aliases
        $code = <<<'CODE'
        <?php
        namespace App\Controllers;

        use App\Services\UserService as Service;

        class UserController
        {
            public function __construct()
            {
                $this->service = new Service();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService is in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
    }

    public function test_resolves_class_names_with_group_use_statements()
    {
        // Define PHP code with grouped use statements
        $code = <<<'CODE'
        <?php
        namespace App\Controllers;

        use App\Services\{UserService, AuthService};

        class UserController
        {
            public function __construct()
            {
                $this->userService = new UserService();
                $this->authService = new AuthService();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService and AuthService are in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
        $this->assertContains('App\Services\AuthService', $dependencies);
    }

    public function test_excludes_reserved_names()
    {
        // Define PHP code using reserved names in a valid way
        $code = <<<'CODE'
        <?php
        namespace App;

        class Sample
        {
            public function test(self $selfParam, parent $parentParam)
            {
                // Method logic
            }

            public static function getStatic(): self
            {
                return new self();
            }

            public function returnStatic(): static
            {
                return $this;
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that reserved names are not in dependencies
        $this->assertNotContains('self', $dependencies);
        $this->assertNotContains('static', $dependencies);
        $this->assertNotContains('parent', $dependencies);
    }

    public function test_handles_files_without_namespace()
    {
        // Define PHP code without a namespace
        $code = <<<'CODE'
        <?php

        class UserService
        {
            public function __construct()
            {
                $this->repository = new UserRepository();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserRepository is in dependencies
        $this->assertContains('UserRepository', $dependencies);
    }

    public function test_handles_multiple_classes_in_file()
    {
        // Define PHP code with multiple classes
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Services\UserService;
        use App\Repositories\UserRepository;

        class UserController
        {
            public function __construct()
            {
                $this->service = new UserService();
            }
        }

        class AdminController
        {
            public function __construct()
            {
                $this->repository = new UserRepository();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService and UserRepository are in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
        $this->assertContains('App\Repositories\UserRepository', $dependencies);
    }

    public function test_handles_files_with_multiple_use_statements()
    {
        // Define PHP code with multiple use statements
        $code = <<<'CODE'
        <?php
        namespace App\Controllers;

        use App\Services\UserService;
        use App\Repositories\UserRepository;

        class UserController
        {
            public function __construct()
            {
                $this->service = new UserService();
                $this->repository = new UserRepository();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService and UserRepository are in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
        $this->assertContains('App\Repositories\UserRepository', $dependencies);
    }

    public function test_handles_union_and_intersection_types()
    {
        // Define PHP code with union and intersection types
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Services\UserService;
        use App\Services\AuthService;

        class UserController
        {
            public function handle(UserService|AuthService $service)
            {
                // Method logic
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService and AuthService are in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
        $this->assertContains('App\Services\AuthService', $dependencies);
    }

    public function test_handles_nullable_types()
    {
        // Define PHP code with nullable types
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Services\UserService;

        class UserController
        {
            private ?UserService $service;

            public function getService(): ?UserService
            {
                return $this->service;
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService is in dependencies
        $this->assertContains('App\Services\UserService', $dependencies);
    }

    public function test_dependencies_excludes_declarations()
    {
        // Define PHP code declaring classes and using them
        $code = <<<'CODE'
        <?php
        namespace App;

        class UserService
        {
            public function doSomething()
            {
                // Logic
            }
        }

        class UserController
        {
            public function __construct()
            {
                $this->service = new UserService();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService is not in dependencies since it's declared in the same file
        $this->assertNotContains('App\UserService', $dependencies);
    }

    public function test_dependencies_are_unique()
    {
        // Define PHP code with multiple usages of the same class
        $code = <<<'CODE'
        <?php
        namespace App;

        use App\Services\UserService;

        class UserController
        {
            public function __construct()
            {
                $this->service1 = new UserService();
                $this->service2 = new UserService();
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that UserService appears only once in dependencies
        $this->assertCount(1, $dependencies);
        $this->assertContains('App\Services\UserService', $dependencies);
    }

    public function test_dependencies_handles_empty_files()
    {
        // Define empty PHP code
        $code = <<<'CODE'
        <?php
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that dependencies list is empty
        $this->assertEmpty($dependencies);
    }

    public function test_dependencies_with_no_dependencies()
    {
        // Define PHP code with a class that has no dependencies
        $code = <<<'CODE'
        <?php
        namespace App;

        class SimpleClass
        {
            public function doNothing()
            {
                // No dependencies
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that dependencies list is empty
        $this->assertEmpty($dependencies);
    }

    public function test_dependencies_with_nested_traits()
    {
        // Define PHP code with traits using other traits
        $code = <<<'CODE'
        <?php
        namespace App\Traits;

        trait Loggable
        {
            use Formatter;
        }

        trait Formatter
        {
            // Formatting logic
        }

        namespace App;

        use App\Traits\Loggable;

        class UserService
        {
            use Loggable;

            public function perform()
            {
                // Service logic
            }
        }
        CODE;

        // Get dependencies from the code
        $dependencies = $this->getDependencies($code);

        // Assert that Formatter is NOT in dependencies since it's declared in the same file
        $this->assertNotContains('App\Traits\Formatter', $dependencies);
    }
}
