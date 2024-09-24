<?php

namespace Thomasmarinissen\ClassDependencyResolver;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UnionType;
use PhpParser\NodeVisitorAbstract;

/**
 * Class DependencyExtractor
 *
 * Extracts fully qualified class names, interfaces, traits, and dependencies
 * from a parsed PHP file. It handles namespace `use` statements (including grouped uses),
 * class instantiations, class/interface/trait declarations, and trait uses within classes.
 */
class DependencyExtractor extends NodeVisitorAbstract
{
    /**
     * @var array List of extracted class dependencies (fully qualified class names).
     */
    protected array $dependencies = [];

    /**
     * @var string|null The current namespace of the file being processed.
     */
    protected ?string $currentNamespace = null;

    /**
     * @var array List of fully qualified class names declared in the file.
     */
    protected array $classNames = [];

    /**
     * @var array List of fully qualified interface names declared in the file.
     */
    protected array $declaredInterfaces = [];

    /**
     * @var array List of fully qualified trait names declared in the file.
     */
    protected array $declaredTraits = [];

    /**
     * @var array A list of fully qualified interfaces implemented by the class.
     */
    protected array $interfaceNames = [];

    /**
     * @var array List of fully qualified trait names being used within classes.
     */
    protected array $traitNames = [];

    /**
     * @var array<string, string> Mapping of alias to fully qualified class names from `use` statements.
     */
    protected array $useStatements = [];

    /**
     * @var array List of reserved keywords and built-in types to exclude from dependencies.
     */
    protected array $reservedNames = ['self', 'static', 'parent', 'callable', 'iterable', 'void', 'bool', 'int', 'float',
        'string', 'array', 'object', 'mixed', 'resource', 'null', 'false', 'true', 'never', ];

    /**
     * Enter each node in the parsed file and handle the necessary logic for extracting dependencies.
     *
     * @param  Node  $node  The current node being processed.
     * @return null|int|Node|Node[] Return values as per the NodeVisitor interface.
     */
    public function enterNode(Node $node): null|int|Node|array
    {
        // Check if the node is a namespace declaration, if so, set the current namespace.
        if ($node instanceof Namespace_) {
            $this->setCurrentNamespace($node);
        }

        // Check if the node is a use statement or a group use statement, if so, add it to the useStatements property.
        if ($node instanceof Use_ || $node instanceof GroupUse) {
            $this->addUseStatements($node);
        }

        // Check if the node is a class declaration, if so, process the class declaration
        if ($node instanceof Class_) {
            $this->processClassDeclaration($node);
        }

        // Check if the node is an interface declaration, if so, add the interface name to the declaredInterfaces property.
        if ($node instanceof Interface_) {
            $this->addDeclaredInterface($node);
        }

        // Check if the node is a trait declaration, if so, add the trait name to the declaredTraits property.
        if ($node instanceof Trait_) {
            $this->addDeclaredTrait($node);
        }

        // Check if the node is a class instantiation, if so, add the instantiated class to the dependencies.
        if ($node instanceof New_) {
            $this->addClassInstantiation($node);
        }

        // Check if the node is a static method call, if so, add the class used in the static call to the dependencies.
        if ($node instanceof StaticCall) {
            $this->addStaticCall($node);
        }

        // Check if the node is an assignment with a new class instantiation, if so, add the instantiated class to the dependencies.
        if ($node instanceof Assign && $node->expr instanceof New_) {
            $this->addAssignNew($node);
        }

        // Handle instanceof expressions
        if ($node instanceof Instanceof_ && $node->class instanceof Name) {
            $this->addDependency($this->resolveClassName($node->class));
        }

        // Handle catch clauses
        if ($node instanceof Catch_) {
            foreach ($node->types as $type) {
                $this->addDependency($this->resolveClassName($type));
            }
        }

        // Process parameter types
        if ($node instanceof Param) {
            $this->processParamType($node);
        }

        // Process property types
        if ($node instanceof Property) {
            $this->processPropertyType($node);
        }

        // Process method return types
        if ($node instanceof ClassMethod) {
            $this->processMethodReturnType($node);
        }

        // Continue traversing without modification.
        return null;
    }

    /**
     * Get the list of fully qualified dependencies, including classes, interfaces, traits being used, and use statements.
     *
     * @return array The list of unique fully qualified class names.
     */
    public function dependencies(): array
    {
        // Merge dependencies, interfaceNames, traitNames, and useStatements values into one array.
        $allDependencies = array_merge($this->dependencies, $this->interfaceNames(), $this->traitNames());

        // Exclude traits, interfaces, classes aliased in the file.
        $internalDeclarations = array_merge($this->classNames(), $this->declaredInterfaces(), $this->declaredTraits());

        // Filter out internal declarations from dependencies.
        $externalDependencies = array_diff($allDependencies, $internalDeclarations);

        // Return the unique external dependencies.
        return array_unique($externalDependencies);
    }

    /**
     * Get the list of fully qualified class names declared in the file.
     *
     * @return array The list of fully qualified class names.
     */
    public function classNames(): array
    {
        return $this->classNames;
    }

    /**
     * Get the list of fully qualified interface names declared in the file.
     *
     * @return array The list of fully qualified interface names.
     */
    public function declaredInterfaces(): array // Renamed method
    {
        return $this->declaredInterfaces;
    }

    /**
     * Get the list of fully qualified trait names declared in the file.
     *
     * @return array The list of fully qualified trait names.
     */
    public function declaredTraits(): array
    {
        return $this->declaredTraits;
    }

    /**
     * Get the interface names implemented by the class.
     *
     * @return array The list of fully qualified interface names.
     */
    public function interfaceNames(): array
    {
        return $this->interfaceNames;
    }

    /**
     * Get the list of fully qualified trait names being used within classes.
     *
     * @return array The list of fully qualified trait names.
     */
    public function traitNames(): array
    {
        return $this->traitNames;
    }

    /**
     * Get the use statements mapping alias to fully qualified class names.
     *
     * @return array<string, string> The use statements mapping.
     */
    public function useStatements(): array
    {
        return $this->useStatements;
    }

    /**
     * Set the current namespace based on the namespace node.
     *
     * @param  Namespace_  $node  The namespace node from which to extract the current namespace.
     */
    protected function setCurrentNamespace(Namespace_ $node): void
    {
        // Check if the namespace node has a name.
        if ($node->name instanceof Name) {
            // Set the current namespace without a leading backslash.
            $this->currentNamespace = $node->name->toString();
        } else {
            // If no namespace is declared, set it to null.
            $this->currentNamespace = null;
        }
    }

    /**
     * Add all the `use` statements (including grouped uses) from the node to the useStatements property.
     *
     * @param  Node  $node  The `use` statement node.
     */
    protected function addUseStatements(Node $node): void
    {
        // Check if the node is a standard use statement.
        if ($node instanceof Use_) {
            // Handle standard use statements.
            $this->handleUseStatement($node);
        } // Check if the node is a group use statement.
        elseif ($node instanceof GroupUse) {
            // Handle grouped use statements.
            $this->handleGroupUseStatement($node);
        }
    }

    /**
     * Handle a standard `use` statement and add it to useStatements and dependencies.
     *
     * @param  Use_  $use  The `use` statement node.
     */
    protected function handleUseStatement(Use_ $use): void
    {
        // Iterate through each use within the use statement.
        foreach ($use->uses as $useUse) {
            // Construct the fully qualified name without a leading backslash.
            $fullName = $useUse->name->toString();

            // Determine the alias; use the provided alias or the last part of the name.
            $alias = $useUse->alias ? $useUse->alias->toString() : $useUse->name->getLast();

            // Map the alias to the fully qualified name in useStatements.
            $this->useStatements[$alias] = $fullName;
        }
    }

    /**
     * Handle a group `use` statement and add it to useStatements and dependencies.
     *
     * @param  GroupUse  $node  The group use statement node.
     */
    protected function handleGroupUseStatement(GroupUse $node): void
    {
        // Construct the prefix of the group use without a leading backslash.
        $prefix = $node->prefix->toString();

        // Iterate through each use within the group.
        foreach ($node->uses as $useUse) {
            // Combine the prefix with the use name to form the fully qualified name.
            $fullName = $prefix . '\\' . $useUse->name->toString();

            // Determine the alias; use the provided alias or the last part of the name.
            $alias = $useUse->alias ? $useUse->alias->toString() : $useUse->name->getLast();

            // Map the alias to the fully qualified name in useStatements.
            $this->useStatements[$alias] = $fullName;
        }
    }

    /**
     * Add the fully qualified class name of a class instantiation to the dependencies array.
     *
     * @param  New_  $node  The class instantiation node.
     */
    protected function addClassInstantiation(New_ $node): void
    {
        // Extract the class node from the instantiation
        $classNode = $node->class;

        // Ensure the class node is a Name node
        if (!$classNode instanceof Name) {
            return;
        }

        // Resolve the fully qualified class name
        $fullyQualifiedName = $this->resolveClassName($classNode);

        // Add the dependency
        $this->addDependency($fullyQualifiedName);
    }

    /**
     * Add the fully qualified class name from a static method call to the dependencies array.
     *
     * @param  StaticCall  $node  The static call node.
     */
    protected function addStaticCall(StaticCall $node): void
    {
        // Check if the class part of the static call is a Name node.
        if (!$node->class instanceof Name) {
            // If not, exit the method.
            return;
        }

        // Resolve the class name using the node
        $fullyQualifiedName = $this->resolveClassName($node->class);

        // Add the fully qualified class name to the dependencies array.
        $this->addDependency($fullyQualifiedName);
    }

    /**
     * Add the fully qualified class name from an assignment with a new instantiation to the dependencies array.
     *
     * @param  Assign  $node  The assignment node.
     */
    protected function addAssignNew(Assign $node): void
    {
        // Ensure the expression being assigned is a new instantiation.
        if ($node->expr instanceof New_) {
            // Add the instantiated class to the dependencies array.
            $this->addClassInstantiation($node->expr);
        }
    }

    /**
     * Add the fully qualified trait names used within a class to the traitNames array.
     *
     * @param  Class_  $node  The class declaration node.
     */
    protected function processClassTraits(Class_ $node): void
    {
        // Iterate through each statement within the class.
        foreach ($node->stmts as $stmt) {
            // Check if the statement is a TraitUse statement.
            if (!$stmt instanceof TraitUse) {
                // If not, skip to the next statement.
                continue;
            }

            // Iterate through each trait used in the TraitUse statement.
            foreach ($stmt->traits as $trait) {
                // Resolve the trait name using the node
                $fullyQualifiedTraitName = $this->resolveClassName($trait);

                // Add the fully qualified trait name to the traitNames array.
                $this->traitNames[] = $fullyQualifiedTraitName;
            }
        }
    }

    /**
     * Process a class declaration
     *
     * @param  Class_  $node  The class declaration node.
     */
    protected function processClassDeclaration(Class_ $node): void
    {
        // Add the class name to the classNames property.
        $this->addClassName($node);

        // Process traits used within the class and add them to traitNames.
        $this->processClassTraits($node);

        // Process interfaces implemented by the class.
        $this->processClassImplementations($node);

        // Process class inheritance.
        $this->processClassInheritance($node);
    }

    /**
     * Add the fully qualified class name to the classNames array.
     *
     * @param  Class_  $node  The class declaration node.
     */
    protected function addClassName(Class_ $node): void
    {
        // Check if the class node has a name.
        if (!$node->name instanceof Identifier) {
            // If not, exit the method.
            return;
        }

        // Add the fully qualified class name to the classNames array.
        $this->classNames[] = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . $node->name->toString();
    }

    /**
     * Add the fully qualified interface name to the declaredInterfaces array.
     *
     * @param  Interface_  $node  The interface declaration node.
     */
    protected function addDeclaredInterface(Interface_ $node): void
    {
        // Check if the interface node has a name.
        if (!$node->name instanceof Identifier) {
            // If not, exit the method.
            return;
        }

        // Add the fully qualified interface name to the declaredInterfaces array.
        $this->declaredInterfaces[] = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . $node->name->toString();
    }

    /**
     * Add the fully qualified trait name to the declaredTraits array.
     *
     * @param  Trait_  $node  The trait declaration node.
     */
    protected function addDeclaredTrait(Trait_ $node): void
    {
        // Check if the trait node has a name.
        if (!$node->name instanceof Identifier) {
            // If not, exit the method.
            return;
        }

        // Add the fully qualified trait name to the declaredTraits array.
        $this->declaredTraits[] = ($this->currentNamespace ? $this->currentNamespace . '\\' : '') . $node->name->toString();
    }

    /**
     * Resolve a class name to its fully qualified name using `use` statements and current namespace.
     *
     * @param  Name  $name  The class name node to resolve.
     * @return string The fully qualified class name.
     */
    protected function resolveClassName(Name $name): string
    {
        // Check if the name is a reserved keyword or built-in type (e.g., self, static, int, string)
        if (in_array(strtolower($name->toString()), $this->reservedNames, true)) {
            return $name->toString(); // Return as is, since it's a reserved name or built-in type.
        }

        // Check if the name is already fully qualified (starts with a backslash)
        if ($name->isFullyQualified()) {
            // Return the fully qualified name without the leading backslash.
            return ltrim($name->toString(), '\\');
        }

        // Check if the name is relative (e.g., starts with 'namespace\')
        if ($name->isRelative()) {
            // Prepend the current namespace to the relative name.
            return $this->currentNamespace
                ? $this->currentNamespace . '\\' . $name->toString()
                : $name->toString();
        }

        // Check if the name has been aliased via a use statement
        $firstPart = $name->getFirst();
        if (isset($this->useStatements[$firstPart])) {
            // Use the alias to fully qualify the name.
            $useFQN = $this->useStatements[$firstPart];
            $remainingName = $name->slice(1);
            $remainingParts = $remainingName ? $remainingName->toString() : '';

            // Combine the alias with the remaining parts of the name.
            return $remainingParts
                ? $useFQN . '\\' . $remainingParts
                : $useFQN;
        }

        // If none of the above, prepend the current namespace (if it exists) to the class name.
        return $this->currentNamespace
            ? $this->currentNamespace . '\\' . $name->toString()
            : $name->toString();
    }

    /**
     * Helper method to add a dependency to the dependencies array.
     *
     * @param  string  $dependency  The dependency to add.
     */
    protected function addDependency(string $dependency): void
    {
        // Normalize the dependency name to lowercase for comparison
        $normalizedDependency = strtolower($dependency);

        // If the dependency is reserved, skip adding it
        if (in_array($normalizedDependency, $this->reservedNames, true)) {
            return;
        }

        // If the dependency is not already in the dependencies array, add it.
        if (!in_array($dependency, $this->dependencies, true)) {
            $this->dependencies[] = $dependency;
        }
    }

    /**
     * Process interfaces implemented by the class and add them to dependencies.
     *
     * @param  Class_  $node  The class declaration node.
     */
    protected function processClassImplementations(Class_ $node): void
    {
        // Iterate through each implemented interface, resolve the class name, and add it to the interfaceNames array.
        foreach ($node->implements as $interface) {
            $this->interfaceNames[] = $this->resolveClassName($interface);
        }
    }

    /**
     * Process the class inheritance and add the fully qualified name of the extended class to dependencies.
     *
     * @param  Class_  $node  The class declaration node.
     */
    protected function processClassInheritance(Class_ $node): void
    {
        // Check if the class extends another class, if so, add the fully qualified name to the dependencies.
        if ($node->extends instanceof Name) {
            $this->addDependency($this->resolveClassName($node->extends));
        }
    }

    /**
     * Process the parameter type and add it to dependencies if it's a class.
     *
     * @param  Param  $param  The parameter node.
     */
    protected function processParamType(Param $param): void
    {
        // Check if the parameter has a type, if so, process the type.
        if ($param->type) {
            $this->processType($param->type);
        }
    }

    /**
     * Process the property type and add it to dependencies if it's a class.
     *
     * @param  Property  $property  The property node.
     */
    protected function processPropertyType(Property $property): void
    {
        // Check if the property has a type, if so, process the type.
        if ($property->type) {
            $this->processType($property->type);
        }
    }

    /**
     * Process the return method type and add it to dependencies if it's a class.
     *
     * @param  ClassMethod  $method  The class method node.
     */
    protected function processMethodReturnType(ClassMethod $method): void
    {
        // Check if the method has a return type, if so, process the type.
        if ($method->returnType) {
            $this->processType($method->returnType);
        }
    }

    /**
     * Process a type node (can be a simple type, nullable type, union type, or intersection type).
     *
     * @param  Node  $type  The type node.
     */
    protected function processType(Node $type): void
    {
        if ($type instanceof Name) {
            // Resolve the fully qualified class name
            $fullyQualifiedName = $this->resolveClassName($type);

            // Add the dependency
            $this->addDependency($fullyQualifiedName);
        } elseif ($type instanceof NullableType) {
            // Process the underlying type
            $this->processType($type->type);
        } elseif ($type instanceof UnionType || $type instanceof IntersectionType) {
            // Process each type in the union or intersection
            foreach ($type->types as $subType) {
                $this->processType($subType);
            }
        }
    }
}
